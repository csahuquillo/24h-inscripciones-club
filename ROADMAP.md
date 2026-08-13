# Roadmap

Estado actual: **en producción** para un club real. Preinscripción, pago presencial marcado por
un administrador, área de usuario, panel de gestión con notificaciones por email y resumen
económico. Lo que sigue son mejoras previstas, priorizadas por impacto/esfuerzo. Todo es
realista en PHP sencillo, sin apps nativas.

## Próximo (alto impacto / esfuerzo bajo-medio)

- **Generación automática de cuadros/brackets con siembra.** Al cerrar el plazo, generar los
  cuadros de eliminación (con *byes*) colocando las cabezas de serie ya marcadas en tenis/pádel
  en posiciones estándar, y liguillas/round-robin para los sociales. Editable a mano por el
  admin. Es el corazón del sistema y alimenta horarios y área de usuario.
- **Recordatorios automáticos por email (cron).** Sobre la cola ya existente: confirmación,
  "tu partido es a las HH:MM en pista X", cambios de horario. Reduce ausencias.
- **Lista de espera por actividad (cupos).** Cuando una actividad llega a su tope, pasar a lista
  de espera y promover automáticamente al siguiente si se libera plaza.
- **Exportar a Excel/PDF.** Listados por actividad, pagos pendientes/cobrados y cuadros/hojas de
  mesa imprimibles para el día del evento.

## Medio plazo

- **Pago online desde el móvil con pasarela de pago.** Integrar una plataforma de pago (p. ej.
  Stripe o Redsys) para que quien se preinscribe pueda **pagar desde el móvil** en el momento,
  como alternativa (o complemento) al pago presencial. Incluye conciliación con la caja
  presencial, gestión de comisiones/devoluciones y marcado automático de "pagado" al confirmarse
  el cobro. *(Se dejó fuera de la v1 a propósito por su complejidad; queda como evolución
  natural del flujo de pago.)*
- **Integración con el listado de socios del club.** Verificación **automática** de la condición
  de socio contra el padrón/sistema del club (importación periódica o API), para aplicar el
  precio correcto (socio/no socio) **sin cruce manual** y detectar altas/bajas. Sustituye el
  indicador manual "socio ✓ / ⚠ sin match" por una comprobación fiable en tiempo real.
- **Resultados en vivo.** El responsable de pista mete el resultado desde el móvil y el ganador
  avanza solo en el cuadro; página pública que se refresca (polling, sin websockets).
- **Check-in con QR el día del evento.** QR único por inscripción; en recepción se escanea con la
  cámara del móvil (web, sin app) para acreditar y/o confirmar el pago en el acto.

## Más adelante

- **Ranking / palmarés histórico entre ediciones.** Guardar campeones y resultados por año;
  clasificaciones históricas que fidelizan y generan "pique" cada edición.
- **Calendario personal (ICS).** "Añadir a mi calendario" por partido y agenda del participante.
- **Compartir en redes / web pública del evento** con cuadros en vivo y patrocinadores.
- **Borrado/anonimización RGPD automático** al cierre de temporada (cron) y política de retención
  del `audit_log`.

## Notas técnicas

Casi todo vive sobre 3–4 tablas nuevas (`matches`, `results`, `waitlist`, `ediciones`) y
librerías PHP gratuitas y sin dependencias de sistema (FPDF/Dompdf para PDF, una lib de QR,
jsQR en el cliente). Para ~cientos de participantes en un día, *polling* + *cron* cubren de sobra.
