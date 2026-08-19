<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const PRICE_SOCIO = 3.00;
const PRICE_NO_SOCIO = 5.00;
const RGPD_VERSION = '2026-08-11';
const MAX_DISCIPLINAS = 3;          // 2+1
const EDAD_MAX_INFANTIL = 14;
const NIVEL_PADEL_MAX = 4;          // "Pádel" es nivel 4 o inferior; "Pádel+4" es el nivel superior

// Ventana de preinscripción (hora peninsular). Abierta ya (QR repartido).
const PREINS_OPEN  = '2026-08-12 00:00:00';
const PREINS_CLOSE = '2026-08-18 21:00:00';
const PREINS_TZ    = 'Europe/Madrid';

// Pago presencial (Notas 2026): lunes 17 y martes 18 de agosto, 18:00–21:00.
const PAGO_INFO = 'Para tener plaza hay que pagar en el club el <strong>lunes 17 y martes 18 de agosto, de 18:00 a 21:00 h</strong> (TPV preferible, o efectivo).';
const AVISO_NO_PAGO = 'La preinscripción <strong>NO será válida</strong> si no se efectúa el pago dentro del plazo. Sin pago no hay plaza.';

/** 'antes' | 'abierta' | 'cerrada' */
function preins_estado(): string {
    $tz = new DateTimeZone(PREINS_TZ);
    $now = new DateTime('now', $tz);
    if ($now < new DateTime(PREINS_OPEN, $tz))  return 'antes';
    if ($now > new DateTime(PREINS_CLOSE, $tz)) return 'cerrada';
    return 'abierta';
}

function preins_banner(): string {
    $close = (new DateTime(PREINS_CLOSE))->format('d/m/Y \a \l\a\s H:i');
    $open  = (new DateTime(PREINS_OPEN))->format('d/m/Y \a \l\a\s H:i');
    switch (preins_estado()) {
        case 'antes':
            return '<div class="banner info">🗓️ La preinscripción abre el <strong>' . e($open)
                 . '</strong> y cierra el <strong>' . e($close) . '</strong>.</div>';
        case 'cerrada':
            $mail = cfg('CLUB_EMAIL', '');
            $contacto = $mail !== '' ? ' en <a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '';
            return '<div class="banner cerrado">⛔ El plazo de preinscripción se cerró el <strong>'
                 . e($close) . '</strong>. Si tienes dudas, contacta con el club' . $contacto . '.</div>';
        default:
            return '<div class="banner abierto">✅ Preinscripción <strong>abierta</strong>. '
                 . 'Plazo hasta el <strong>' . e($close) . '</strong>.</div>';
    }
}

function price_for(bool $socio): float {
    return $socio ? PRICE_SOCIO : PRICE_NO_SOCIO;
}

/** Datos del club / responsable del tratamiento (RGPD). Se configuran en /etc/poli24h/env. */
function club(): array {
    return [
        'nombre'   => cfg('CLUB_NOMBRE', 'Tu Club Deportivo'),
        'cif'      => cfg('CLUB_CIF', ''),
        'domicilio'=> cfg('CLUB_DOMICILIO', ''),
        'email'    => cfg('CLUB_EMAIL', 'contacto@ejemplo.org'),
        'telefono' => cfg('CLUB_TELEFONO', ''),
    ];
}

/** Nombre del evento (para títulos/marca). Configurable. */
function evento(): string { return cfg('EVENTO_NOMBRE', '24 Horas'); }

/** Regla 2+1: máx 3, y no más de 2 del mismo tipo. */
function validar_combinacion(array $tipos): ?string {
    $n = count($tipos);
    if ($n === 0) return 'Selecciona al menos una actividad.';
    if ($n > MAX_DISCIPLINAS) return 'Máximo 3 actividades por persona.';
    $deportes = count(array_filter($tipos, fn($t) => $t === 'deporte'));
    $sociales = count(array_filter($tipos, fn($t) => $t === 'social'));
    if ($deportes > 2 || $sociales > 2) {
        return 'Máximo 2 deportivas + 1 social, o 2 sociales + 1 deportiva.';
    }
    return null;
}

function disciplinas(string $ambito): array {
    $st = db()->prepare('SELECT * FROM disciplina WHERE ambito = ? AND activa = 1 ORDER BY tipo DESC, nombre');
    $st->execute([$ambito]);
    return $st->fetchAll();
}

/** Normaliza un nombre para cruzar con el padrón (sin tildes, minúsculas, ordenado). */
function norm_nombre(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',','=>' ']);
    $s = preg_replace('/\s+/', ' ', $s);
    $partes = explode(' ', trim($s));
    sort($partes);
    return implode(' ', $partes);
}
