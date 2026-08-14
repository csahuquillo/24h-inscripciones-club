<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/security.php';
require_once $root . '/src/auth.php';
require_once $root . '/src/domain.php';
require_once $root . '/src/layout.php';
require_once $root . '/src/ses_mailer.php';
require_once $root . '/src/notify.php';
foreach (glob($root . '/src/controllers/*.php') as $f) require_once $f;

secure_session();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    'GET /'                          => 'ctrl_home',
    'GET /robots.txt'                => 'ctrl_robots',
    'GET /sitemap.xml'               => 'ctrl_sitemap',
    'GET /privacidad'                => 'ctrl_privacy',
    'GET /preinscripcion'            => 'ctrl_signup_individual',
    'POST /preinscripcion'           => 'ctrl_signup_individual',
    'GET /preinscripcion-futbol'     => 'ctrl_signup_football',
    'POST /preinscripcion-futbol'    => 'ctrl_signup_football',
    'GET /login'                     => 'ctrl_login',
    'POST /login'                    => 'ctrl_login',
    'POST /logout'                   => 'ctrl_logout',
    'GET /mi-cuenta'                 => 'ctrl_account',
    'GET /mi-cuenta/password'        => 'ctrl_password',
    'POST /mi-cuenta/password'       => 'ctrl_password',
    'GET /admin'                     => 'ctrl_admin',
    'POST /admin'                    => 'ctrl_admin',
    'GET /admin/editar'              => 'ctrl_admin_edit',
    'GET /admin/nueva'               => 'ctrl_admin_new',
];

$key = "$method $path";
if (isset($routes[$key])) { $routes[$key](); exit; }

http_response_code(404);
render('No encontrado', '<h1>404</h1><p>Página no encontrada.</p>', current_user());
