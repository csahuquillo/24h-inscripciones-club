<?php
declare(strict_types=1);

/** Carga /etc/poli24h/env (KEY=VALUE) a $_ENV una sola vez. */
function env_load(string $path): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    if (!is_readable($path)) {
        http_response_code(500);
        exit('Configuración no disponible');
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

env_load('/etc/poli24h/env');

// Nunca mostrar errores al cliente (defensa en profundidad, no depender del php.ini).
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function (Throwable $e): void {
    error_log('[poli24h] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo 'Error interno. Inténtalo de nuevo más tarde.';
});

function cfg(string $key, ?string $default = null): string {
    return $_ENV[$key] ?? $default ?? '';
}

function is_prod(): bool { return cfg('APP_ENV', 'prod') === 'prod'; }
