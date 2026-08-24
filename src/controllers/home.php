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
<section class="hero">
  <p class="kicker">🏆 Edición 2026 · ¡Gracias por participar!</p>
  <h1>$ev · $clubn</h1>
  <div class="banner abierto" style="font-size:1.05rem">🏆 <strong>El torneo ha terminado.</strong> La <strong>entrega de trofeos</strong> será el <strong>sábado 29 de agosto</strong> en el polideportivo. ¡Os esperamos para celebrarlo!</div>
  <p>Gracias a todas y a todos por unas <strong>24 Horas inolvidables</strong>: participantes, familias, voluntarios y responsables de cada disciplina.</p>
  <p>Si participaste, entra en tu área privada para ver el <strong>palmarés</strong> de todas las disciplinas y <strong>cómo has quedado</strong>.</p>
  <p><a class="cta-btn" href="/login">Acceder a mi cuenta →</a></p>
  <p class="note">¿Alguna duda? Escríbenos al club: <a href="mailto:$contacto">$contacto</a>.</p>
</section>
$lona
HTML;
    render('Inicio', $c, $u);
}
