# Cierre de una edición: apagar el servidor y dejar una página de temporada baja

Guía operativa para **cerrar la web al terminar una edición**: la app deja de recibir
inscripciones, el servidor se apaga la mayor parte del año (para no pagarlo en vano) y el
dominio pasa a mostrar una **página estática de "temporada baja"** que no depende del servidor.
Y, al año siguiente, cómo **volver a encender** todo.

Esto complementa la sección *"La web después del evento (ciclo de vida de la edición)"* de
[DECISIONES.md](DECISIONES.md), donde está el *porqué* de cada estado.

> Esta guía es **genérica y reutilizable**. Los identificadores concretos de un despliegue real
> (ID de instancia, IP, IDs de cuenta/zona de DNS, nombres) **no van en este repositorio**:
> viven en el gestor de secretos / notas privadas de quien opera. Aquí se usan *placeholders*
> tipo `<INSTANCE_ID>`, `<ZONE_ID>`, `<DOMINIO>`.

---

## Resumen del cierre en tres pasos

1. **Cargar resultados y palmarés en la BD** (para que "Mi cuenta" los muestre) y **cambiar la
   home a "terminado" + entrega de trofeos**. Ambos son cambios de contenido, ya cubiertos por
   la app.
2. **Publicar la página estática** de temporada baja en un hosting **independiente del
   servidor** y **conmutar el dominio** hacia ella. → Núcleo de esta guía.
3. **Apagar el servidor** (parar, *no* destruir: los datos deben sobrevivir al año que viene).

El orden importa: se publica y verifica la estática **antes** de apagar, para que nunca haya un
minuto en que el dominio no muestre nada.

---

## La página estática

Requisitos:

- **Un único fichero autónomo** (`index.html`) sin dependencias externas: el CSS de la web real
  **inline** y el logo **embebido como data-URI**. Así se sirve desde cualquier sitio sin
  necesidad de `/assets` ni del servidor.
- **Reutiliza el estilo de la home real** (misma paleta, misma barra superior con logo) para que
  no desentone. Se construye a partir de `public/assets/style.css` y el logo del club.
- **`<meta name="robots" content="noindex, nofollow">`**: es una página temporal, no debe
  indexarse.
- **Sin palmarés ni nombres**: es **pública**. El palmarés y las posiciones tienen datos de
  menores y viven **solo** tras el login (área privada), nunca aquí.
- Mensaje: *"La edición XXXX ha terminado — Volvemos en <mes> de <año+1>"* + un email de
  contacto. Nada más.

### Cómo construirla

Si el servidor sigue encendido, extrae los assets reales y móntalos en un solo fichero:

```bash
# 1) CSS de producción (texto)
#    (por SSM, o scp, según cómo accedas)  ->  guarda public/assets/style.css

# 2) Logo del club  ->  optimízalo pequeño (~240px, JPEG q~84) y pásalo a data-URI
python3 - <<'PY'
import base64
css   = open("style.css", encoding="utf-8").read()
logo  = "data:image/jpeg;base64," + base64.b64encode(open("logo.jpg","rb").read()).decode()
html  = f"""<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Evento · Club</title>
<style>{css}
:root{{--logo:url("{logo}")}}
.cerrada{{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:2.4rem 1.6rem;text-align:center;margin:2.2rem 0}}
.cerrada .emblema{{width:84px;height:84px;background:var(--logo) center/contain no-repeat;margin:0 auto 1rem;border-radius:10px}}
.cerrada .pill{{display:inline-block;background:#b45309;color:#fff;border-radius:999px;padding:.6rem 1.2rem;font-weight:700;margin-top:1.3rem}}
.cerrada .anio{{color:#ffe08a;font-weight:800;text-decoration:underline}}  /* dorado sobre pastilla oscura: legible */
</style></head><body>
<header class="topbar"><div class="brand">
  <span class="logo" style="width:40px;height:38px;background:var(--logo) center/contain no-repeat #fff;border-radius:6px;padding:2px"></span>
  <span class="event">24 Horas</span></div></header>
<main class="wrap"><section class="cerrada">
  <div class="emblema"></div>
  <h1>La edición 2026 ha terminado</h1>
  <p>Gracias a todas y a todos por unas <strong>24 Horas inolvidables</strong>.</p>
  <p>La web de inscripciones descansa hasta la próxima edición.</p>
  <div class="pill">Volvemos en <span class="anio">agosto de 2027</span></div>
  <p class="note" style="margin-top:1.4rem">¿Alguna duda? Escríbenos: <a href="mailto:CONTACTO">CONTACTO</a></p>
</section></main>
<footer class="foot">Club · 24 Horas</footer></body></html>"""
open("index.html","w",encoding="utf-8").write(html)
PY
```

> **Nota sobre el dorado del año**: va sobre la pastilla ámbar **oscura**. Si lo pones sobre
> fondo claro no se lee. Es el error clásico; por eso la pastilla es de color y el año dorado.

Verifícala en local (captura headless o abriéndola en el navegador) antes de publicar.

---

## Dónde alojarla (independiente del servidor)

Dos opciones válidas; la clave es que **no dependa del servidor del evento**, que estará apagado.

### Opción A — Cloudflare Worker *(recomendada si el dominio ya está en Cloudflare)*

Es la más robusta para **verificar en el momento y apagar el servidor el mismo día**: el
certificado HTTPS lo da el **Universal SSL** del dominio al instante, no hay que esperar
aprovisionamientos, y es **100 % reversible**.

La página (HTML) se empaqueta dentro del Worker (en base64 → bytes, para servir UTF-8 intacto):

```js
// worker.mjs
const B64 = "..."; // base64 del index.html
const BYTES = Uint8Array.from(atob(B64), c => c.charCodeAt(0));
export default {
  async fetch() {
    return new Response(BYTES, { headers: {
      "content-type": "text/html; charset=utf-8",
      "cache-control": "public, max-age=300",
      "x-robots-tag": "noindex, nofollow"
    }});
  }
};
```

Despliegue y conmutación (API de Cloudflare; token con permisos *Workers Scripts: Edit* +
*DNS: Edit* + *Zone: Read*):

```bash
CF=<CLOUDFLARE_API_TOKEN>;  ACC=<ACCOUNT_ID>;  ZID=<ZONE_ID>;  NAME=<evento>-offseason

# 1) Subir el Worker (formato módulo)
curl -s -X PUT "https://api.cloudflare.com/client/v4/accounts/$ACC/workers/scripts/$NAME" \
  -H "Authorization: Bearer $CF" \
  -F 'metadata={"main_module":"worker.mjs","compatibility_date":"2024-11-01"};type=application/json' \
  -F "worker.mjs=@worker.mjs;type=application/javascript+module"

# 2) Ruta hostname -> Worker  (¡antes de tocar el DNS, para no dejar ventana de error!)
curl -s -X POST "https://api.cloudflare.com/client/v4/zones/$ZID/workers/routes" \
  -H "Authorization: Bearer $CF" -H "Content-Type: application/json" \
  --data '{"pattern":"<DOMINIO>/*","script":"'"$NAME"'"}'

# 3) Activar el proxy naranja en el registro A del hostname (mantén la IP real: revertir será trivial)
curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/$ZID/dns_records/<RECORD_ID>" \
  -H "Authorization: Bearer $CF" -H "Content-Type: application/json" \
  --data '{"proxied":true}'
```

Con el proxy activo y la ruta puesta, el Worker responde en el *edge* y **nunca** contacta con el
origen: da igual que el servidor esté apagado. Una ruta más específica (`<DOMINIO>/*`) gana a
cualquier comodín (`*.dominio/*`) que ya exista.

> **No toques los registros de correo** del hostname (MX/SPF/DMARC): solo se cambia el registro A.

### Opción B — GitHub Pages

Repo (público) con `index.html` + fichero `CNAME` con el dominio; activar Pages; y en el DNS,
`CNAME <DOMINIO> -> <usuario>.github.io`. Inconveniente: el **certificado** de GitHub para el
dominio propio **tarda** en aprovisionarse (minutos a horas), lo que complica "verificar y apagar
el mismo día". Si el dominio está en Cloudflare, la Opción A evita esa espera.

---

## Verificación (obligatoria antes de apagar)

```bash
curl -sI https://<DOMINIO>/        # 200, content-type text/html, x-robots-tag noindex
curl -s  https://<DOMINIO>/ | grep -i "ha terminado"
```

Y una captura con navegador headless para comprobar que **se ve bien** (barra, logo, pastilla
legible) en escritorio y móvil.

---

## Apagar el servidor

**Parar, no destruir.** La base de datos y la app viven en el disco; deben sobrevivir para la
edición siguiente.

```bash
# AWS EC2 (ejemplo): parar, NO terminar
aws ec2 stop-instances --instance-ids <INSTANCE_ID> --region <REGION>
```

Tras pararlo, **vuelve a verificar** `https://<DOMINIO>/`: debe seguir respondiendo 200 (lo sirve
el Worker/Pages, no el origen). Eso confirma que el cierre es correcto.

> Mantén la IP elástica / el registro A apuntando a la IP real aunque el servidor esté parado:
> así reabrir es solo encender + revertir el DNS. (Una IP elástica sin instancia corriendo puede
> tener un coste mínimo; suele compensar frente a reconfigurar.)

---

## Reabrir la edición siguiente (revertir)

En orden inverso:

```bash
# 1) Encender el servidor
aws ec2 start-instances --instance-ids <INSTANCE_ID> --region <REGION>

# 2) Devolver el hostname al origen: proxy gris (DNS-only) en el registro A
curl -s -X PATCH "https://api.cloudflare.com/client/v4/zones/$ZID/dns_records/<RECORD_ID>" \
  -H "Authorization: Bearer $CF" -H "Content-Type: application/json" --data '{"proxied":false}'

# 3) Quitar la ruta del Worker (para que deje de interceptar)
curl -s -X DELETE "https://api.cloudflare.com/client/v4/zones/$ZID/workers/routes/<ROUTE_ID>" \
  -H "Authorization: Bearer $CF"
```

El servidor recupera su propio certificado (Let's Encrypt) al arrancar. El Worker y su script
pueden quedarse guardados para el año siguiente (o borrarse; el `index.html` se puede reconstruir
con el bloque de arriba).

---

## Checklist

- [ ] Resultados/palmarés cargados en BD; home en modo "terminado" + trofeos.
- [ ] `index.html` autónomo construido (CSS + logo embebidos, `noindex`, sin nombres/palmarés).
- [ ] Estática publicada en hosting independiente del servidor.
- [ ] DNS conmutado; **registros de correo intactos**.
- [ ] `https://<DOMINIO>/` verificado (curl + captura) **con el servidor aún encendido**.
- [ ] Servidor **parado** (no destruido).
- [ ] `https://<DOMINIO>/` verificado **de nuevo** con el servidor ya apagado.
- [ ] Identificadores reales (instancia, IPs, IDs de cuenta/zona/ruta/registro) anotados en las
      notas privadas del proyecto para poder revertir.
