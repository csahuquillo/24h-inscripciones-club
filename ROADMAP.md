# Roadmap

Estado actual: **en producción** para un club real. Preinscripción, pago presencial marcado por
un administrador, área de usuario, panel de gestión con notificaciones por email y resumen
económico. Lo que sigue son mejoras previstas, priorizadas por impacto/esfuerzo. Todo es
realista en PHP sencillo, sin apps nativas.

## Para la próxima edición (lecciones de la última)

Carencias que en la última edición hubo que suplir a mano, fuera de la aplicación, con hojas de
cálculo y mensajería, más alguna regla nueva que el evento pidió a gritos. El objetivo es que el
año que viene el control esté entero dentro del sistema. Pendiente de estudiar en detalle antes
de implementar.

El contexto completo —qué se decidió, por qué, y con qué se tropezó— está en
[docs/DECISIONES.md](docs/DECISIONES.md), y el formato de cada actividad en
[docs/FORMATO-DISCIPLINAS.md](docs/FORMATO-DISCIPLINAS.md).

- **Consolidación de parejas: una pareja, una sola vez.** Hoy la inscripción guarda una fila por
  participante con el nombre de la pareja en un campo de texto libre, así que **la misma pareja
  existe dos veces**, una por cada miembro. De ahí salió la mayor parte del ruido de la última
  edición: en el área de usuario uno veía su inscripción y otra vez la misma pareja desde el lado
  del compañero; en los listados y en el Excel cada pareja aparecía duplicada; y los recuentos no
  cuadraban (grupos con un número impar de filas por parejas escritas de dos formas distintas).
  La pareja debe pasar a ser una **entidad propia con identidad única**, a la que se enlazan sus
  dos miembros: **da igual quién se inscriba primero, pero la pareja sale una sola vez** en el área
  de usuario, en los listados, en el Excel y en los cuadrantes, y la inscripción del segundo miembro
  se une a la existente en vez de crear otra. Incluye normalizar el nombre al vincular (mayúsculas,
  acentos, espacios sobrantes, literales tipo "con Fulano") y avisar al admin de las parejas
  incompletas o ambiguas en vez de dejarlas pasar. Detalle del problema en
  [docs/DECISIONES.md](docs/DECISIONES.md#parejas-la-fuente-de-casi-todos-los-líos).
- **Emparejamientos por sorteo, no por orden alfabético.** Ahora los grupos y cruces se forman
  siguiendo el orden en que se listan los inscritos, que en la práctica es alfabético: sale un
  reparto previsible, agrupa a gente del mismo entorno (parejas, hermanos, apellidos comunes) y
  obliga al coordinador a rehacerlo a mano. Debe pasar a ser un **sorteo aleatorio con semilla
  reproducible** (misma semilla → mismo sorteo, para poder repetirlo y justificarlo), con
  restricciones configurables: separar a los inscritos que vienen juntos, respetar cabezas de
  serie y equilibrar los grupos por número de participantes. Y un botón de "volver a sortear"
  con vista previa antes de confirmar. **Ojo: el sorteo del pádel no es uniforme, es sembrado por
  nivel** — las parejas declaran un nivel y se reparten para que los grupos queden equilibrados
  (ver [docs/DECISIONES.md](docs/DECISIONES.md)). El sorteo debe aleatorizar **dentro de cada
  nivel/bombo**, no sobre el total.
- **Horarios con control de las horas de juego.** El calendario debe conocer la duración real
  de cada partido y la franja que ocupa, no solo su orden. Requisitos detectados:
  separación mínima configurable entre dos partidos del mismo jugador (para descansar y para
  cubrir cambios de pista), detección automática de solapes **entre actividades distintas**
  (quien se apunta a varias no puede estar en dos sitios a la vez), reserva de instalaciones
  libres para recuperaciones y cambios, y aviso al admin cuando un cambio manual rompa alguna
  de estas reglas. Con esto el cuadrante deja de validarse a ojo.
- **Introducción de resultados.** Poder registrar el resultado de cada partido en el sistema:
  quién gana, el marcador y, según la actividad, el detalle que corresponda (juegos, puntos,
  partidas). De ahí salen solas la clasificación de cada grupo, los criterios de desempate y
  el cruce de la fase final, que ahora se calculan a mano sobre papel. Es el paso previo a
  *Resultados en vivo* (ver más abajo): primero que se puedan **meter y consultar**, y ya
  después que se publiquen en tiempo real. El marcador debe ser configurable por actividad,
  porque cada una cuenta de una forma.

- **Penalización por incomparecencia.** *(Idea sin cerrar: falta decidir el criterio.)* Una pareja
  que no se presenta no solo pierde su partido: deja tirado al rival, bloquea una pista que estaba
  contada y descuadra la clasificación del grupo. Hoy no tiene ninguna consecuencia, así que sale
  gratis apuntarse y no aparecer. Ideas sobre la mesa, a elegir o combinar:
  - **Recargo en la siguiente edición** (del orden de +2 € por actividad) para quien no se presentó
    sin avisar.
  - **No poder inscribirse** en la edición siguiente, o poder solo en lista de espera, después de
    N incomparecencias.
  - Distinguir siempre entre **avisar con antelación** (que libera la plaza y no debería penalizar)
    y **no presentarse sin decir nada**, que es lo que hace daño.

  Implica marcar la incomparecencia al registrar el resultado del partido, guardarla asociada a la
  persona (no a la pareja, para que no arrastre al compañero que sí fue) y que el cálculo del precio
  la consulte al abrir el plazo. Encaja con el modelo multi-edición de *Ranking / palmarés histórico*
  y depende de **Introducción de resultados**: sin resultados no hay forma de saber quién faltó.
  Antes de aplicarla, acordarla con el club y **anunciarla en las bases** de la edición: una sanción
  que no se avisó de antemano no se puede cobrar.

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
- **Resultados en vivo.** Continuación natural de *Introducción de resultados*: el responsable de
  pista mete el resultado desde el móvil y el ganador avanza solo en el cuadro; página pública
  que se refresca (polling, sin websockets).
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

Casi todo vive sobre unas pocas tablas nuevas (`matches`, `results`, `waitlist`, `ediciones`) y
librerías PHP gratuitas y sin dependencias de sistema (FPDF/Dompdf para PDF, una lib de QR,
jsQR en el cliente). Para ~cientos de participantes en un día, *polling* + *cron* cubren de sobra.

Los tres puntos de la próxima edición encajan en el mismo esquema: el **sorteo** solo necesita
guardar la semilla y las restricciones junto al grupo generado, para poder reproducirlo; las
**horas de juego** piden que cada partido lleve inicio, duración e instalación (más una
validación que recorra los partidos de cada participante buscando solapes y descansos cortos,
también entre actividades distintas); y los **resultados** son una tabla `results` con el
marcador y un criterio de puntuación por actividad, del que se derivan clasificación y
desempates. Nada de esto exige salir de PHP sencillo.
