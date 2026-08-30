# 24h — Preinscripción y gestión de eventos deportivos de club

Aplicación web ligera para gestionar la **preinscripción** y la **gestión de pagos** de un
evento deportivo de club (tipo *"24 Horas del deporte"*, torneos de urbanización, jornadas de
puertas abiertas): deportes individuales, actividades sociales (parchís, truc, dominó…) y
fútbol infantil por equipos.

Nace para un club real y se publica como **plantilla reutilizable** para que cualquier otro
club pueda desplegarla. Sin dependencias de pago, sin apps nativas: **PHP + MariaDB/MySQL +
nginx**, pensada para correr en un servidor pequeño.

> Los datos del club, los secretos y las imágenes de marca **no** están en el repositorio: se
> configuran por variables de entorno y por ficheros locales. Ver [Configuración](#configuración).

---

## Características

- **Preinscripción pública** sin cuenta previa:
  - Individual/social con la regla **2+1** (máx. 3 actividades: 2 deportivas + 1 social o
    viceversa), validada también entre envíos.
  - **Fútbol infantil por equipos**: lo formaliza un adulto responsable, paga una sola persona,
    de los menores solo se piden nombre y edad.
  - Campos condicionales: nivel en pádel, edad en infantil.
  - **Ventana de plazo** configurable (abre/cierra a fecha y hora).
- **Consentimiento RGPD** explícito (con fecha e IP), **minimización** y datos de **menores**
  con consentimiento del adulto responsable.
- **Panel de gestión** (roles `staff`/`admin`):
  - Marcar **pagado** (TPV/efectivo) → registra el cobro y **envía por email** al usuario su
    acceso automáticamente.
  - **Anular** inscripciones/equipos, **cabezas de serie** (tenis/pádel), **grupos**.
  - **Notificaciones** por email (a todos, a los que ya pagaron, a los **impagados** —
    recordatorios— o por actividad), en **cola asíncrona**.
  - **Resumen económico**: previsto (según preinscripciones) vs. cobrado real (TPV/efectivo),
    pendiente, % conversión y desglose por actividad.
  - Cruce con el **padrón de socios** (indicador socio ✓ / ⚠ sin match) para aplicar el precio.
- **Área de usuario** (todo tras login, `noindex`):
  - Sus inscripciones y estado de pago; cambio de contraseña.
  - **Tus emparejamientos**: el grupo que le ha tocado en cada disciplina, con los rivales y un
    enlace `wa.me` para contactar con cada pareja **solo de su grupo** (sin exponer el número).
  - **Tus partidos**: hora, pista y rival, a partir del cuadro publicado por el responsable.
  - **Listas de sus deportes**: todas las parejas de las categorías en las que participa
    (una fila por pareja, sin datos de contacto), para detectar errores de inscripción.
- **Correo transaccional por AWS SES** firmado con SigV4 usando el **rol de instancia**
  (sin secretos estáticos en disco).
- **Seguridad por defecto**: argon2id, sentencias preparadas (sin SQLi), escape de salida
  (sin XSS), CSRF, cabeceras (CSP/HSTS/X-Frame-Options), rate-limiting, registro de auditoría,
  manejador global de errores.

## Stack

- PHP 8.3 (sin framework; router propio, ~20 ficheros)
- MariaDB/MySQL (InnoDB, utf8mb4)
- nginx + PHP-FPM
- AWS SES para email (opcional: cualquier SMTP sirve con pequeños cambios en `src/ses_mailer.php`)
- TLS con Let's Encrypt

## Estructura

```
public/            webroot (nginx apunta aquí)
  index.php        front controller / router
  assets/          CSS + (tus imágenes de club, no versionadas)
src/
  config.php       carga /etc/poli24h/env, manejador de errores
  db.php           PDO
  security.php     sesión, CSRF, cabeceras, rate-limit, auditoría
  auth.php         login (argon2id), roles
  domain.php       reglas de negocio, precios, plazo, datos del club (por env)
  ses_mailer.php   SES v2 vía SigV4 (rol de instancia)
  notify.php       plantillas de email + cola
  layout.php       maquetación
  controllers/     home, privacy, signup_individual, signup_football,
                   auth_ctrl, account, admin, password
migrations/
  schema.sql       esquema + semilla de actividades (edítala para tu club)
bin/
  create_admin.php        crea un administrador (CLI)
  send_notifications.php  procesa la cola de email (cron cada minuto)
docs/
  FORMATO-DISCIPLINAS.md  formato de cada actividad: grupos, sistema,
                          duración, instalaciones y reglas del cuadrante
  DECISIONES.md           decisiones tomadas, por qué, y con qué se tropezó
  CIERRE-DE-EDICION.md    apagar el servidor al terminar y dejar la web en
                          "temporada baja"; y cómo reabrir al año siguiente
```

## Requisitos

- Un servidor Linux (probado en Ubuntu 24.04) con nginx, PHP-FPM 8.3 y MariaDB/MySQL.
- Un dominio y certificado TLS (Let's Encrypt).
- Para el email: una cuenta AWS con **SES en producción** y el dominio verificado (DKIM), y
  el servidor con un **rol de instancia** con permiso `ses:SendEmail`. (Alternativa: adaptar
  `src/ses_mailer.php` a SMTP.)

## Instalación / despliegue

```bash
# 1) Paquetes
sudo apt-get install -y nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-curl \
     php8.3-xml php8.3-bcmath mariadb-server certbot python3-certbot-nginx

# 2) Código
sudo mkdir -p /var/www/poli24h
sudo cp -r . /var/www/poli24h/           # este repo
sudo chown -R root:www-data /var/www/poli24h
sudo find /var/www/poli24h -type d -exec chmod 750 {} +
sudo find /var/www/poli24h -type f -exec chmod 640 {} +

# 3) Base de datos
sudo mysql -e "CREATE DATABASE poli24h CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'poli24h'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CLAVE';"
sudo mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE ON poli24h.* TO 'poli24h'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql poli24h < /var/www/poli24h/migrations/schema.sql

# 4) Configuración (ver .env.example)
sudo mkdir -p /etc/poli24h
sudo cp .env.example /etc/poli24h/env      # y EDÍTALO
sudo chown root:www-data /etc/poli24h/env
sudo chmod 640 /etc/poli24h/env

# 5) vhost nginx (ejemplo) -> root /var/www/poli24h/public, PHP-FPM,
#    y bloquea /src /bin /migrations. Luego:
sudo certbot --nginx -d tudominio.example --redirect

# 6) Administrador inicial
sudo php /var/www/poli24h/bin/create_admin.php admin@tudominio.example "Administrador"

# 7) Cron de la cola de email (cada minuto)
echo '* * * * * www-data /usr/bin/php /var/www/poli24h/bin/send_notifications.php >/dev/null 2>&1' \
  | sudo tee /etc/cron.d/poli24h
```

Ejemplo mínimo de `location` en nginx dentro del `server`:

```nginx
root /var/www/poli24h/public; index index.php; server_tokens off;
location / { try_files $uri /index.php$is_args$args; }
location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ ^/(src|bin|migrations)/ { deny all; }
```

Recuerda `display_errors = Off` en `php.ini` de producción.

## Configuración

Todo se define en `/etc/poli24h/env` (ver [`.env.example`](.env.example)). Claves principales:
conexión a BD, `APP_KEY`, `BASE_URL`, remitente de correo (`MAIL_FROM`), `AWS_REGION`, y los
**datos del club** (`CLUB_NOMBRE`, `CLUB_CIF`, `CLUB_DOMICILIO`, `CLUB_EMAIL`, `CLUB_TELEFONO`)
y `EVENTO_NOMBRE`.

Otros ajustes están en el código, fáciles de editar:
- **Fechas del plazo, precios y niveles**: constantes al inicio de `src/domain.php`
  (`PREINS_OPEN`, `PREINS_CLOSE`, `PRICE_SOCIO`, `PRICE_NO_SOCIO`, `PAGO_INFO`…).
- **Actividades/disciplinas**: la semilla en `migrations/schema.sql` (edítala para tu club).
- **Padrón de socios** (opcional): carga tu propio listado en la tabla `socio_padron`
  (`num_nuevo`, `apellidos_nombre`, `norm_nombre`, …). No incluyas datos personales en el repo.

## Personalización visual

Coloca en `public/assets/` (no versionado): `logo.png`/`.jpg`/`.svg`, `membrete.png` y
`lona_1..3.jpg` (patrocinadores). La app los detecta solos; si no están, usa el nombre del club
como texto. Los colores/estilos están en `public/assets/style.css`.

## Uso del panel de gestión (staff)

Con una cuenta `staff` o `admin` (login en `/login`), el **Panel de gestión** (`/admin`) permite:

- **Dar de alta presencial** (`/admin/nueva`): crea una inscripción para quien se apunta en
  persona sin haberse preinscrito online (contacto, participante, actividades, pareja), con
  opción de **cobrar en el acto** (marca pagado y envía el acceso). Misma validación que el alta online.
- **Marcar pagado**: registra el cobro (TPV/efectivo) de una inscripción o equipo y envía a la
  persona su email de acceso. Muestra un indicador de socio (cruce con el padrón) para el precio.
- **Editar** (`/admin/editar`): corrige sin anular — nombre, actividad, email, socio (recalcula
  el precio), nivel y pareja/compañero.
- **Anular**: quita una inscripción o equipo (errores, duplicados, bajas).
- **Cabezas de serie**: marca semillas en tenis/pádel para el sorteo.
- **Notificaciones**: email a todos / pagados / impagados (recordatorio) / por actividad, en cola.
- **Resumen económico**: previsto (según preinscripciones) vs cobrado real, con desglose y % conversión.
- **Gestión de staff** (solo `admin`): alta de cuentas `staff`/`admin`.

Crea el primer administrador con `bin/create_admin.php` (ver Instalación).

## Seguridad

Ver el código de `src/security.php` y `src/auth.php`. Resumen: contraseñas **argon2id**, PDO
**preparado**, **CSRF** en toda mutación, sesión `secure`+`httponly`+`samesite`, **rate-limit**
por IP, cabeceras **CSP/HSTS**, `audit_log`, manejador global de errores y correo por API de SES
con credenciales temporales del rol de instancia. Reporta problemas por *issue* (sin incluir
datos sensibles).

## Cómo se organiza una edición

Antes de montar una edición conviene leer los dos documentos de `docs/`, escritos precisamente para
no depender de quién organizó la anterior:

- **[docs/FORMATO-DISCIPLINAS.md](docs/FORMATO-DISCIPLINAS.md)** — cómo se organiza cada actividad:
  unidad de inscripción (jugador, pareja o equipo), número y tamaño de los grupos, sistema de
  competición, duración de los encuentros, instalaciones y franjas. Incluye las **reglas de
  construcción del cuadrante** (descanso mínimo entre partidos del mismo jugador, instalación de
  reserva, comprobación de solapes entre disciplinas, prioridad cuando dos actividades chocan) y
  los criterios de puntuación y fase final.
- **[docs/DECISIONES.md](docs/DECISIONES.md)** — el porqué de cada decisión y los problemas con los
  que se tropezó, empezando por el manejo de **parejas**, que fue el origen de la mayoría.
- **[docs/CIERRE-DE-EDICION.md](docs/CIERRE-DE-EDICION.md)** — al terminar la edición: cómo **apagar
  el servidor** dejando el dominio con una **página estática de temporada baja** (independiente del
  servidor) y cómo **reabrir** todo al año siguiente.

## Roadmap

Ver [ROADMAP.md](ROADMAP.md). Lo primero para la próxima edición es la **consolidación de parejas**
(que una pareja aparezca una sola vez), el **sorteo de emparejamientos** en lugar del orden
alfabético, el **control de las horas de juego** y la **introducción de resultados**. Después,
la **generación automática de cuadros con siembra**, el **pago online desde el móvil** con pasarela
y la **integración con el listado de socios** del club.

## Licencia

[MIT](LICENSE). Úsalo, adáptalo y compártelo. Si te sirve para tu club, ¡un saludo!

## Créditos

Creado por Carlos Sahuquillo para las 24 Horas de un club deportivo. Contribuciones bienvenidas.
