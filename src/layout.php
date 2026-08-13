<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

function layout(string $title, string $content, ?array $user = null, string $robots = 'index, follow'): string {
    $clubName = e(cfg('CLUB_NOMBRE', 'Tu Club Deportivo'));
    $eventName = e(cfg('EVENTO_NOMBRE', '24 Horas'));
    $desc = e('Preinscripción online de ' . cfg('EVENTO_NOMBRE', '24 Horas') . ' · ' . cfg('CLUB_NOMBRE', 'club deportivo')
        . '. Deportes, actividades sociales y fútbol infantil.');
    $nav = '<a href="/">Inicio</a> ';
    if ($user) {
        if (in_array($user['rol'], ['staff', 'admin'], true)) $nav .= '<a href="/admin">Panel</a> ';
        $nav .= '<a href="/mi-cuenta">Mi cuenta</a> ';
        $nav .= '<form method="post" action="/logout" class="inline">' . csrf_field()
              . '<button class="linkbtn">Salir</button></form>';
    } else {
        $nav .= '<a href="/login">Acceder</a>';
    }
    $flash = flash_get();
    $flashHtml = $flash ? '<div class="flash">' . e($flash) . '</div>' : '';
    $t = e($title);

    // Logo del club: si existe /assets/logo.* se usa; si no, wordmark tipográfico.
    $logoGlob = glob(__DIR__ . '/../public/assets/logo.{png,jpg,jpeg,svg,webp}', GLOB_BRACE) ?: [];
    $brand = $logoGlob
        ? '<img src="/assets/' . rawurlencode(basename($logoGlob[0])) . '?v=' . filemtime($logoGlob[0]) . '" alt="' . $clubName . '" class="logo">'
        : '<span class="wordmark">' . $clubName . '</span>';
    $cssv = @filemtime(__DIR__ . '/../public/assets/style.css') ?: 1;

    // Patrocinadores: se pintan solos los ficheros que haya en /assets/sponsors/
    $sponsorFiles = glob(__DIR__ . '/../public/assets/sponsors/*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];
    $sponsorsHtml = '';
    if ($sponsorFiles) {
        $imgs = '';
        foreach ($sponsorFiles as $s) {
            $imgs .= '<img src="/assets/sponsors/' . rawurlencode(basename($s)) . '" alt="Patrocinador" class="sponsor">';
        }
        $sponsorsHtml = '<div class="sponsors"><span>Con la colaboración de</span><div class="sponsor-strip">' . $imgs . '</div></div>';
    }
    return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="$robots">
<title>$t · $eventName</title>
<meta name="description" content="$desc">
<link rel="stylesheet" href="/assets/style.css?v=$cssv">
</head>
<body>
<header class="topbar">
  <div class="brand"><a href="/">$brand</a><span class="event">$eventName</span></div>
  <nav class="mainnav">$nav</nav>
</header>
<main class="wrap">
$flashHtml
$content
</main>
$sponsorsHtml
<footer class="foot">
  <a href="/privacidad">Política de privacidad</a> ·
  Responsable: $clubName · Datos tratados según el RGPD.
</footer>
</body>
</html>
HTML;
}

function render(string $title, string $content, ?array $user = null, string $robots = 'index, follow'): never {
    security_headers();
    echo layout($title, $content, $user, $robots);
    exit;
}
