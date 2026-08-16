<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ses_mailer.php';
require_once __DIR__ . '/domain.php';

function gen_password(int $len = 10): string {
    $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789'; // sin caracteres ambiguos
    $max = strlen($alphabet) - 1;
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alphabet[random_int(0, $max)];
    return $s;
}

function tpl_wrap(string $inner): string {
    $club = e(cfg('CLUB_NOMBRE', 'Tu Club Deportivo'));
    $ev   = e(cfg('EVENTO_NOMBRE', '24 Horas'));
    $mail = e(cfg('CLUB_EMAIL', 'contacto@ejemplo.org'));
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222">'
        . '<h2 style="color:#b45309">' . $ev . ' · ' . $club . '</h2>' . $inner
        . '<hr><p style="font-size:12px;color:#777">Este mensaje se envía para gestionar tu participación en el evento. '
        . 'Responsable: ' . $club . '. Puedes ejercer tus derechos escribiendo a ' . $mail . '.</p></div>';
}

function tpl_credentials(string $nombre, string $email, string $pass): array {
    $base = cfg('BASE_URL');
    $html = tpl_wrap(
        '<p>Hola ' . e($nombre) . ',</p>'
        . '<p>El club ha confirmado tu pago. Ya puedes acceder para ver tus inscripciones, '
        . 'los emparejamientos y los horarios.</p>'
        . '<p><strong>Acceso:</strong> <a href="' . e($base) . '/login">' . e($base) . '/login</a><br>'
        . '<strong>Usuario:</strong> ' . e($email) . '<br>'
        . '<strong>Contraseña:</strong> <code>' . e($pass) . '</code></p>'
        . '<p>Te recomendamos guardarla. Si necesitas ayuda, contacta con el club.</p>');
    $text = "Hola $nombre,\n\nEl club ha confirmado tu pago. Accede en $base/login\n"
        . "Usuario: $email\nContraseña: $pass\n\n" . cfg('CLUB_NOMBRE', 'Tu Club Deportivo');
    return [$html, $text];
}

function tpl_payment_confirm(string $nombre): array {
    $base = cfg('BASE_URL');
    $html = tpl_wrap('<p>Hola ' . e($nombre) . ',</p><p>Confirmamos un nuevo pago registrado en tu cuenta. '
        . 'Puedes revisar tus inscripciones en <a href="' . e($base) . '/mi-cuenta">' . e($base) . '/mi-cuenta</a>.</p>');
    $text = "Hola $nombre,\n\nConfirmamos un nuevo pago en tu cuenta. Revisa en $base/mi-cuenta\n\n" . cfg('CLUB_NOMBRE', 'Tu Club Deportivo');
    return [$html, $text];
}

function tpl_preinscripcion(string $nombre, array $lineas, float $total, string $nota = ''): array {
    $base = cfg('BASE_URL');
    $ev   = e(cfg('EVENTO_NOMBRE', '24 Horas'));
    $items = '';
    foreach ($lineas as $l) $items .= '<li>' . e($l) . '</li>';
    $totalTxt = number_format($total, 2, ',', '.');
    $notaHtml = $nota !== '' ? '<p>' . e($nota) . '</p>' : '';
    $html = tpl_wrap(
        '<p>Hola ' . e($nombre) . ',</p>'
        . '<p>Hemos recibido tu <strong>preinscripción</strong> para ' . $ev . '. Queda <strong>pendiente de pago</strong>:</p>'
        . '<ul>' . $items . '</ul>'
        . '<p><strong>Total a pagar: ' . $totalTxt . ' €</strong></p>'
        . $notaHtml
        . '<p>' . PAGO_INFO . '</p>'
        . '<p><strong>La preinscripción no da plaza hasta que se abona.</strong> Cuando el club confirme tu pago '
        . 'recibirás por email tu <strong>usuario y contraseña</strong> para entrar en '
        . '<a href="' . e($base) . '/login">' . e($base) . '</a> y ver tus inscripciones, emparejamientos y horarios.</p>');
    $notaText = $nota !== '' ? $nota . "\n\n" : '';
    $text = "Hola $nombre,\n\nHemos recibido tu preinscripción para " . cfg('EVENTO_NOMBRE', '24 Horas')
        . " (pendiente de pago):\n" . implode("\n", array_map(fn($l) => " - $l", $lineas)) . "\n"
        . "Total a pagar: $totalTxt EUR\n\n" . $notaText . strip_tags(PAGO_INFO) . "\n\n"
        . "La preinscripcion no da plaza hasta que se abona. Cuando el club confirme el pago recibiras tu "
        . "usuario y contrasena para entrar en $base/login\n\n" . cfg('CLUB_NOMBRE', 'Tu Club Deportivo');
    return [$html, $text];
}

/** Envía el email de "preinscripción recibida (pendiente de pago)". Best-effort. */
function email_preinscripcion(string $toEmail, string $nombre, array $lineas, float $total, string $nota = ''): void {
    [$h, $t] = tpl_preinscripcion($nombre, $lineas, $total, $nota);
    try {
        ses_send($toEmail, 'Preinscripción recibida (pendiente de pago) · ' . cfg('EVENTO_NOMBRE', '24 Horas'), $h, $t);
        audit('preins_email', $toEmail);
    } catch (Throwable $ex) {
        audit('preins_email_fail', $ex->getMessage());
    }
}

/** Al confirmar un pago: si la cuenta no tiene acceso, lo crea y envía credenciales; si ya lo tiene, confirma. */
function activate_or_confirm(int $cuentaId): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM cuenta WHERE id = ?');
    $st->execute([$cuentaId]);
    $u = $st->fetch();
    if (!$u) return;

    if ($u['password_hash'] === null) {
        $pass = gen_password();
        $pdo->prepare('UPDATE cuenta SET password_hash = ?, activated_at = NOW() WHERE id = ?')
            ->execute([hash_password($pass), $cuentaId]);
        [$h, $t] = tpl_credentials($u['nombre_completo'], $u['email'], $pass);
        try { ses_send($u['email'], 'Tu acceso · ' . cfg('EVENTO_NOMBRE', '24 Horas'), $h, $t); audit('cred_email', 'cuenta=' . $cuentaId); }
        catch (Throwable $ex) { audit('cred_email_fail', $ex->getMessage()); }
    } else {
        [$h, $t] = tpl_payment_confirm($u['nombre_completo']);
        try { ses_send($u['email'], 'Pago confirmado · ' . cfg('EVENTO_NOMBRE', '24 Horas'), $h, $t); }
        catch (Throwable $ex) { audit('confirm_email_fail', $ex->getMessage()); }
    }
}

/** Encola una notificación para envío asíncrono (bin/send_notifications.php). */
function enqueue_notification(string $asunto, string $cuerpo, string $target, array $emails, ?int $staffId): int {
    $pdo = db();
    $pdo->prepare('INSERT INTO notificacion(asunto, cuerpo, target, creada_por) VALUES(?,?,?,?)')
        ->execute([$asunto, $cuerpo, $target, $staffId]);
    $nid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO notificacion_dest(notificacion_id, email) VALUES(?,?)');
    $seen = [];
    foreach ($emails as $em) {
        $em = mb_strtolower(trim($em));
        if ($em === '' || isset($seen[$em]) || !filter_var($em, FILTER_VALIDATE_EMAIL)) continue;
        $seen[$em] = true;
        $ins->execute([$nid, $em]);
    }
    return count($seen);
}
