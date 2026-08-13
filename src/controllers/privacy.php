<?php
declare(strict_types=1);

function ctrl_privacy(): void {
    $k = club();
    $nombre = e($k['nombre']); $cif = e($k['cif']); $dom = e($k['domicilio']);
    $mail = e($k['email']); $tel = e($k['telefono']); $ver = e(RGPD_VERSION);
    $c = <<<HTML
<article class="legal">
  <h1>Política de privacidad</h1>
  <p class="muted">Versión $ver</p>

  <h2>Responsable del tratamiento</h2>
  <p><strong>$nombre</strong> (CIF $cif)<br>$dom<br>
     Contacto: <a href="mailto:$mail">$mail</a> · $tel</p>

  <h2>Finalidad</h2>
  <p>Gestionar la preinscripción, el pago y la participación en el torneo «24 Horas»
     del club: organización de actividades, emparejamientos, horarios y las
     comunicaciones necesarias (confirmación de pago, emparejamientos, cambios de
     horario y avisos del evento).</p>

  <h2>Base jurídica</h2>
  <p>Su <strong>consentimiento</strong>, que otorga al marcar la casilla del formulario.
     Puede retirarlo en cualquier momento sin que ello afecte a la licitud del
     tratamiento previo.</p>

  <h2>Datos que tratamos</h2>
  <p>Nombre y apellidos, teléfono, correo electrónico, condición de socio y las
     actividades elegidas (con el nivel en pádel y la edad cuando la actividad lo
     requiere). No solicitamos datos innecesarios.</p>

  <h2>Menores</h2>
  <p>Las inscripciones de menores de 14 años las realiza y consiente su
     padre, madre o tutor. En el fútbol (equipos de menores) la inscripción la
     formaliza una persona adulta responsable, que declara contar con la
     autorización de los progenitores o tutores de los menores incluidos; de esos
     menores solo se recogen <strong>nombre y edad</strong>.</p>

  <h2>Conservación</h2>
  <p>Conservamos los datos durante la organización y celebración del evento y,
     como máximo, hasta el final de la temporada, salvo obligación legal. Después
     se suprimen o anonimizan.</p>

  <h2>Destinatarios</h2>
  <p>No se ceden datos a terceros salvo obligación legal. Se emplean proveedores
     tecnológicos que actúan como encargados del tratamiento para el alojamiento y
     el envío de correo, con las debidas garantías.</p>

  <h2>Sus derechos</h2>
  <p>Puede ejercer los derechos de acceso, rectificación, supresión, oposición,
     limitación y portabilidad escribiendo a <a href="mailto:$mail">$mail</a>.
     Si considera que sus derechos no han sido atendidos, puede reclamar ante la
     Agencia Española de Protección de Datos (<a href="https://www.aepd.es" rel="noopener">aepd.es</a>).</p>
</article>
HTML;
    render('Política de privacidad', $c, current_user());
}
