<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name('POLI24H');
    session_start();
    if (empty($_SESSION['_init'])) {
        $_SESSION['_init'] = 1;
        session_regenerate_id(true);
    }
}

function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "style-src 'self'; script-src 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header_remove('X-Powered-By');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        security_headers();
        header('Content-Type: text/plain; charset=utf-8');
        exit('Sesión caducada. Recarga la página e inténtalo de nuevo.');
    }
}

function client_ip(): string {
    // El origen recibe la conexión DIRECTA (sin proxy Cloudflare delante): la única IP fiable
    // es REMOTE_ADDR. No confiar en cabeceras tipo CF-Connecting-IP / X-Forwarded-For, que un
    // cliente puede falsificar sin un proxy de confianza (bypass de rate-limit, IP de consentimiento falsa).
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Devuelve true si aún hay cuota; false si se ha superado el límite. */
function rate_limit(string $key, int $max, int $window): bool {
    $pdo = db();
    $bucket = hash('sha256', $key . '|' . client_ip());
    $now = time();
    $pdo->prepare('DELETE FROM rate_limit WHERE reset_at < ?')->execute([$now]);
    $st = $pdo->prepare('SELECT hits FROM rate_limit WHERE bucket = ?');
    $st->execute([$bucket]);
    $row = $st->fetch();
    if (!$row) {
        $pdo->prepare('INSERT INTO rate_limit(bucket, hits, reset_at) VALUES(?,1,?)')
            ->execute([$bucket, $now + $window]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    $pdo->prepare('UPDATE rate_limit SET hits = hits + 1 WHERE bucket = ?')->execute([$bucket]);
    return true;
}

function audit(string $accion, string $detalle = ''): void {
    try {
        db()->prepare('INSERT INTO audit_log(accion, detalle, ip, creado_at) VALUES(?,?,?,NOW())')
            ->execute([$accion, mb_substr($detalle, 0, 500), client_ip()]);
    } catch (Throwable $ex) { /* no romper el flujo por el log */ }
}

function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash_set(string $m): void { $_SESSION['_flash'] = $m; }
function flash_get(): ?string { $m = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $m; }
