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

## Notas de operación

- **Los audios y las fotos de papel son parte del canal.** Buena parte de las instrucciones de los
  responsables llegó como nota de voz o foto de una hoja manuscrita. Conviene transcribirlas y
  volcarlas a texto en cuanto llegan, o se pierden.
- **Anotar quién decide qué.** Cada disciplina tiene su responsable y sus criterios; sin registrarlo,
  la información se va con la persona.
- **Lo que se imprime, se congela.** Anotar qué versión se llevó a imprenta y marcar a mano las
  diferencias posteriores, para que el papel y la web no se contradigan sin que nadie lo sepa.
