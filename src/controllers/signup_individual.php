<?php
declare(strict_types=1);

function ctrl_signup_individual(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        signup_individual_post();
        return;
    }
    signup_individual_form([]);
}

function signup_individual_form(array $errors, array $in = []): void {
    $err = $errors ? '<div class="errors"><ul><li>' . implode('</li><li>', array_map('e', $errors)) . '</li></ul></div>' : '';
    $adultos   = disciplinas('adulto');
    $infantil  = disciplinas('infantil');
    $checks = function (array $list) use ($in): string {
        $sel = $in['disciplinas'] ?? [];
        $out = '';
        foreach ($list as $d) {
            $id = (int)$d['id'];
            $ck = in_array((string)$id, array_map('strval', (array)$sel), true) ? ' checked' : '';
            $out .= '<label class="chk"><input type="checkbox" name="disciplinas[]" value="' . $id . '"' . $ck . '> '
                  . e($d['nombre']) . ' <span class="tag">' . e($d['tipo']) . '</span></label>';
        }
        return $out;
    };
    $csrf = csrf_field();
    $nom = e($in['nombre_adulto'] ?? ''); $ema = e($in['email'] ?? ''); $tel = e($in['telefono'] ?? '');
    $pnom = e($in['participante'] ?? ''); $nsoc = e($in['num_socio'] ?? '');
    $pnj = e($in['pareja_nombre'] ?? ''); $pnjns = e($in['pareja_num_socio'] ?? '');
    $pjSi = ($in['pareja_socio'] ?? false) ? ' checked' : '';
    $pjNo = ($in['pareja_socio'] ?? false) ? '' : ' checked';
    $adultosH = $checks($adultos); $infantilH = $checks($infantil);
    $banner = preins_banner();
    $aviso = AVISO_NO_PAGO;

    $c = <<<HTML
$banner
<div class="aviso">⚠️ $aviso</div>
<h1>Preinscripción · deportes y actividades sociales</h1>
<p class="muted">Recuerda: máximo 3 actividades (2 deportivas + 1 social, o 2 sociales + 1 deportiva).
En pádel: elige <strong>Pádel</strong> (nivel 4 o inferior) <strong>o</strong> <strong>Pádel+4</strong> (nivel superior), no ambos.
La preinscripción no da plaza hasta que pagas en el club.</p>
<div class="banner info">👥 <strong>¿Juegas dobles o quieres apuntar a más gente?</strong>
Apunta a una persona y envía; al terminar podrás <strong>añadir a tu pareja o a tu familia</strong>
rellenando el formulario otra vez. Puedes usar el <strong>mismo email y teléfono</strong> para todas.</div>
$err
<form method="post" action="/preinscripcion" class="form" novalidate>
  $csrf
  <fieldset>
    <legend>Persona que inscribe (adulto)</legend>
    <label>Nombre y apellidos <input name="nombre_adulto" value="$nom" required maxlength="120"></label>
    <label>Email <input type="email" name="email" value="$ema" required maxlength="190"></label>
    <label>Teléfono <input name="telefono" value="$tel" required maxlength="30"></label>
  </fieldset>

  <fieldset>
    <legend>Participante</legend>
    <label>Nombre y apellidos del participante <input name="participante" value="$pnom" required maxlength="120"></label>
    <div class="radios">
      <span>¿Es menor de edad?</span>
      <label><input type="radio" name="es_menor" value="0" checked> No (adulto/a)</label>
      <label><input type="radio" name="es_menor" value="1"> Sí, es menor de 18</label>
    </div>
    <label>Edad (si es menor) <input type="number" name="edad" min="1" max="17"></label>
    <p class="note">Los <strong>jóvenes de 15 a 17 años</strong> que juegan con los adultos: marca "Sí, es menor",
    pon su edad y elige las <strong>actividades de adultos</strong> de abajo.</p>
    <div class="radios">
      <span>¿Es socio/a del club?</span>
      <label><input type="radio" name="socio" value="1"> Sí (3 €/actividad)</label>
      <label><input type="radio" name="socio" value="0" checked> No (5 €/actividad)</label>
    </div>
    <label>Nº de socio (si lo sabes) <input name="num_socio" value="$nsoc" maxlength="10"></label>
  </fieldset>

  <fieldset>
    <legend>Actividades — adultos (y jóvenes de 15+ que juegan en adultos)</legend>
    <div class="checks">$adultosH</div>
  </fieldset>
  <fieldset>
    <legend>Actividades — infantil (menores)</legend>
    <div class="checks">$infantilH</div>
  </fieldset>
  <fieldset>
    <legend>Pádel</legend>
    <label>Nivel de pádel (4 o inferior) <input type="number" name="nivel_padel" min="1" max="4"></label>
  </fieldset>

  <fieldset>
    <legend>¿Apuntas también a tu pareja/compañero? (opcional)</legend>
    <label>Nombre y apellidos de tu pareja <input name="pareja_nombre" value="$pnj" maxlength="120"></label>
    <div class="radios">
      <span>¿Es socio/a?</span>
      <label><input type="radio" name="pareja_socio" value="1"$pjSi> Sí (3 €)</label>
      <label><input type="radio" name="pareja_socio" value="0"$pjNo> No (5 €)</label>
    </div>
    <label>Nº de socio de tu pareja (si lo es) <input name="pareja_num_socio" value="$pnjns" maxlength="10"></label>
    <p class="note">Si rellenas esto, apuntas a tu pareja <strong>a las mismas actividades que tú</strong> y quedáis
    <strong>emparejados</strong> automáticamente (cada uno con su precio). Úsalo <strong>solo si tu pareja aún no se ha
    apuntado</strong>; si ya lo hizo por su cuenta, déjalo vacío.</p>
  </fieldset>

  <fieldset class="consent">
    <label class="chk"><input type="checkbox" name="rgpd" value="1" required>
      He leído y acepto la <a href="/privacidad" target="_blank">política de privacidad</a> y
      consiento el tratamiento de mis datos (y, en su caso, los del menor a mi cargo) para
      gestionar la inscripción y las comunicaciones del evento.</label>
  </fieldset>
  <button class="primary" type="submit">Enviar preinscripción</button>
</form>
HTML;
    render('Preinscripción', $c, current_user());
}

function signup_individual_post(): void {
    csrf_check();
    if (preins_estado() !== 'abierta') {
        signup_individual_form(['La preinscripción no está abierta en este momento.']);
        return;
    }
    if (!rate_limit('signup', 60, 3600)) {
        signup_individual_form(['Demasiados envíos desde tu conexión. Inténtalo más tarde.']);
        return;
    }
    $in = [
        'nombre_adulto' => trim((string)($_POST['nombre_adulto'] ?? '')),
        'email'         => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
        'telefono'      => trim((string)($_POST['telefono'] ?? '')),
        'participante'  => trim((string)($_POST['participante'] ?? '')),
        'es_menor'      => ($_POST['es_menor'] ?? '0') === '1',
        'edad'          => trim((string)($_POST['edad'] ?? '')),
        'socio'         => ($_POST['socio'] ?? '0') === '1',
        'num_socio'     => trim((string)($_POST['num_socio'] ?? '')),
        'nivel_padel'   => trim((string)($_POST['nivel_padel'] ?? '')),
        'pareja_nombre' => trim((string)($_POST['pareja_nombre'] ?? '')),
        'pareja_socio'  => ($_POST['pareja_socio'] ?? '0') === '1',
        'pareja_num_socio' => trim((string)($_POST['pareja_num_socio'] ?? '')),
        'disciplinas'   => array_values(array_filter((array)($_POST['disciplinas'] ?? []), 'is_numeric')),
    ];
    $errors = [];
    if ($in['nombre_adulto'] === '') $errors[] = 'Falta el nombre de la persona que inscribe.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido.';
    if ($in['telefono'] === '' || !preg_match('/^[0-9 +()\-]{6,30}$/', $in['telefono'])) $errors[] = 'El teléfono no es válido.';
    if ($in['participante'] === '') $errors[] = 'Falta el nombre del participante.';
    if (empty($_POST['rgpd'])) $errors[] = 'Debes aceptar la política de privacidad para continuar.';

    // Cargar disciplinas elegidas y validar categoría + combinación
    $ids = array_map('intval', $in['disciplinas']);
    $discs = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT * FROM disciplina WHERE id IN ($ph) AND activa = 1");
        $st->execute($ids);
        $discs = $st->fetchAll();
    }
    if (!$discs) $errors[] = 'Selecciona al menos una actividad.';
    // La categoría (adulto/infantil) se DERIVA de las actividades; deben ser todas de una sola.
    $ambitos = array_unique(array_column($discs, 'ambito'));
    if (count($ambitos) > 1) $errors[] = 'Elige actividades de una sola categoría (adultos o infantil), no mezcladas.';
    $ambito = $ambitos ? reset($ambitos) : 'adulto';
    // Edad: obligatoria en infantil y también si se marca "es menor" (jóvenes 15-17 que juegan en adultos).
    $edad = null;
    if ($ambito === 'infantil' || $in['es_menor']) {
        $edad = (int)$in['edad'];
        if ($edad < 1 || $edad > 17) $errors[] = 'Indica la edad del menor (1–17).';
    }
    $tipos = array_column($discs, 'tipo');
    if ($discs && ($msg = validar_combinacion($tipos))) $errors[] = $msg;

    // Pádel y Pádel+4 son la misma actividad por nivel: no ambas a la vez.
    $nombres = array_column($discs, 'nombre');
    if (in_array('Pádel', $nombres, true) && in_array('Pádel+4', $nombres, true)) {
        $errors[] = 'No puedes apuntarte a Pádel y Pádel+4 a la vez: elige tu categoría por nivel.';
    }

    // Solo el "Pádel" (nivel 4 o inferior) pide nivel; "Pádel+4" es la categoría superior.
    $pidePadel = (bool)array_filter($discs, fn($d) => (int)$d['pide_nivel'] === 1);
    $nivel = null;
    if ($pidePadel) {
        $nivel = (int)$in['nivel_padel'];
        if ($nivel < 1 || $nivel > NIVEL_PADEL_MAX) $errors[] = 'En pádel indica un nivel entre 1 y ' . NIVEL_PADEL_MAX . '.';
    }

    if ($errors) { signup_individual_form($errors, $in); return; }

    // Persistir (transacción)
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cuentaId = find_or_create_cuenta($in['email'], $in['nombre_adulto'], $in['telefono']);
        $partId = find_or_create_participante($cuentaId, $in['participante'], $in['socio'], $in['num_socio'] ?: null, $edad, $ambito);

        // Regla 2+1 contra lo YA inscrito (evita saltarse el máximo con varios envíos).
        $ex = $pdo->prepare("SELECT d.id, d.tipo FROM inscripcion i JOIN disciplina d ON d.id=i.disciplina_id
                             WHERE i.participante_id=? AND i.estado<>'anulada'");
        $ex->execute([$partId]);
        $union = $ex->fetchAll();
        $existIds = array_map('intval', array_column($union, 'id'));
        foreach ($discs as $d) {
            if (!in_array((int)$d['id'], $existIds, true)) $union[] = ['id' => $d['id'], 'tipo' => $d['tipo']];
        }
        if ($msg = validar_combinacion(array_column($union, 'tipo'))) {
            $pdo->rollBack();
            signup_individual_form(['Sumando lo que ya tienes inscrito se supera el máximo. ' . $msg]);
            return;
        }

        $precio = price_for($in['socio']);
        $parejaNombre = $in['pareja_nombre'];
        $companeroMain = $parejaNombre !== '' ? $parejaNombre : null;
        $ins = $pdo->prepare('INSERT IGNORE INTO inscripcion(participante_id, disciplina_id, nivel_padel, precio_eur, companero) VALUES(?,?,?,?,?)');
        foreach ($discs as $d) {
            $ins->execute([$partId, (int)$d['id'], (int)$d['pide_nivel'] === 1 ? $nivel : null, $precio, $companeroMain]);
        }
        // Si se indica pareja, se crea también su inscripción (mismas actividades) y quedan emparejados.
        if ($parejaNombre !== '') {
            $parejaEdad = $ambito === 'infantil' ? $edad : null;   // misma categoría; en infantil, edad orientativa (editable)
            $parejaPart = find_or_create_participante($cuentaId, $parejaNombre, $in['pareja_socio'], $in['pareja_num_socio'] ?: null, $parejaEdad, $ambito);
            $precioPareja = price_for($in['pareja_socio']);
            foreach ($discs as $d) {
                $ins->execute([$parejaPart, (int)$d['id'], (int)$d['pide_nivel'] === 1 ? $nivel : null, $precioPareja, $in['participante']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        audit('signup_error', $ex->getMessage());
        signup_individual_form(['No hemos podido registrar la preinscripción. Inténtalo de nuevo.']);
        return;
    }
    audit('signup_ok', 'email=' . $in['email']);
    $lineas = [];
    foreach ($discs as $d) {
        $lineas[] = $in['participante'] . ' · ' . $d['nombre'] . ' (' . number_format($precio, 2, ',', '.') . ' €)';
    }
    $total = $precio * count($discs);
    if ($in['pareja_nombre'] !== '') {
        $precioPareja = price_for($in['pareja_socio']);
        foreach ($discs as $d) {
            $lineas[] = $in['pareja_nombre'] . ' · ' . $d['nombre'] . ' (' . number_format($precioPareja, 2, ',', '.') . ' €)';
        }
        $total += $precioPareja * count($discs);
    }
    email_preinscripcion($in['email'], $in['nombre_adulto'], $lineas, $total);
    signup_success();
}

function find_or_create_cuenta(string $email, string $nombre, string $tel): int {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM cuenta WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    $consent = [1, (new DateTime())->format('Y-m-d H:i:s'), client_ip(), RGPD_VERSION];
    if ($row) {
        // Cuenta ya existente: NO sobrescribir el consentimiento previo de un tercero
        // (el email no está verificado). Se conserva el registro de consentimiento original.
        return (int)$row['id'];
    }
    $pdo->prepare('INSERT INTO cuenta(email, nombre_completo, telefono, rgpd_consent, rgpd_consent_at, rgpd_consent_ip, rgpd_version) VALUES(?,?,?,?,?,?,?)')
        ->execute([$email, $nombre, $tel, ...$consent]);
    return (int)$pdo->lastInsertId();
}

function find_or_create_participante(int $cuentaId, string $nombre, bool $socio, ?string $numSocio, ?int $edad, string $ambito): int {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM participante WHERE cuenta_id=? AND nombre_completo=? AND ambito=?');
    $st->execute([$cuentaId, $nombre, $ambito]);
    if ($row = $st->fetch()) {
        $pdo->prepare('UPDATE participante SET es_socio=?, num_socio=?, edad=? WHERE id=?')
            ->execute([$socio ? 1 : 0, $numSocio, $edad, $row['id']]);
        return (int)$row['id'];
    }
    $pdo->prepare('INSERT INTO participante(cuenta_id, nombre_completo, es_socio, num_socio, edad, ambito) VALUES(?,?,?,?,?,?)')
        ->execute([$cuentaId, $nombre, $socio ? 1 : 0, $numSocio, $edad, $ambito]);
    return (int)$pdo->lastInsertId();
}

function signup_success(): void {
    $pago = PAGO_INFO;
    $aviso = AVISO_NO_PAGO;
    $c = <<<HTML
<section class="success">
  <h1>¡Preinscripción recibida!</h1>
  <p>Hemos anotado la preinscripción. <strong>Recuerda que no tienes plaza hasta pagar.</strong></p>
  <p class="pago">💳 $pago</p>
  <p class="aviso">⚠️ $aviso</p>
  <p>Cuando el club confirme el pago, recibirás un correo con tu acceso para consultar
     inscripciones, emparejamientos y horarios.</p>
  <p>👥 ¿Juegas <strong>dobles</strong> o quieres apuntar a <strong>otra persona</strong>
     (tu pareja, tu familia)? Puedes hacerlo ahora, con el mismo email:</p>
  <p><a class="primary" href="/preinscripcion">Apuntar a otra persona</a>
     &nbsp; <a href="/">Volver al inicio</a></p>
</section>
HTML;
    render('Preinscripción recibida', $c, current_user(), 'noindex, nofollow');
}
