<?php
declare(strict_types=1);
require_once __DIR__ . '/../notify.php';

function ctrl_admin(): void {
    $u = require_staff();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { admin_post($u); return; }
    admin_dashboard($u);
}

function admin_post(array $u): void {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $pdo = db();
    try {
        switch ($do) {
            case 'pay_ins':
                $id = (int)($_POST['id'] ?? 0);
                $metodo = ($_POST['metodo'] ?? 'tpv') === 'efectivo' ? 'efectivo' : 'tpv';
                $st = $pdo->prepare('SELECT i.*, p.cuenta_id FROM inscripcion i JOIN participante p ON p.id=i.participante_id WHERE i.id=?');
                $st->execute([$id]); $ins = $st->fetch();
                if ($ins) {
                    $upd = $pdo->prepare('UPDATE inscripcion SET estado="pagada" WHERE id=? AND estado="preinscrita"');
                    $upd->execute([$id]);
                    if ($upd->rowCount() === 1) {  // solo si esta petición hizo el cambio (no doble pago)
                        $pdo->prepare('INSERT INTO pago(objeto_tipo,objeto_id,importe_eur,metodo,cobrado_por) VALUES("inscripcion",?,?,?,?)')
                            ->execute([$id, $ins['precio_eur'], $metodo, $u['id']]);
                        activate_or_confirm((int)$ins['cuenta_id']);
                        audit('pay_ins', 'id=' . $id . ' por=' . $u['id']);
                        flash_set('Inscripción marcada como pagada y aviso enviado.');
                    }
                }
                break;

            case 'pay_eq':
                $id = (int)($_POST['id'] ?? 0);
                $metodo = ($_POST['metodo'] ?? 'tpv') === 'efectivo' ? 'efectivo' : 'tpv';
                $st = $pdo->prepare('SELECT * FROM equipo WHERE id=?'); $st->execute([$id]); $eq = $st->fetch();
                if ($eq) {
                    $upd = $pdo->prepare('UPDATE equipo SET estado="pagado" WHERE id=? AND estado="preinscrito"');
                    $upd->execute([$id]);
                    if ($upd->rowCount() === 1) {
                        $pdo->prepare('INSERT INTO pago(objeto_tipo,objeto_id,importe_eur,metodo,cobrado_por) VALUES("equipo",?,?,?,?)')
                            ->execute([$id, $eq['precio_total_eur'], $metodo, $u['id']]);
                        activate_or_confirm((int)$eq['cuenta_id']);
                        audit('pay_eq', 'id=' . $id . ' por=' . $u['id']);
                        flash_set('Equipo marcado como pagado y aviso enviado.');
                    }
                }
                break;

            case 'seed':
                $id = (int)($_POST['id'] ?? 0);
                $val = ($_POST['val'] ?? '0') === '1' ? 1 : 0;
                $pdo->prepare('UPDATE inscripcion SET cabeza_serie=? WHERE id=?')->execute([$val, $id]);
                flash_set('Cabeza de serie actualizada.');
                break;

            case 'annul_ins':
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE inscripcion SET estado="anulada" WHERE id=? AND estado="preinscrita"')->execute([$id]);
                audit('annul_ins', 'id=' . $id . ' por=' . $u['id']);
                flash_set('Inscripción anulada.');
                break;

            case 'annul_eq':
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE equipo SET estado="anulado" WHERE id=? AND estado="preinscrito"')->execute([$id]);
                audit('annul_eq', 'id=' . $id . ' por=' . $u['id']);
                flash_set('Equipo anulado.');
                break;

            case 'edit_ins':
                edit_ins_guardar($u);
                break;

            case 'notify':
                $asunto = trim((string)($_POST['asunto'] ?? ''));
                $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
                $target = (string)($_POST['target'] ?? 'all');
                if ($asunto === '' || $cuerpo === '') { flash_set('Asunto y mensaje son obligatorios.'); break; }
                $emails = recipients_for($target);
                $n = enqueue_notification($asunto, $cuerpo, $target, $emails, (int)$u['id']);
                audit('notify_enqueue', "target=$target n=$n");
                flash_set("Notificación encolada para $n destinatarios (se envían en segundo plano).");
                break;

            case 'grp_create':
                $disc = (int)($_POST['disciplina_id'] ?? 0);
                $etq = trim((string)($_POST['etiqueta'] ?? ''));
                if ($disc && $etq !== '') {
                    $pdo->prepare('INSERT INTO grupo(disciplina_id,etiqueta) VALUES(?,?)')->execute([$disc, mb_substr($etq, 0, 10)]);
                    flash_set('Grupo creado.');
                }
                break;

            case 'grp_assign':
                $gid = (int)($_POST['grupo_id'] ?? 0);
                $iid = (int)($_POST['inscripcion_id'] ?? 0);
                if ($gid && $iid) {
                    $pdo->prepare('INSERT INTO grupo_miembro(grupo_id,inscripcion_id) VALUES(?,?)')->execute([$gid, $iid]);
                    flash_set('Participante añadido al grupo.');
                }
                break;

            case 'create_staff':
                if ($u['rol'] !== 'admin') { http_response_code(403); exit('Solo admin'); }
                $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $rol = in_array($_POST['rol'] ?? '', ['staff', 'admin'], true) ? $_POST['rol'] : 'staff';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nombre === '') { flash_set('Datos de staff inválidos.'); break; }
                create_staff_account($email, $nombre, $rol);
                flash_set("Cuenta de $rol creada y credenciales enviadas a $email.");
                break;
        }
    } catch (Throwable $ex) {
        audit('admin_error', $do . ':' . $ex->getMessage());
        flash_set('Error al procesar la acción.');
    }
    redirect('/admin');
}

function recipients_for(string $target): array {
    $pdo = db();
    if ($target === 'all') {
        return array_column($pdo->query('SELECT DISTINCT email FROM cuenta')->fetchAll(), 'email');
    }
    if ($target === 'activos') {
        return array_column($pdo->query('SELECT email FROM cuenta WHERE activated_at IS NOT NULL')->fetchAll(), 'email');
    }
    if ($target === 'impagados') {
        // Cuentas con alguna preinscripción o equipo SIN pagar (recordatorio de pago).
        return array_column($pdo->query("
            SELECT DISTINCT c.email FROM cuenta c
            WHERE EXISTS (SELECT 1 FROM participante p JOIN inscripcion i ON i.participante_id=p.id
                          WHERE p.cuenta_id=c.id AND i.estado='preinscrita')
               OR EXISTS (SELECT 1 FROM equipo e WHERE e.cuenta_id=c.id AND e.estado='preinscrito')
        ")->fetchAll(), 'email');
    }
    if (str_starts_with($target, 'disc:')) {
        $id = (int)substr($target, 5);
        $st = $pdo->prepare('SELECT DISTINCT c.email FROM cuenta c
            JOIN participante p ON p.cuenta_id=c.id JOIN inscripcion i ON i.participante_id=p.id
            WHERE i.disciplina_id=?');
        $st->execute([$id]);
        return array_column($st->fetchAll(), 'email');
    }
    return [];
}

function create_staff_account(string $email, string $nombre, string $rol): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM cuenta WHERE email=?'); $st->execute([$email]);
    if ($row = $st->fetch()) {
        $pdo->prepare('UPDATE cuenta SET rol=? WHERE id=?')->execute([$rol, $row['id']]);
        $cid = (int)$row['id'];
    } else {
        $pdo->prepare('INSERT INTO cuenta(email,nombre_completo,telefono,rol) VALUES(?,?,"",?)')->execute([$email, $nombre, $rol]);
        $cid = (int)$pdo->lastInsertId();
    }
    $pass = gen_password();
    $pdo->prepare('UPDATE cuenta SET password_hash=?, activated_at=NOW() WHERE id=?')->execute([hash_password($pass), $cid]);
    [$h, $t] = tpl_credentials($nombre, $email, $pass);
    try { ses_send($email, 'Acceso de gestión · ' . cfg('EVENTO_NOMBRE', '24 Horas'), $h, $t); } catch (Throwable $ex) { audit('staff_email_fail', $ex->getMessage()); }
    audit('create_staff', "email=$email rol=$rol");
}

function admin_dashboard(array $u): void {
    $pdo = db();
    $csrf = csrf_field();
    $resumen = admin_resumen($pdo);

    // Padrón para cruce socio (por nº y por nombre normalizado)
    $padronNum = []; $padronNorm = [];
    foreach ($pdo->query('SELECT num_nuevo, norm_nombre FROM socio_padron')->fetchAll() as $s) {
        if ($s['num_nuevo'] !== null) $padronNum[(string)$s['num_nuevo']] = true;
        if ($s['norm_nombre']) $padronNorm[$s['norm_nombre']] = true;
    }

    // Pagos pendientes: inscripciones
    $ins = $pdo->query('SELECT i.id, i.precio_eur, i.cabeza_serie, i.companero, d.nombre AS disc, d.tipo, p.nombre_completo AS part,
        p.es_socio, p.num_socio, c.nombre_completo AS titular, c.email FROM inscripcion i
        JOIN participante p ON p.id=i.participante_id JOIN cuenta c ON c.id=p.cuenta_id
        JOIN disciplina d ON d.id=i.disciplina_id WHERE i.estado="preinscrita" ORDER BY c.email, d.nombre')->fetchAll();
    $insRows = '';
    foreach ($ins as $r) {
        // Indicador socio declarado vs padrón
        if ((int)$r['es_socio'] === 1) {
            $match = ($r['num_socio'] !== null && $r['num_socio'] !== '' && isset($padronNum[(string)$r['num_socio']]))
                  || isset($padronNorm[norm_nombre($r['part'])]);
            $socioTag = $match
                ? '<span class="tag ok">socio ✓</span>'
                : '<span class="tag err">socio ⚠ sin match</span>';
        } else {
            $socioTag = '<span class="tag">no socio</span>';
        }
        $esSemilla = preg_match('/^(Tenis|Pádel)/u', $r['disc']) === 1;
        $seedBtn = '';
        if ($esSemilla) {
            $next = (int)$r['cabeza_serie'] === 1 ? '0' : '1';
            $lbl = (int)$r['cabeza_serie'] === 1 ? 'Quitar cabeza' : 'Cabeza de serie';
            $seedBtn = '<form method="post" class="inline">' . $csrf . '<input type="hidden" name="do" value="seed">'
                . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="val" value="' . $next . '">'
                . '<button class="linkbtn">' . $lbl . '</button></form>';
        }
        $insRows .= '<tr><td>' . e($r['part']) . ' ' . $socioTag . '<br><span class="muted">' . e($r['titular']) . ' · ' . e($r['email']) . '</span></td>'
            . '<td>' . e($r['disc']) . ((int)$r['cabeza_serie'] === 1 ? ' <span class="tag seed">CS</span>' : '')
            . ($r['companero'] ? '<br><span class="muted">con ' . e($r['companero']) . '</span>' : '') . '</td>'
            . '<td>' . number_format((float)$r['precio_eur'], 2, ',', '.') . ' €</td>'
            . '<td><form method="post" class="inline">' . $csrf . '<input type="hidden" name="do" value="pay_ins">'
            . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
            . '<select name="metodo"><option value="tpv">TPV</option><option value="efectivo">Efectivo</option></select> '
            . '<button class="primary sm">Marcar pagado</button></form> ' . $seedBtn
            . ' <a class="linkbtn" href="/admin/editar?ins=' . (int)$r['id'] . '">Editar</a>'
            . ' <form method="post" class="inline">' . $csrf . '<input type="hidden" name="do" value="annul_ins">'
            . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="linkbtn danger">Anular</button></form></td></tr>';
    }
    $insTable = $insRows
        ? '<table class="grid"><thead><tr><th>Participante</th><th>Actividad</th><th>Precio</th><th>Acción</th></tr></thead><tbody>' . $insRows . '</tbody></table>'
        : '<p class="muted">No hay inscripciones pendientes.</p>';

    // Equipos pendientes
    $eqs = $pdo->query('SELECT e.id, e.nombre_equipo, e.precio_total_eur, c.nombre_completo AS titular, c.email
        FROM equipo e JOIN cuenta c ON c.id=e.cuenta_id WHERE e.estado="preinscrito" ORDER BY e.nombre_equipo')->fetchAll();
    $eqRows = '';
    foreach ($eqs as $r) {
        $eqRows .= '<tr><td>' . e($r['nombre_equipo']) . '<br><span class="muted">' . e($r['titular']) . ' · ' . e($r['email']) . '</span></td>'
            . '<td>' . number_format((float)$r['precio_total_eur'], 2, ',', '.') . ' €</td>'
            . '<td><form method="post" class="inline">' . $csrf . '<input type="hidden" name="do" value="pay_eq">'
            . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
            . '<select name="metodo"><option value="tpv">TPV</option><option value="efectivo">Efectivo</option></select> '
            . '<button class="primary sm">Marcar pagado</button></form>'
            . ' <form method="post" class="inline">' . $csrf . '<input type="hidden" name="do" value="annul_eq">'
            . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="linkbtn danger">Anular</button></form></td></tr>';
    }
    $eqTable = $eqRows
        ? '<table class="grid"><thead><tr><th>Equipo</th><th>Total</th><th>Acción</th></tr></thead><tbody>' . $eqRows . '</tbody></table>'
        : '<p class="muted">No hay equipos pendientes.</p>';

    // Notificaciones — opciones de destino
    $opts = '<option value="impagados">Preinscritos SIN pagar (recordatorio)</option>'
          . '<option value="activos">Solo con acceso (pagados)</option>'
          . '<option value="all">Todos los preinscritos</option>';
    foreach ($pdo->query('SELECT id,nombre,ambito FROM disciplina ORDER BY ambito,nombre')->fetchAll() as $d) {
        $opts .= '<option value="disc:' . (int)$d['id'] . '">' . e($d['nombre']) . ' (' . e($d['ambito']) . ')</option>';
    }

    // Grupos
    $discOpts = '';
    foreach ($pdo->query('SELECT id,nombre,ambito FROM disciplina ORDER BY ambito,nombre')->fetchAll() as $d) {
        $discOpts .= '<option value="' . (int)$d['id'] . '">' . e($d['nombre']) . ' (' . e($d['ambito']) . ')</option>';
    }
    $grupos = $pdo->query('SELECT g.id,g.etiqueta,d.nombre AS disc,COUNT(gm.id) AS n
        FROM grupo g JOIN disciplina d ON d.id=g.disciplina_id
        LEFT JOIN grupo_miembro gm ON gm.grupo_id=g.id GROUP BY g.id ORDER BY d.nombre,g.etiqueta')->fetchAll();
    $grRows = '';
    foreach ($grupos as $g) $grRows .= '<li>' . e($g['disc']) . ' · grupo ' . e($g['etiqueta']) . ' (' . (int)$g['n'] . ')</li>';
    $grList = $grRows ? '<ul>' . $grRows . '</ul>' : '<p class="muted">Aún no hay grupos.</p>';

    // Staff (solo admin)
    $staffSection = '';
    if ($u['rol'] === 'admin') {
        $staff = $pdo->query('SELECT email,nombre_completo,rol FROM cuenta WHERE rol IN ("staff","admin") ORDER BY rol,email')->fetchAll();
        $staffRows = '';
        foreach ($staff as $s) $staffRows .= '<li>' . e($s['nombre_completo']) . ' · ' . e($s['email']) . ' <span class="tag">' . e($s['rol']) . '</span></li>';
        $staffSection = <<<HTML
<section class="panel"><h2>Equipo de gestión (admin)</h2>
<ul>$staffRows</ul>
<form method="post" class="form">$csrf<input type="hidden" name="do" value="create_staff">
  <label>Nombre <input name="nombre" required></label>
  <label>Email <input type="email" name="email" required></label>
  <label>Rol <select name="rol"><option value="staff">staff</option><option value="admin">admin</option></select></label>
  <button class="primary">Crear y enviar credenciales</button>
</form></section>
HTML;
    }

    $c = <<<HTML
<h1>Panel de gestión</h1>
$resumen
<section class="panel"><h2>Pagos pendientes · inscripciones</h2>$insTable</section>
<section class="panel"><h2>Pagos pendientes · equipos de fútbol</h2>$eqTable</section>

<section class="panel"><h2>Enviar notificación</h2>
<form method="post" class="form">$csrf<input type="hidden" name="do" value="notify">
  <label>Destinatarios <select name="target">$opts</select></label>
  <label>Asunto <input name="asunto" required maxlength="160"></label>
  <label>Mensaje <textarea name="cuerpo" rows="5" required></textarea></label>
  <button class="primary">Encolar envío</button>
</form>
<p class="note">Los correos se envían en segundo plano (cola). Útil para emparejamientos y cambios de horario.</p>
</section>

<section class="panel"><h2>Grupos / emparejamientos</h2>
$grList
<form method="post" class="form inline-form">$csrf<input type="hidden" name="do" value="grp_create">
  <select name="disciplina_id">$discOpts</select>
  <input name="etiqueta" placeholder="Etiqueta (A, B…)" maxlength="10" required>
  <button class="primary sm">Crear grupo</button>
</form>
<form method="post" class="form inline-form">$csrf<input type="hidden" name="do" value="grp_assign">
  <input name="grupo_id" type="number" placeholder="ID grupo" required>
  <input name="inscripcion_id" type="number" placeholder="ID inscripción" required>
  <button class="primary sm">Asignar a grupo</button>
</form>
<p class="note">La siembra automática (repartir cabezas de serie de tenis/pádel) se aplicará en el sorteo; de momento marca las cabezas y asigna grupos manualmente.</p>
</section>

$staffSection
HTML;
    render('Panel', $c, $u, 'noindex, nofollow');
}

/** Resumen económico y estadísticas para el panel. */
function admin_resumen(PDO $pdo): string {
    $f = fn($x) => number_format((float)$x, 2, ',', '.');
    $previsto = (float)$pdo->query("SELECT COALESCE(SUM(precio_eur),0) FROM inscripcion WHERE estado<>'anulada'")->fetchColumn()
              + (float)$pdo->query("SELECT COALESCE(SUM(precio_total_eur),0) FROM equipo WHERE estado<>'anulado'")->fetchColumn();
    $cobrado = (float)$pdo->query("SELECT COALESCE(SUM(importe_eur),0) FROM pago")->fetchColumn();
    $tpv = (float)$pdo->query("SELECT COALESCE(SUM(importe_eur),0) FROM pago WHERE metodo='tpv'")->fetchColumn();
    $efe = (float)$pdo->query("SELECT COALESCE(SUM(importe_eur),0) FROM pago WHERE metodo='efectivo'")->fetchColumn();
    $pendiente = $previsto - $cobrado;
    $conv = $previsto > 0 ? round($cobrado / $previsto * 100) : 0;

    $insPre = (int)$pdo->query("SELECT COUNT(*) FROM inscripcion WHERE estado='preinscrita'")->fetchColumn();
    $insPag = (int)$pdo->query("SELECT COUNT(*) FROM inscripcion WHERE estado='pagada'")->fetchColumn();
    $eqPre  = (int)$pdo->query("SELECT COUNT(*) FROM equipo WHERE estado='preinscrito'")->fetchColumn();
    $eqPag  = (int)$pdo->query("SELECT COUNT(*) FROM equipo WHERE estado='pagado'")->fetchColumn();

    $rows = $pdo->query("SELECT d.nombre, d.ambito, COUNT(*) n,
        SUM(i.estado='pagada') pag, COALESCE(SUM(i.precio_eur),0) imp
        FROM inscripcion i JOIN disciplina d ON d.id=i.disciplina_id
        WHERE i.estado<>'anulada' GROUP BY d.id ORDER BY n DESC")->fetchAll();
    $tr = '';
    foreach ($rows as $r) {
        $tr .= '<tr><td>' . e($r['nombre']) . ' <span class="muted">(' . e($r['ambito']) . ')</span></td>'
             . '<td>' . (int)$r['n'] . '</td><td>' . (int)$r['pag'] . '</td><td>' . $f($r['imp']) . ' €</td></tr>';
    }
    $actTable = $tr
        ? '<table class="grid"><thead><tr><th>Actividad</th><th>Inscritos</th><th>Pagados</th><th>Previsto</th></tr></thead><tbody>' . $tr . '</tbody></table>'
        : '<p class="muted">Sin inscripciones todavía.</p>';

    return '<section class="panel stats"><h2>Resumen económico</h2>'
        . '<div class="kpis">'
        . '<div class="kpi"><span class="kn">' . $f($previsto) . ' €</span><span class="kl">Previsto (preinscripciones)</span></div>'
        . '<div class="kpi ok"><span class="kn">' . $f($cobrado) . ' €</span><span class="kl">Cobrado real</span></div>'
        . '<div class="kpi warn"><span class="kn">' . $f($pendiente) . ' €</span><span class="kl">Pendiente de cobro</span></div>'
        . '<div class="kpi"><span class="kn">' . $conv . ' %</span><span class="kl">Conversión</span></div>'
        . '</div>'
        . '<p class="muted">Cobrado: TPV ' . $f($tpv) . ' € · Efectivo ' . $f($efe) . ' €. '
        . 'Inscripciones: ' . $insPag . ' pagadas / ' . ($insPre + $insPag) . ' totales. '
        . 'Equipos: ' . $eqPag . ' pagados / ' . ($eqPre + $eqPag) . ' totales.</p>'
        . '<h3>Por actividad</h3>' . $actTable
        . '</section>';
}

/** Formulario de edición de una inscripción (staff). */
function ctrl_admin_edit(): void {
    $u = require_staff();
    $id = (int)($_GET['ins'] ?? 0);
    $pdo = db();
    $st = $pdo->prepare('SELECT i.*, p.nombre_completo, p.es_socio, p.num_socio, p.ambito, c.email
        FROM inscripcion i JOIN participante p ON p.id=i.participante_id JOIN cuenta c ON c.id=p.cuenta_id WHERE i.id=?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) { render('Editar', '<h1>Editar</h1><p>Inscripción no encontrada.</p><p><a href="/admin">Volver al panel</a></p>', $u, 'noindex, nofollow'); }

    $csrf = csrf_field();
    $opts = '';
    foreach (disciplinas($r['ambito']) as $d) {
        $sel = (int)$d['id'] === (int)$r['disciplina_id'] ? ' selected' : '';
        $opts .= '<option value="' . (int)$d['id'] . '"' . $sel . '>' . e($d['nombre']) . ' (' . e($d['tipo']) . ')</option>';
    }
    $nombre = e($r['nombre_completo']); $email = e($r['email']); $numSocio = e((string)$r['num_socio']);
    $nivel = e((string)($r['nivel_padel'] ?? '')); $comp = e((string)($r['companero'] ?? ''));
    $siSel = (int)$r['es_socio'] === 1 ? ' checked' : '';
    $noSel = (int)$r['es_socio'] === 1 ? '' : ' checked';
    $ambito = e($r['ambito']);
    $c = <<<HTML
<h1>Editar inscripción</h1>
<p class="muted">Categoría: $ambito. Al cambiar "socio" se recalcula el precio de todas las actividades de esta persona.</p>
<form method="post" action="/admin" class="form narrow">
  $csrf
  <input type="hidden" name="do" value="edit_ins">
  <input type="hidden" name="ins_id" value="$id">
  <label>Nombre y apellidos <input name="nombre" value="$nombre" required maxlength="120"></label>
  <label>Email (de la cuenta) <input type="email" name="email" value="$email" required maxlength="190"></label>
  <label>Actividad <select name="disciplina_id">$opts</select></label>
  <div class="radios"><span>¿Socio?</span>
    <label><input type="radio" name="socio" value="1"$siSel> Sí (3 €)</label>
    <label><input type="radio" name="socio" value="0"$noSel> No (5 €)</label>
  </div>
  <label>Nº de socio <input name="num_socio" value="$numSocio" maxlength="10"></label>
  <label>Nivel de pádel (si aplica, 1–4) <input type="number" name="nivel_padel" value="$nivel" min="1" max="4"></label>
  <label>Pareja / compañero (opcional) <input name="companero" value="$comp" maxlength="120"></label>
  <button class="primary" type="submit">Guardar cambios</button>
  &nbsp; <a href="/admin" class="linkbtn">Cancelar</a>
</form>
HTML;
    render('Editar inscripción', $c, $u, 'noindex, nofollow');
}

/** Guarda los cambios de una inscripción. */
function edit_ins_guardar(array $u): void {
    $pdo = db();
    $id = (int)($_POST['ins_id'] ?? 0);
    $st = $pdo->prepare('SELECT i.id, i.participante_id, p.cuenta_id, p.ambito
        FROM inscripcion i JOIN participante p ON p.id=i.participante_id WHERE i.id=?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) { flash_set('Inscripción no encontrada.'); return; }

    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email  = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $disc   = (int)($_POST['disciplina_id'] ?? 0);
    $socio  = ($_POST['socio'] ?? '0') === '1';
    $numSocio = trim((string)($_POST['num_socio'] ?? '')) ?: null;
    $nivelRaw = trim((string)($_POST['nivel_padel'] ?? ''));
    $nivel  = $nivelRaw === '' ? null : (int)$nivelRaw;
    $companero = trim((string)($_POST['companero'] ?? '')) ?: null;

    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash_set('Nombre o email no válidos.'); return; }
    $sd = $pdo->prepare('SELECT id, pide_nivel FROM disciplina WHERE id=? AND ambito=? AND activa=1');
    $sd->execute([$disc, $r['ambito']]);
    $d = $sd->fetch();
    if (!$d) { flash_set('Actividad no válida para esta categoría.'); return; }
    if ((int)$d['pide_nivel'] === 1 && ($nivel < 1 || $nivel > NIVEL_PADEL_MAX)) { flash_set('En pádel indica un nivel 1–' . NIVEL_PADEL_MAX . '.'); return; }
    if ((int)$d['pide_nivel'] === 0) $nivel = null;

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE participante SET nombre_completo=?, es_socio=?, num_socio=? WHERE id=?')
            ->execute([$nombre, $socio ? 1 : 0, $numSocio, $r['participante_id']]);
        $pdo->prepare('UPDATE inscripcion SET disciplina_id=?, nivel_padel=?, companero=? WHERE id=?')->execute([$disc, $nivel, $companero, $id]);
        $pdo->prepare('UPDATE inscripcion SET precio_eur=? WHERE participante_id=? AND estado<>"anulada"')
            ->execute([price_for($socio), $r['participante_id']]);
        $pdo->prepare('UPDATE cuenta SET email=? WHERE id=?')->execute([$email, $r['cuenta_id']]);
        $pdo->commit();
        audit('edit_ins', 'id=' . $id . ' por=' . $u['id']);
        flash_set('Inscripción actualizada.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $m = $ex->getMessage();
        flash_set(str_contains($m, 'uq_email') ? 'Ese email ya está en uso por otra cuenta.'
            : (str_contains($m, 'uq_part_disc') ? 'Esa persona ya está inscrita en esa actividad.'
            : 'No se pudo guardar el cambio.'));
    }
}
