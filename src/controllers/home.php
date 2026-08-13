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
    $rifaContacto = e($kc['email']) . ($kc['telefono'] !== '' ? ' · ' . e($kc['telefono']) : '');
    $rifa = '<section class="rifa"><h2>🎁 Rifa solidaria</h2>'
        . '<p>Habrá <strong>rifa</strong> durante las ' . e(evento()) . ' y <strong>buscamos regalos</strong>. '
        . 'Si quieres colaborar aportando algún regalo, contacta con el club: '
        . '<a href="mailto:' . e($kc['email']) . '">' . $rifaContacto . '</a>.</p></section>';
    $c = <<<HTML
$membrete
$banner
<section class="hero">
  <p class="kicker">🏆 Jornada de puertas abiertas · un día entero de deporte y juego</p>
  <h1>$ev · $clubn</h1>
  <p>Una <strong>jornada de puertas abiertas</strong> para aficionados al deporte y a pasarlo bien:
     seas o no del club, ¡te esperamos! Preinscríbete aquí y apúntate hasta a <strong>3</strong>
     actividades — dos deportivas y una social, o dos sociales y una deportiva.</p>
  <p class="muted">La preinscripción <strong>no da plaza</strong> hasta que pagas en el club
     (TPV o efectivo). Una vez pagado recibirás por email tu acceso para ver tus inscripciones,
     los emparejamientos y los horarios.</p>
  <p class="pago">💳 $pago</p>
  <p class="aviso">⚠️ $aviso</p>
  <div class="cards">
    <a class="card" href="/preinscripcion">
      <div class="ico">🎾</div>
      <h2>Deportes y actividades sociales</h2>
      <p>Tenis, tenis dobles, pádel, frontón · truc, parchís, dominó, rummikub, roby.</p>
      <span class="cta">Preinscribirme →</span>
    </a>
    <a class="card" href="/preinscripcion-futbol">
      <div class="ico">⚽</div>
      <h2>Fútbol · menores de 14</h2>
      <p>Inscripción por equipos. La formaliza un adulto responsable y paga una sola persona.</p>
      <span class="cta">Inscribir equipo →</span>
    </a>
  </div>
  <p class="note">Precio por actividad: <strong>3&nbsp;€ socios · 5&nbsp;€ no socios</strong>.</p>
</section>
<section class="como">
  <h2>Cómo funciona</h2>
  <ol class="steps">
    <li>Rellena tu <strong>preinscripción</strong> eligiendo hasta 3 actividades (2 deportivas + 1 social, o 2 sociales + 1 deportiva).</li>
    <li><strong>Pasa a pagar por el club</strong> el lunes 17 o martes 18 de agosto, de 18:00 a 21:00 h (TPV preferible, o efectivo). <strong>Sin pago en plazo no hay plaza.</strong></li>
    <li>Cuando el club confirme el pago, recibirás por <strong>email tu usuario y contraseña</strong>.</li>
    <li>Entra en la web para ver tus <strong>inscripciones, emparejamientos y horarios</strong>.</li>
  </ol>
</section>
$rifa
$lona
HTML;
    render('Inicio', $c, $u);
}
