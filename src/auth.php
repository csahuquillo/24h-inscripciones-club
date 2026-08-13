<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

const PWD_ALGO = PASSWORD_ARGON2ID;
const LOGIN_MAX_FAILS = 5;
const LOGIN_BLOCK_MIN = 15;

function hash_password(string $plain): string {
    return password_hash($plain, PWD_ALGO);
}

function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    $st = db()->prepare('SELECT id, email, nombre_completo, rol, activated_at FROM cuenta WHERE id = ?');
    $st->execute([(int)$_SESSION['uid']]);
    return $cache = ($st->fetch() ?: null);
}

function login_session(int $cuentaId): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $cuentaId;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('/login');
    return $u;
}
function require_staff(): array {
    $u = require_login();
    if (!in_array($u['rol'], ['staff', 'admin'], true)) { deny_403(); }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['rol'] !== 'admin') { deny_403(); }
    return $u;
}
function deny_403(): never {
    http_response_code(403);
    security_headers();
    header('Content-Type: text/plain; charset=utf-8');
    exit('No autorizado');
}

/**
 * Devuelve la cuenta si las credenciales son válidas; null si no.
 * El anti-fuerza-bruta se hace por IP en el controlador (rate_limit). Aquí NO se bloquea la
 * cuenta entera (evita que un tercero deje sin acceso a un usuario probando contraseñas).
 */
function attempt_login(string $email, string $password): ?array {
    $pdo = db();
    $id = mb_strtolower(trim($email));
    // Se puede acceder por email o por un nombre de usuario (si la cuenta tiene uno asignado).
    $st = $pdo->prepare('SELECT * FROM cuenta WHERE email = ? OR username = ? LIMIT 1');
    $st->execute([$id, $id]);
    $u = $st->fetch();

    // Verificar SIEMPRE contra un hash (aunque el usuario no exista) para no filtrar por temporización.
    static $dummyHash = null;
    if ($dummyHash === null) $dummyHash = password_hash('cuenta-inexistente', PWD_ALGO);
    $hash = ($u && $u['password_hash'] !== null) ? $u['password_hash'] : $dummyHash;
    $ok = password_verify($password, $hash);

    if (!$u || $u['password_hash'] === null || !$ok) {
        if ($u) {
            $pdo->prepare('UPDATE cuenta SET intentos_login = intentos_login + 1 WHERE id = ?')->execute([$u['id']]);
            audit('login_fail', 'cuenta=' . $u['id']);
        }
        return null;
    }
    // éxito: reset contador y re-hash si procede
    if (password_needs_rehash($u['password_hash'], PWD_ALGO)) {
        $pdo->prepare('UPDATE cuenta SET password_hash = ? WHERE id = ?')->execute([hash_password($password), $u['id']]);
    }
    $pdo->prepare('UPDATE cuenta SET intentos_login = 0 WHERE id = ?')->execute([$u['id']]);
    audit('login_ok', 'cuenta=' . $u['id']);
    return $u;
}
