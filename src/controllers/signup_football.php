<?php
declare(strict_types=1);

const FUTBOL_MAX_MIEMBROS = 12;

function ctrl_signup_football(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { signup_football_post(); return; }
    signup_football_form([]);
}

function signup_football_form(array $errors, array $in = []): void {
    $err = $errors ? '<div class="errors"><ul><li>' . implode('</li><li>', array_map('e', $errors)) . '</li></ul></div>' : '';
    $csrf = csrf_field();
    $nom = e($in['nombre_adulto'] ?? ''); $ema = e($in['email'] ?? ''); $tel = e($in['telefono'] ?? '');
    $eq  = e($in['nombre_equipo'] ?? '');
    $rows = '';
    for ($i = 0; $i < FUTBOL_MAX_MIEMBROS; $i++) {
        $mn = e($in['m_nombre'][$i] ?? ''); $me = e($in['m_edad'][$i] ?? '');
        $socioSel = ($in['m_socio'][$i] ?? '') === '1';
        $rows .= '<tr>'
            . '<td><input name="m_nombre[]" value="' . $mn . '" maxlength="120"></td>'
            . '<td><input type="number" name="m_edad[]" value="' . $me . '" min="1" max="14" class="edad"></td>'
            . '<td><select name="m_socio[]"><option value="0"' . ($socioSel ? '' : ' selected') . '>No (5€)</option>'
            . '<option value="1"' . ($socioSel ? ' selected' : '') . '>Sí (3€)</option></select></td>'
            . '</tr>';
    }

    $banner = preins_banner();
    $aviso = AVISO_NO_PAGO;
    $c = <<<HTML
$banner
<div class="aviso">⚠️ $aviso</div>
<h1>Inscripción de fútbol · equipos de menores (hasta 14 años)</h1>
<p class="muted">La inscripción la formaliza una persona adulta responsable y paga una sola
persona: el total es la suma por jugador (3 € socio / 5 € no socio). De los jugadores solo
pedimos nombre y edad.</p>
$err
<form method="post" action="/preinscripcion-futbol" class="form" novalidate>
  $csrf
  <fieldset>
    <legend>Responsable (adulto)</legend>
    <label>Nombre y apellidos <input name="nombre_adulto" value="$nom" required maxlength="120"></label>
    <label>Email <input type="email" name="email" value="$ema" required maxlength="190"></label>
    <label>Teléfono <input name="telefono" value="$tel" required maxlength="30"></label>
  </fieldset>
  <fieldset>
    <legend>Equipo</legend>
    <label>Nombre del equipo <input name="nombre_equipo" value="$eq" required maxlength="60"></label>
    <table class="miembros">
      <thead><tr><th>Jugador/a (nombre y apellidos)</th><th>Edad</th><th>¿Socio?</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
    <p class="note">Rellena solo las filas que necesites.</p>
  </fieldset>
  <fieldset class="consent">
    <label class="chk"><input type="checkbox" name="permiso" value="1" required>
      Declaro que soy una persona adulta responsable y que cuento con la autorización de los
      padres/madres o tutores de los menores que inscribo.</label>
    <label class="chk"><input type="checkbox" name="rgpd" value="1" required>
      He leído y acepto la <a href="/privacidad" target="_blank">política de privacidad</a>.</label>
  </fieldset>
  <button class="primary" type="submit">Enviar inscripción del equipo</button>
</form>
HTML;
    render('Inscripción fútbol', $c, current_user());
}

function signup_football_post(): void {
    csrf_check();
    if (preins_estado() !== 'abierta') { signup_football_form(['La preinscripción no está abierta en este momento.']); return; }
    if (!rate_limit('signup_fut', 60, 3600)) { signup_football_form(['Demasiados envíos. Inténtalo más tarde.']); return; }
    $in = [
        'nombre_adulto' => trim((string)($_POST['nombre_adulto'] ?? '')),
        'email'         => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
        'telefono'      => trim((string)($_POST['telefono'] ?? '')),
        'nombre_equipo' => trim((string)($_POST['nombre_equipo'] ?? '')),
        'm_nombre'      => (array)($_POST['m_nombre'] ?? []),
        'm_edad'        => (array)($_POST['m_edad'] ?? []),
        'm_socio'       => (array)($_POST['m_socio'] ?? []),
    ];
    $errors = [];
    if ($in['nombre_adulto'] === '') $errors[] = 'Falta el nombre del responsable.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
    if ($in['telefono'] === '' || !preg_match('/^[0-9 +()\-]{6,30}$/', $in['telefono'])) $errors[] = 'El teléfono no es válido.';
    if ($in['nombre_equipo'] === '') $errors[] = 'Falta el nombre del equipo.';
    if (empty($_POST['permiso'])) $errors[] = 'Debes declarar que cuentas con la autorización de los tutores.';
    if (empty($_POST['rgpd'])) $errors[] = 'Debes aceptar la política de privacidad.';

    // Miembros válidos
    $miembros = [];
    foreach ($in['m_nombre'] as $i => $nombre) {
        $nombre = trim((string)$nombre);
        if ($nombre === '') continue;
        $edad = (int)($in['m_edad'][$i] ?? 0);
        $socio = ($in['m_socio'][$i] ?? '0') === '1';
        if ($edad < 1 || $edad > EDAD_MAX_INFANTIL) {
            $errors[] = 'Cada jugador debe tener una edad entre 1 y ' . EDAD_MAX_INFANTIL . " ($nombre).";
            continue;
        }
        $miembros[] = ['nombre' => $nombre, 'edad' => $edad, 'socio' => $socio];
    }
    if (count($miembros) > FUTBOL_MAX_MIEMBROS) $miembros = array_slice($miembros, 0, FUTBOL_MAX_MIEMBROS);
    if (!$miembros) $errors[] = 'Añade al menos un jugador con su edad.';

    if ($errors) { signup_football_form($errors, $in); return; }

    $total = 0.0;
    foreach ($miembros as $m) $total += price_for($m['socio']);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cuentaId = find_or_create_cuenta($in['email'], $in['nombre_adulto'], $in['telefono']);
        $pdo->prepare('INSERT INTO equipo(cuenta_id, nombre_equipo, precio_total_eur, permiso_menores, permiso_menores_at) VALUES(?,?,?,1,NOW())')
            ->execute([$cuentaId, $in['nombre_equipo'], $total]);
        $eqId = (int)$pdo->lastInsertId();
        $im = $pdo->prepare('INSERT INTO equipo_miembro(equipo_id, nombre_completo, edad, es_socio) VALUES(?,?,?,?)');
        foreach ($miembros as $m) $im->execute([$eqId, $m['nombre'], $m['edad'], $m['socio'] ? 1 : 0]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        audit('signup_fut_error', $ex->getMessage());
        signup_football_form(['No hemos podido registrar el equipo. Inténtalo de nuevo.']);
        return;
    }
    audit('signup_fut_ok', 'equipo=' . $in['nombre_equipo']);
    email_preinscripcion($in['email'], $in['nombre_adulto'],
        ['Equipo ' . $in['nombre_equipo'] . ' · ' . count($miembros) . ' jugadores'], $total);
    signup_success();
}
