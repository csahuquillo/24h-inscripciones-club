<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notify.php';

$email = $argv[1] ?? null;
$nombre = $argv[2] ?? 'Administrador';
if (!$email) { fwrite(STDERR, "uso: create_admin.php email [nombre]\n"); exit(1); }
$email = mb_strtolower(trim($email));

$pdo = db();
$st = $pdo->prepare('SELECT id FROM cuenta WHERE email = ?');
$st->execute([$email]);
$pass = gen_password(12);
if ($row = $st->fetch()) {
    $pdo->prepare('UPDATE cuenta SET rol="admin", password_hash=?, activated_at=NOW() WHERE id=?')
        ->execute([hash_password($pass), $row['id']]);
} else {
    $pdo->prepare('INSERT INTO cuenta(email, nombre_completo, telefono, rol, password_hash, activated_at) VALUES(?,?,"","admin",?,NOW())')
        ->execute([$email, $nombre, hash_password($pass)]);
}
echo "ADMIN_EMAIL=$email\nADMIN_PASS=$pass\n";
