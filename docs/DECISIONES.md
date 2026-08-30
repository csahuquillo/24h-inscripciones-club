# Decisiones y lecciones de la última edición

Qué se decidió, **por qué**, y con qué se tropezó. El objetivo es que quien monte la próxima
edición —aunque no sea quien montó esta— no tenga que reconstruir el razonamiento ni volver a
consultar a los responsables de cada disciplina.

Complementa a [FORMATO-DISCIPLINAS.md](FORMATO-DISCIPLINAS.md), que describe el *qué* de cada
actividad. Aquí está el *por qué*.

---

## Parejas: la fuente de casi todos los líos

**El problema.** La inscripción guarda una fila por **participante**, con un campo de texto libre
para el nombre de la pareja. Consecuencia: una pareja existe en el sistema **dos veces**, una por
cada miembro, y cada una apunta a la otra por su nombre escrito a mano.

Todo lo que se derivó de ahí:

- En el área de usuario, quien inscribía a otra persona veía **su propia inscripción y otra vez la
  misma pareja** desde el lado del compañero. Parecía que estaba apuntado dos veces.
- En los listados y en el Excel, cada pareja aparecía **duplicada**, una vez por miembro. Los
  responsables de disciplina lo interpretaban como inscripciones distintas.
- Los recuentos no cuadraban. Al agrupar por pareja, varios grupos daban un **número impar de
  filas** (9 filas para 5 parejas, 7 para 4), señal de parejas escritas de dos formas distintas o
  con un solo miembro registrado.
- El texto libre trae de todo: mayúsculas y minúsculas mezcladas, dobles espacios, apellidos a veces
  sí y a veces no, y literales como `"- pareja"` o `"con <nombre>"` en el campo del compañero.
  Cruzar eso obliga a normalizar y a comparar por aproximación, con el riesgo de unir a dos personas
  distintas de nombre parecido (pasó: dos personas con el mismo nombre y apellido, una adulta y otra
  infantil, estuvieron a punto de fusionarse).

**Cómo se resolvió esta edición.** A base de deduplicar en cada sitio donde se mostraba la
información: normalizando el nombre (sin acentos ni puntuación, en minúsculas), tratando la pareja
como un **conjunto no ordenado** de sus dos miembros y quedándose con una sola aparición. Funciona,
pero es un parche aplicado en varios lugares y hay que acordarse de aplicarlo en cada listado nuevo.

**Cómo debe resolverse.** La pareja tiene que ser una **entidad propia** con identidad única, a la
que se enlazan sus miembros. Da igual cuál de los dos se inscribiera primero: la pareja se crea una
vez y **aparece una sola vez** en el área de usuario, en los listados, en el Excel y en los
cuadrantes. La inscripción del segundo miembro no crea otra pareja, se une a la existente. Está
recogido en el [roadmap](../ROADMAP.md) como *Consolidación de parejas*.

---

## Emparejamientos por orden alfabético

Los grupos se formaron recorriendo los inscritos en el orden en que salen listados, que en la
práctica es alfabético. Efectos: reparto previsible, y **gente del mismo entorno junta** —parejas,
hermanos, apellidos comunes— que acaba compitiendo entre sí en la primera ronda. Obliga al
responsable a rehacerlo a mano.

Debe ser un **sorteo aleatorio con semilla reproducible**: guardando la semilla se puede repetir el
mismo sorteo y justificarlo si alguien protesta, que es justo lo que un sorteo a ojo no permite.
En el [roadmap](../ROADMAP.md).

---

## Una única fuente de verdad

Durante la edición convivieron la base de datos, un Excel compartido y varias versiones sueltas
enviadas por WhatsApp. Se generaban contradicciones constantes: alguien trabajaba sobre una copia
antigua y proponía cambios ya aplicados.

**Decisión: la base de datos manda.** El Excel se **genera** desde ella con un script y se
actualiza **sobre el mismo fichero de Drive**, para que el enlace no cambie nunca. Se anunció en el
grupo que no habría más versiones sueltas. A partir de ahí los descuadres desaparecieron.

Regla para la próxima edición: **un solo enlace, actualizado en sitio, y ninguna versión adjunta.**

---

## Todos los cuadrantes dentro del sistema

Los responsables de cada disciplina preparaban sus cuadros por su cuenta —en papel, en fotos de
WhatsApp, en Excel propio— y la web solo conocía las inscripciones. Eso impedía comprobar solapes
entre disciplinas y obligaba a contestar a mano "¿a qué hora juego?".

**Decisión: importar todos los cuadrantes a la base de datos**, en una tabla de partidos con
disciplina, orden, franja, instalación y los dos contendientes. A partir de ahí salen solos el área
de usuario, el Excel, los correos personalizados y la validación de solapes.

Conviene mantenerlo: es lo que permitió detectar conflictos que nadie había visto.

---

## Validación automática de solapes

Con todos los cuadrantes en la base de datos se pudo cruzar quién juega a qué hora en **todas** las
disciplinas. Apareció más de un conflicto real que había pasado desapercibido.

Dos errores cometidos al programar esa comprobación, por si se reescribe:

- **Comparar nombres por igualdad exacta no sirve.** La misma persona aparece como "Nombre Apellido"
  en una disciplina y "Nombre Apellido Apellido" en otra. Hay que comparar por subconjunto de
  palabras… y aun así revisar a mano los positivos antes de avisar a nadie, porque dos personas
  distintas pueden compartir nombre y primer apellido.
- **Cuidado con el cambio de día.** El evento cruza la medianoche: las horas hay que normalizarlas a
  una línea temporal continua. Un umbral mal puesto hace que un partido de la tarde se interprete
  como de la madrugada siguiente y se pierdan solapes reales.

---

## Comunicación con los participantes

- **Un correo personalizado a cada inscrito** con sus inscripciones, sus grupos, sus partidos con
  hora, instalación y rival, y el enlace para entrar en su área. Redujo drásticamente las consultas
  sueltas.
- Cuidado con quien **solo aparece como capitán de equipo** y no tiene inscripción individual: si el
  envío se construye recorriendo inscripciones, esas personas se quedan fuera. Hay que incluirlas
  explícitamente.
- Igual con quien **fue inscrito desde la cuenta de otro**: si el área de usuario solo mira las
  inscripciones creadas por la propia cuenta, esa gente no ve nada. Hay que buscar también por
  nombre del participante, y por pertenencia a equipo para que los padres vean los partidos de sus
  hijos.
- Los correos a participantes salen de una **dirección propia del evento**, nunca de una cuenta
  personal, con `Reply-To` a la dirección de organización.

---

## Cambios de cuadrante una vez publicado

Cuando un cuadrante ya se ha comunicado y **hay carteles impresos**, cambiarlo cuesta más de lo que
arregla. Criterios que se acabaron aplicando:

- **Preferir la solución de mínimo movimiento.** Ante un problema de descansos, se descartaron dos
  reoptimizaciones completas (que movían casi todos los partidos) a favor de intercambios puntuales
  de tres y de un partido.
- **Si la gente ya se ha organizado entre ella, no se toca.** Ocurrió con los grupos de una
  disciplina: la asignación en el sistema no coincidía con el reparto original de la responsable,
  pero los participantes ya estaban quedando entre ellos. Se decidió **adaptar los grupos de
  WhatsApp al sistema**, y no al revés.
- **Confirmar antes de tocar producción.** Más de una vez la corrección "evidente" resultó
  innecesaria al preguntar. Verificar contra la fuente original —y contra la persona responsable—
  antes de cambiar nada.

---

## Cómo se calcula la clasificación y los cruces de una liguilla

Esto se hizo a mano, con los resultados llegando por mensajería y la tabla calculada aparte. Queda
descrito paso a paso porque **es directamente automatizable** y es lo que debería hacer el sistema
en cuanto existan los resultados (ver *Introducción de resultados* en el [roadmap](../ROADMAP.md)).

**1. Punto de partida: el cuadro completo.** Una liguilla de N unidades son `N*(N-1)/2` partidos y
cada una juega `N-1`. Con 7 parejas: 21 partidos, 6 por pareja. Ese número es la vara de medir:
si no cuadra con los resultados recibidos, faltan datos.

**2. Recoger resultados contra el cuadro, no contra una lista suelta.** Cada resultado se casa con
un partido *previsto*. Así se detecta solo lo que falta y se descartan duplicados. En la última
edición llegaron 18 de 21 por mensajería, y el coordinador creía tener 20 apuntados: sin cruzar
contra el cuadro, esa diferencia no se ve.

**3. Normalizar los nombres.** Los resultados llegan en lenguaje natural y con las parejas escritas
de cualquier manera: sólo el nombre de pila, invertidas, con apodos, con erratas, o incluso con el
literal `"y pareja"`. Conviene resolver cada bando **contra la lista oficial de parejas** y, ante la
duda, preguntar en vez de adivinar. Casos reales: un nombre de pila que no existía en la actividad
y otro cambiado por uno parecido; ambos se resolvieron sin ambigüedad porque **solo encajaba un
partido del cuadro**, que es el mejor criterio de desempate.

**4. Contabilizar.** Por unidad: partidos jugados, ganados, perdidos, puntos a favor y en contra, y
diferencia. Dos casos que hay que tratar aparte:
- **Incomparecencia**: se anota victoria del que sí se presentó con un marcador convencional
  (se usó `1-0`) y se marca como falta, que es el dato que necesitará la futura penalización.
- **Punto de oro** u otro desempate dentro del partido: el marcador queda igualado pero hay
  ganador. Si se calcula el ganador comparando tantos, ese partido sale mal: hay que guardar
  el ganador explícitamente, no deducirlo.

**5. Ordenar con los criterios de la actividad**, que los fija su responsable y **hay que preguntar
antes de calcular**. Los que se aplicaron en pádel infantil:
1. Número de partidas ganadas.
2. Empate entre **dos**: resultado directo entre ellas.
3. Empate entre **tres o más**: mejor diferencia de puntos.

**6. Cruces**: `1º vs 4º` y `2º vs 3º`.

**7. Comprobar si lo que falta cambia algo.** Paso barato y muy rentable: simular todos los
desenlaces posibles de los partidos sin resultado y ver si alteran el top-4 o los emparejamientos.
En la última edición faltaban 3 partidos y **los cruces salían idénticos en los seis escenarios**,
así que se pudieron publicar sin esperar. Lo contrario también sirve: saber que un partido concreto
es decisivo y que no se puede anunciar nada hasta tenerlo.

Automatizado, esto es una función que recibe los resultados de una actividad y devuelve tabla y
cruces, más un aviso de "faltan N partidos" y "de ellos, estos son decisivos".

## Cómo se monta la fase final cuando hay varios grupos

La liguilla de un solo grupo es directa (ver el apartado anterior). Cuando la actividad se juega en
**varios grupos** y a la fase final pasa un número de unidades que no es múltiplo del número de
grupos, hace falta un criterio extra. Así se resolvió en la actividad más grande de la última
edición, reconstruido a partir del cuadro que publicó su responsable:

**Punto de partida.** 15 parejas repartidas en **3 grupos de 5**. A cuartos de final pasan **8**.

**El método, tal cual.** No se seleccionan "los N primeros de cada grupo" por separado: se construye
un **ranking global único** con todas las unidades que optan a la fase final, y de ahí sale el cuadro.
El orden del ranking es, en este orden exacto:

1. **Puesto que ocupa en su grupo** (todos los primeros, luego todos los segundos, luego los terceros).
2. **Partidos ganados**.
3. **Diferencia de tantos**.

El primer criterio es el que no se ve a simple vista y el que lo explica todo: en la tabla real, un
**segundo de grupo con 2 victorias y diferencia −6 va por delante de un tercero con 2 victorias y
diferencia +5**. Ordenar solo por victorias y diferencia da un orden distinto del que se jugó. El
puesto en el grupo manda sobre el rendimiento absoluto, que es lo justo cuando los grupos no se han
enfrentado entre sí.

**Cuántos pasan** depende de la estructura de grupos, y la regla es la misma para todos: se cogen las
`potencia de 2` primeras del ranking global. En la edición real:
- **−4**: 3 grupos de 5 → clasifican los 2 primeros de cada grupo y los **2 mejores terceros** (8).
- **+4**: 5 grupos de 4 → clasifican los **5 primeros y los 3 mejores segundos** (8).

Las dos reglas son el mismo ranking global (puesto en grupo → ganados → diferencia, top 8); lo que
cambia es lo que ese orden produce según cómo estén repartidos los grupos.

**Un matiz de la comparación entre grupos que corrige lo que yo había supuesto**: para comparar
unidades de grupos distintos **no se usa la diferencia total sino la MEDIA de puntuación** (lo dicen
las reglas escritas: *"entre grupos: media de puntuación"*). Es exactamente la normalización que hace
falta cuando los grupos tienen distinto número de partidos, y ya estaba resuelta así en origen.

Con 3 grupos de 5, los 3 primeros de cada uno son 9 candidatos para 8 plazas. En la edición real el
ajuste lo resolvió además una **baja**: una pareja clasificada avisó de que no podía jugar la fase
final, se marcó como BAJA y el resto subió.

> **Si una unidad clasificada renuncia**, se marca como baja, **se salta en la numeración** y el
> resto sube. No se deja hueco ni se repesca de otro grupo.

**La siembra es la clásica**: `1-8, 2-7, 3-6, 4-5`. En los cuatro cuartos de la edición real las
posiciones de cada enfrentamiento **suman siempre 9**. Como es siembra pura por ranking, **no se
evita** que coincidan dos unidades del mismo grupo: pasó, se cruzaron el 2º y el 7º, ambos del mismo
grupo.

**Corrección sobre las incomparecencias.** En una versión anterior de este documento deduje, de la
aritmética de un grupo, que el no presentado se anotaba `6-0`. **Las reglas oficiales dicen otra
cosa**: *"no presentado: gana la pareja presentada, resultado la media de las partidas realizadas en
el grupo"*. Es decir, a la que se presenta se le asigna como marcador **su propia media en el grupo**,
no un `6-0` fijo. El `6-0` cuadraba por casualidad en aquel grupo concreto. La regla de la media es
mejor porque no infla ni desinfla artificialmente la diferencia de quien se libra de jugar. **Aun
así, no está unificado entre actividades**: en el pádel se usa la media, pero en otra disciplina de la
misma edición el responsable anotó las incomparecencias `1-0`. Como el desempate mira la puntuación,
conviene un **único convenio para todo el evento** — y el de pádel (media del grupo) es el más justo.

**Cómo se auditó todo esto.** Primero se reconstruyó a mano la tabla de un grupo cuya liguilla estaba
completa en el chat, lo que ya delató la baja y el criterio de orden. Después apareció el **libro de
cálculo completo del responsable**, que confirmó el método y aportó las reglas escritas (ver la
sección siguiente). La lección operativa se mantiene: **conservar los resultados aunque la fase de
grupos haya terminado** es la única forma de auditar un cuadro que llega hecho.

### El libro de cálculo del responsable, como plantilla reutilizable

El responsable de pádel entregó un único Excel que **codifica el método entero** y sirve tal cual de
plantilla para el año que viene. Merece la pena conservarlo porque resuelve, ya escritas, las
decisiones que en otras actividades se tomaron sobre la marcha. Lo que contiene:

- **Hoja de REGLAS** con todo el reglamento deportivo: duración (liga y cuartos 30 min + 5 de
  calentamiento; **semifinales 45 min; final al mejor de 2 sets**), **sin empates** (se decide por
  *punto de oro*), marcador continuo, cómo se comunica el resultado en el grupo de difusión, los
  criterios de clasificación dentro y entre grupos, la regla del no presentado, y el margen de
  **5 minutos** antes de dar un partido por perdido. Un reglamento así, cerrado y publicado, es lo
  que evita las discusiones que sí hubo en las actividades sin reglas escritas.
- **Una hoja por categoría** (infantil, −4, +4) con las parejas, su grupo, su nº de código
  (`A1`, `C4`, `INF 3`…) y la tabla de clasificación.
- **Rejilla horaria de la fase final entera**, con cada ronda en una franja de 30 min sobre 2 pistas
  y **codificada por posiciones, no por nombres**: los cuartos son `1-8, 2-7, 3-6, 4-5`, las
  semifinales cruzan a los ganadores por mitades del cuadro, y las finales cierran el día
  (final −4 a las 19:00, final +4 a las 20:30). Al estar en códigos, la rejilla se rellena sola en
  cuanto se conocen los clasificados: es literalmente el algoritmo de la fase final escrito en celdas.
- **Siembra por nivel en el sorteo de grupos.** En +4 las parejas declararon un **nivel** (5, 4, 3) y
  se repartieron para que cada grupo quedara equilibrado, con cabezas de serie. No fue un sorteo
  puramente aleatorio: esto matiza el punto *"emparejamientos por sorteo"* del [roadmap](../ROADMAP.md)
  — el sorteo debe ser **aleatorio pero sembrado por nivel**, no uniforme.
- **Horario de la fase de grupos** intercalando las tres categorías (infantil, −4, +4) en dos pistas
  a lo largo de toda la noche, que es la materialización de la regla *"mezclar modalidades en el
  tiempo"*.

Comparado con lo que ya teníamos en la base de datos, **el cuadrante de la fase de grupos coincide**
(nuestra importación se hizo a partir de estos mismos cuadros). Lo que la base de datos **no** tenía y
este libro sí es la **fase final** (cuartos, semis y finales) y el **reglamento**. Para la próxima
edición, este Excel es el punto de partida a convertir en lógica del sistema; ver los criterios
enumerados más abajo.

**La programación de la ronda.** Los 4 cuartos se colocaron en **dos franjas de media hora sobre dos
pistas** (dos partidos simultáneos a una hora, dos a la siguiente): toda la ronda se despacha en una
hora, que es lo que permite encadenar semifinales y final el mismo día. Es un buen patrón a
conservar: **cada ronda ocupa `nº de partidos / nº de pistas` franjas**, y conviene calcularlo hacia
atrás desde la hora a la que se quiere entregar los trofeos.

**Un detalle nada obvio:** en ese cuadro **dos parejas del mismo grupo se enfrentaron en cuartos**.
No es efecto de ninguna repesca —el libro del responsable confirma que es **siembra pura** `1-8, 2-7,
3-6, 4-5`—: al sembrar solo por posición del ranking, nada impide que dos del mismo grupo caigan en la
misma llave. Si se quiere evitar (para que no se repita en cuartos un partido ya jugado en la
liguilla) hay que **añadir esa restricción a la siembra explícitamente**; en la última edición no se
hizo.

### Fases finales pequeñas: adaptar el formato al número que queda

No todas las actividades llegan a la fase final con gente suficiente para el cuadro previsto, y la
regla que se aplicó fue **reducir el formato en vez de forzarlo**:

- Liguilla de 4 con **una retirada**: en lugar de montar unas semifinales cojas con los tres que
  quedan, se juega **final directa entre los dos primeros**.
- Actividad con **solo dos participantes**: **final directa**, sin fase previa.
- Dos grupos: **semifinales cruzadas** 1ºA-2ºB y 1ºB-2ºA.

Generalizando, y esto sí es automatizable: con `N` unidades disponibles para la fase final, se juega
el cuadro de la **mayor potencia de 2 que quepa en N** (2 → final; 4 → semifinales; 8 → cuartos), y
si sobran unidades caen las peores del ranking. Es preferible a rellenar con *byes*, que en un evento
de un día regalan una ronda y descuadran los horarios.

La consecuencia práctica: **el formato de la fase final no se puede fijar del todo antes de que
termine la de grupos**, porque depende de cuántos siguen en pie. Lo que sí se puede fijar de antemano
es la *regla* que decide el formato.

### La técnica de publicación: publicar, escuchar, corregir

El responsable no publicó el cuadro y se olvidó. La secuencia real fue:

1. Monta el cuadro con los clasificados por criterio deportivo y lo **publica como imagen**.
2. Los afectados lo revisan y avisan de lo que no cuadra — en este caso, una pareja que no podía
   jugar la fase final.
3. **Corrige, borra la publicación anterior** y publica la versión buena, ya en texto plano.

Los dos aciertos que conviene copiar: **borrar la versión antigua** en vez de dejar dos cuadros
circulando (el mismo principio de fuente única de verdad de más arriba), y publicar la versión
definitiva **en texto**, que se puede citar, buscar y leer desde el móvil, en lugar de una foto.

El fallo de fondo es que la disponibilidad se comprueba **después** de montar el cuadro. Invirtiendo
el orden —confirmar quién sigue en pie antes de sortear— el cuadro nace bien a la primera.

### Lo que hay que dejar definido para automatizar esto

Para el pádel, la mayoría **ya están definidos** en la hoja de REGLAS del responsable; lo que falta es
adoptar ese mismo esquema para las demás actividades y traducirlo a lógica del sistema. Los criterios,
tal como quedaron (los que vienen del reglamento de pádel se marcan con ✔):

1. ✔ **Ranking global**: puesto en el grupo → partidos ganados → diferencia. Añadir un cuarto criterio
   (tantos a favor) para los empates que aún queden.
2. ✔ **Cuántos pasan**: los `potencia de 2` primeros del ranking global. Da "2 por grupo + mejores
   terceros" (−4) o "5 primeros + 3 mejores segundos" (+4) según la estructura, sin necesidad de una
   regla distinta por caso.
3. ✔ **Comparación entre grupos por MEDIA de puntuación**, no por total. Resuelve de raíz los grupos
   de distinto tamaño (era mi duda pendiente: la media ya es la normalización correcta).
4. ✔ **Incomparecencia** = victoria de la presentada con marcador igual a **su media en el grupo**, y
   **5 minutos** de cortesía antes de darla por perdida. **Falta unificarlo con el resto de
   actividades** (en otra se usó `1-0`); el convenio de pádel es el bueno para todo el evento.
5. **Siembra** `1-N, 2-(N-1)…`, y decidir **si se evita o no** que se crucen en la primera ronda dos
   unidades del mismo grupo. En pádel no se evitó; conviene que sea una decisión, no un efecto
   colateral.
6. **Renuncias**: se marcan como baja, se saltan en la numeración y el resto sube. Exige un estado
   explícito de *disponible para la fase final*, preguntado **antes** de generar el cuadro.
7. ✔ **Sorteo de grupos sembrado por nivel**: las parejas declaran nivel y se reparten para equilibrar
   los grupos. El sorteo del [roadmap](../ROADMAP.md) debe ser aleatorio **dentro de cada nivel**, no
   uniforme.
8. **Incomparecencias** en la fase de grupos: si además penalizan de cara al año siguiente (ver el
   [roadmap](../ROADMAP.md)).

Con esos puntos fijados, generar el cuadro es determinista: una función que recibe las tablas de los
grupos y la lista de quién sigue disponible, y devuelve el ranking, el cuadro sembrado y la parrilla de
horas. El libro de pádel es, de hecho, esa función escrita a mano en celdas.

## Recopilar el palmarés y el correo de reconocimiento

Al terminar el evento se envió a **todos los inscritos** un correo con los **campeones y
subcampeones de cada disciplina**. Funcionó muy bien como cierre (da bombo a los ganadores y
recupera a la gente que se fue al quedar eliminada), pero recopilarlo costó más de lo que debería.
Lecciones:

- **Los resultados llegan dispersos y en lenguaje natural.** Cada responsable los cantó por su
  canal (unos en el grupo grande, otros en el suyo de disciplina, algunos solo de palabra), y con
  apodos, motes y nombres a medias. Reconstruir las 17 categorías fue un rastreo manual por varios
  chats. Con *Introducción de resultados* en el sistema (ver [roadmap](../ROADMAP.md)) esto sería
  automático: el palmarés se genera solo de la tabla de resultados.
- **Cuidado al casar apodos con la lista oficial.** Un mismo mensaje de "campeones de tal" se
  atribuyó primero a la disciplina equivocada, porque quien lo publicó coordinaba varias a la vez.
  Hay que confirmar **qué disciplina** y **qué nombres completos** contra el padrón antes de darlo
  por bueno, no fiarse del contexto de quién lo dijo.
- **Nombrar a los equipos por su nombre oficial, nunca por una familia o un apodo.** En el correo,
  a un equipo campeón (de más de una decena de jugadores) se le llamó por el apellido de la familia
  que más suena en él. Es **menospreciar al resto del equipo**: reduce a todos los demás al apellido
  de unos pocos. En una comunicación oficial, el nombre es el del equipo y punto. El apodo cariñoso
  vale en una charla de grupo, no en el correo que reciben más de cien personas.
- **Un error en un correo masivo casi nunca se arregla con otro correo masivo.** Reenviar una
  corrección a los 130 amplifica el desliz en vez de taparlo, y encima hace que todos relean justo
  la línea del fallo. Si algo hay que corregir, se hace **en positivo** (sumando reconocimiento, no
  pidiendo perdón) y por el canal más acotado posible: al afectado directo, o en el grupo. A veces
  la mejor opción es asumir la colleja y no tocar nada.
- **Antes de enviar a los 130, un repaso de nombres.** Mandar primero una copia de prueba a la
  organización y leerla entera evita justo estas cosas (apodos, "(no se presentó)" señalando a
  alguien, un nombre a medias). El coste de revisar es cero comparado con el de rectificar.

## La web después del evento (ciclo de vida de la edición)

Una edición no acaba cuando se juega el último partido: la web pasa por varios estados y conviene
tenerlos previstos.

- **Home durante el evento → home de "terminado".** Al cerrar, la portada deja de anunciar la
  preinscripción y pasa a un mensaje de agradecimiento + el aviso de la **entrega de trofeos** (un
  banner con la fecha), con enlace al área privada. Se retiran las secciones ya caducadas (p. ej. la
  rifa "durante las 24 Horas"). Es un cambio de contenido en `home.php`, nada estructural.
- **Resultados en el área privada.** "Mi cuenta" muestra, al terminar, el **palmarés** de todas las
  disciplinas (tabla `palmares`) y, para quien lo tenga disponible, su **posición final** (hoy solo
  pádel, tabla `clasif_padel`, ver más arriba). Como los datos viven en la BD, **persisten entre
  ediciones**: quien repita al año siguiente puede consultar cómo quedó. El palmarés **no se publica
  en abierto** porque incluye datos de menores; vive tras el login.
- **Página estática de temporada baja.** El servidor del evento se apaga gran parte del año para no
  pagarlo en vano. Mientras está apagado, el dominio debe seguir mostrando algo: una **página
  estática** ("la edición X ha terminado, vuelve en agosto") en un hosting que **no dependa del
  servidor**. Esa página es **pública**, así que **no lleva palmarés ni nombres**. Conviene tenerla
  montada de antemano y activarla justo al apagar el servidor. Debe reutilizar el CSS y el logo de la
  web real para que no desentone (un **único fichero autónomo**, con el CSS y el logo embebidos, se
  despliega sin depender de `/assets`). Sobre el hosting hay dos caminos: **GitHub Pages** con el
  dominio redirigido, o —si el dominio ya está en **Cloudflare**— un **Cloudflare Worker** que sirve
  el HTML desde el *edge*. En la práctica ganó el Worker: el certificado HTTPS es **instantáneo**
  (Universal SSL del dominio), se puede **verificar y apagar el servidor el mismo día** sin esperar
  aprovisionamientos, y revertir es trivial (apagar el proxy del registro + quitar la ruta). El
  detalle operativo completo —construir la página, desplegar, conmutar el DNS, apagar y **reabrir al
  año siguiente**— está en **[CIERRE-DE-EDICION.md](CIERRE-DE-EDICION.md)**.

El orden natural al cerrar: (1) cargar resultados y palmarés en la BD, (2) cambiar la home a
"terminado" + trofeos, (3) tras la entrega de trofeos, apagar el servidor y **conmutar el dominio a
la página estática** — publicar y verificar la estática **antes** de apagar, para no dejar ni un
minuto el dominio en blanco. Paso a paso en
[CIERRE-DE-EDICION.md](CIERRE-DE-EDICION.md).

## Notas de operación

- **Los audios y las fotos de papel son parte del canal.** Buena parte de las instrucciones de los
  responsables llegó como nota de voz o foto de una hoja manuscrita. Conviene transcribirlas y
  volcarlas a texto en cuanto llegan, o se pierden.
- **Anotar quién decide qué.** Cada disciplina tiene su responsable y sus criterios; sin registrarlo,
  la información se va con la persona.
- **Lo que se imprime, se congela.** Anotar qué versión se llevó a imprenta y marcar a mano las
  diferencias posteriores, para que el papel y la web no se contradigan sin que nadie lo sepa.
