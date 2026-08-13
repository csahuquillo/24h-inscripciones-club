<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Procesa la cola de notificaciones. Pensado para cron cada minuto.
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/ses_mailer.php';
require_once __DIR__ . '/../src/notify.php';

$pdo = db();
$rows = $pdo->query('SELECT nd.id, nd.email, n.asunto, n.cuerpo
    FROM notificacion_dest nd JOIN notificacion n ON n.id = nd.notificacion_id
    WHERE nd.estado = "pendiente" ORDER BY nd.id LIMIT 300')->fetchAll();

$ok = 0; $ko = 0;
foreach ($rows as $r) {
    $html = tpl_wrap('<p>' . nl2br(htmlspecialchars($r['cuerpo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>');
    try {
        $mid = ses_send($r['email'], $r['asunto'], $html, $r['cuerpo']);
        $pdo->prepare('UPDATE notificacion_dest SET estado="enviado", message_id=?, enviado_at=NOW(), intentos=intentos+1 WHERE id=?')
            ->execute([$mid, $r['id']]);
        $ok++;
    } catch (Throwable $ex) {
        $pdo->prepare('UPDATE notificacion_dest SET estado=IF(intentos>=3,"error","pendiente"), intentos=intentos+1 WHERE id=?')
            ->execute([$r['id']]);
        $ko++;
    }
    usleep(90000); // ~11/s, por debajo del límite SES (14/s)
}
echo "enviados=$ok errores=$ko\n";
