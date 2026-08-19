<?php
declare(strict_types=1);

function ctrl_home(): void {
    $u = current_user();
    $clubn = e(cfg('CLUB_NOMBRE', 'Tu Club Deportivo'));
    $mbPath = __DIR__ . '/../../public/assets/membrete.png';
    $membrete = is_file($mbPath)
        ? '<img src="/assets/membrete.png?v=' . filemtime($mbPath) . '" alt="' . $clubn . '" class="membrete">' : '';
    $banner = preins_banner();
    $pago = PAGO_INFO;
    $aviso = AVISO_NO_PAGO;
    $rows = '';
    foreach ([1, 2, 3] as $i) {
        $p = __DIR__ . "/../../public/assets/lona_$i.jpg";
        if (is_file($p)) {
            $rows .= '<img src="/assets/lona_' . $i . '.jpg?v=' . filemtime($p)
                . '" alt="Patrocinadores de ' . $clubn . '" class="lona-row">';
        }
    }
    $lona = $rows
        ? '<section class="patro"><h2>Quienes lo hacen posible</h2>'
        . '<p class="muted">Gracias a nuestros patrocinadores por apoyar las 24 Horas.</p>'
        . '<div class="lona-rows">' . $rows . '</div></section>'
        : '';
    $kc = club();
    $ev = e(evento());
    $contacto = e(cfg('CLUB_EMAIL', 'club@tudominio.example'));   // contacto del evento (configurable)
    $rifa = '<section class="rifa"><h2>🎁 Rifa solidaria</h2>'
        . '<p>Habrá <strong>rifa</strong> durante las ' . e(evento()) . ' y <strong>buscamos regalos</strong>. '
        . 'Si quieres colaborar aportando algún regalo, contacta con el club: '
        . '<a href="mailto:' . $contacto . '">' . $contacto . '</a>.</p></section>';
    $c = <<<HTML
$membrete
$banner
<section class="hero">
  <p class="kicker">🏆 Jornada de puertas abiertas · un día entero de deporte y juego</p>
  <h1>$ev · $clubn</h1>
  <p>Una <strong>jornada de puertas abiertas</strong> para aficionados al deporte y a pasarlo bien:
     deportes individuales, juegos sociales y fútbol de menores.</p>
  <div class="aviso" style="font-size:1.05rem">⏰ <strong>La preinscripción ya está cerrada</strong> — el plazo ha finalizado. Ya no se admiten nuevas inscripciones.</div>
  <p>Si <strong>ya te inscribiste y pagaste</strong>, entra en tu área privada para consultar tus
     <strong>inscripciones, los emparejamientos y los horarios</strong>.</p>
  <p><a class="cta-btn" href="/login">Acceder a mi cuenta →</a></p>
  <p class="note">¿Tienes alguna duda? Escríbenos al club: <a href="mailto:$contacto">$contacto</a>.</p>
</section>
$rifa
$lona
HTML;
    render('Inicio', $c, $u);
}
