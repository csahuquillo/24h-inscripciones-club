<?php
declare(strict_types=1);

function ctrl_robots(): void {
    header('Content-Type: text/plain; charset=utf-8');
    $base = rtrim(cfg('BASE_URL', ''), '/');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /admin\n";
    echo "Disallow: /mi-cuenta\n";
    echo "Disallow: /login\n";
    if ($base !== '') echo "Sitemap: $base/sitemap.xml\n";
    exit;
}

function ctrl_sitemap(): void {
    header('Content-Type: application/xml; charset=utf-8');
    $base = rtrim(cfg('BASE_URL', ''), '/');
    $paths = ['/', '/preinscripcion', '/preinscripcion-futbol', '/privacidad'];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($paths as $p) {
        echo '  <url><loc>' . e($base . $p) . '</loc></url>' . "\n";
    }
    echo '</urlset>' . "\n";
    exit;
}
