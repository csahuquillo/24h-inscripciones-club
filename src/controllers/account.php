<?php
declare(strict_types=1);

function ctrl_account(): void {
    $u = require_login();
    $pdo = db();

    // Participantes + inscripciones
    $parts = $pdo->prepare('SELECT * FROM participante WHERE cuenta_id = ? ORDER BY id');
    $parts->execute([$u['id']]);
    $partList = $parts->fetchAll();

    $insByPart = [];
    if ($partList) {
        $ids = array_column($partList, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT i.*, d.nombre AS disc, d.tipo FROM inscripcion i
            JOIN disciplina d ON d.id = i.disciplina_id WHERE i.participante_id IN ($ph) ORDER BY d.tipo DESC, d.nombre");
        $st->execute($ids);
        foreach ($st->fetchAll() as $r) $insByPart[$r['participante_id']][] = $r;
    }

    $partHtml = '';
    foreach ($partList as $p) {
        $rows = '';
        foreach ($insByPart[$p['id']] ?? [] as $i) {
            $badge = estado_badge($i['estado']);
            $seed = (int)$i['cabeza_serie'] === 1 ? ' <span class="tag seed">cabeza de serie</span>' : '';
            $niv = $i['nivel_padel'] ? ' (nivel ' . e((string)$i['nivel_padel']) . ')' : '';
            $rows .= '<tr><td>' . e($i['disc']) . $niv . $seed . '</td><td>' . e($i['tipo'])
                   . '</td><td>' . number_format((float)$i['precio_eur'], 2, ',', '.') . ' €</td><td>' . $badge . '</td></tr>';
        }
        if ($rows === '') continue;
        $socio = (int)$p['es_socio'] === 1 ? 'socio/a' : 'no socio';
        $partHtml .= '<article class="panel"><h3>' . e($p['nombre_completo']) . ' <span class="muted">(' . $socio . ')</span></h3>'
            . '<table class="grid"><thead><tr><th>Actividad</th><th>Tipo</th><th>Precio</th><th>Estado</th></tr></thead><tbody>'
            . $rows . '</tbody></table></article>';
    }

    // Equipos de fútbol
    $eqs = $pdo->prepare('SELECT * FROM equipo WHERE cuenta_id = ?');
    $eqs->execute([$u['id']]);
    $eqHtml = '';
    foreach ($eqs->fetchAll() as $eq) {
        $mm = $pdo->prepare('SELECT * FROM equipo_miembro WHERE equipo_id = ? ORDER BY id');
        $mm->execute([$eq['id']]);
        $rows = '';
        foreach ($mm->fetchAll() as $m) {
            $rows .= '<tr><td>' . e($m['nombre_completo']) . '</td><td>' . (int)$m['edad']
                   . '</td><td>' . ((int)$m['es_socio'] === 1 ? 'socio' : 'no socio') . '</td></tr>';
        }
        $eqHtml .= '<article class="panel"><h3>Equipo: ' . e($eq['nombre_equipo']) . ' ' . estado_badge($eq['estado'])
            . '</h3><p>Total: <strong>' . number_format((float)$eq['precio_total_eur'], 2, ',', '.') . ' €</strong></p>'
            . '<table class="grid"><thead><tr><th>Jugador/a</th><th>Edad</th><th>Socio</th></tr></thead><tbody>'
            . $rows . '</tbody></table></article>';
    }

    // Listas de las categorías en las que participa (solo sus deportes)
    $listasHtml = listas_por_deporte((int)$u['id']);
    // Emparejamientos (grupos) donde participa
    $grHtml = grupos_de_cuenta((int)$u['id']);
    // Horario de sus partidos (si el responsable ha publicado el cuadro)
    $partidosHtml = partidos_de_cuenta((int)$u['id']);
    // Horarios próximos
    $hoHtml = horarios_html();

    $club = e((string)cfg('CLUB_EMAIL', ''));
    $listasIntro = '<p class="muted">Estas son todas las parejas y participantes de las categorías en las que estás inscrito/a. '
        . 'Tu inscripción aparece resaltada. Si ves algún error'
        . ($club ? ', escríbenos a <a href="mailto:' . $club . '">' . $club . '</a>' : ', avísanos') . ' y lo corregimos.</p>';

    $nombre = e($u['nombre_completo']);
    $c = "<h1>Hola, $nombre</h1>"
       . '<p><a href="/mi-cuenta/password">Cambiar contraseña</a></p>'
       . ($partHtml ?: '')
       . ($eqHtml ?: '')
       . ($partHtml || $eqHtml ? '' : '<p>No hay inscripciones asociadas a tu cuenta.</p>')
       . ($grHtml ? "<h2>Tus emparejamientos (grupos)</h2><p class=\"muted\">Estos son los grupos que te han tocado. Cada grupo organiza sus partidas como quiera dentro del horario de la disciplina. Usa el botón <strong>💬 WhatsApp</strong> de cada pareja para poneros de acuerdo.</p>$grHtml" : '')
       . ($partidosHtml ? "<h2>Tus partidos (horario)</h2><p class=\"muted\">Horario provisional publicado por la organización (hora, pista y rival). Puede haber ajustes por lluvia.</p>$partidosHtml" : '')
       . ($listasHtml ? "<h2>Todas las parejas de tus deportes</h2>$listasIntro$listasHtml" : '')
       . ($hoHtml ? "<h2>Horarios</h2>$hoHtml" : '');
    render('Mi cuenta', $c, $u, 'noindex, nofollow');
}

/**
 * Listas por deporte: para cada (disciplina, ámbito) en que participa la cuenta,
 * muestra todas las parejas/participantes de esa categoría (nombres, sin datos de
 * contacto), deduplicando parejas y resaltando la inscripción propia.
 */
function listas_por_deporte(int $cuentaId): string {
    $pdo = db();
    // Categorías (disciplina + ámbito) del usuario, con inscripción activa
    $st = $pdo->prepare("SELECT DISTINCT i.disciplina_id, d.nombre AS disc, p.ambito
        FROM participante p
        JOIN inscripcion i ON i.participante_id = p.id
        JOIN disciplina d ON d.id = i.disciplina_id
        WHERE p.cuenta_id = ? AND i.estado NOT IN ('anulada','anulado')
        ORDER BY d.nombre, p.ambito");
    $st->execute([$cuentaId]);
    $cats = $st->fetchAll();
    if (!$cats) return '';

    // Nombres propios de la cuenta (para resaltar)
    $mn = $pdo->prepare('SELECT nombre_completo FROM participante WHERE cuenta_id = ?');
    $mn->execute([$cuentaId]);
    $mine = [];
    foreach ($mn->fetchAll() as $m) $mine[canon_nombre($m['nombre_completo'])] = true;

    $out = '';
    $q = $pdo->prepare("SELECT p.nombre_completo AS nom, COALESCE(i.companero,'') AS comp
        FROM inscripcion i JOIN participante p ON p.id = i.participante_id
        WHERE i.disciplina_id = ? AND p.ambito = ? AND i.estado NOT IN ('anulada','anulado')
        ORDER BY p.nombre_completo");
    foreach ($cats as $cat) {
        $q->execute([$cat['disciplina_id'], $cat['ambito']]);
        $seen = [];
        $items = [];
        foreach ($q->fetchAll() as $r) {
            $nom = trim((string)$r['nom']);
            $comp = trim((string)$r['comp']);
            $names = [canon_nombre($nom)];
            if ($comp !== '') $names[] = canon_nombre($comp);
            sort($names);
            $key = implode('|', $names);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $isMe = isset($mine[canon_nombre($nom)]) || ($comp !== '' && isset($mine[canon_nombre($comp)]));
            $label = e($nom) . ($comp !== '' ? ' / ' . e($comp) : '');
            $items[] = ['label' => $label, 'me' => $isMe, 'sort' => mb_strtolower($nom)];
        }
        if (!$items) continue;
        usort($items, fn($a, $b) => strcmp($a['sort'], $b['sort']));
        $lis = '';
        foreach ($items as $it) {
            $lis .= '<li' . ($it['me'] ? ' class="me"' : '') . '>' . $it['label']
                  . ($it['me'] ? ' <span class="tag">tú</span>' : '') . '</li>';
        }
        $n = count($items);
        $out .= '<article class="panel"><h3>' . e(cat_label($cat['disc'], $cat['ambito']))
              . ' <span class="muted">(' . $n . ')</span></h3><ol class="listas">' . $lis . '</ol></article>';
    }
    return $out;
}

function canon_nombre(?string $s): string {
    $s = mb_strtolower(trim((string)$s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ï'=>'i','ç'=>'c']);
    return preg_replace('/\s+/', ' ', $s);
}

/** Horario de partidos del usuario (tabla `partido`, poblada desde el cuadro del responsable). */
function partidos_de_cuenta(int $cuentaId): string {
    $pdo = db();
    $mn = $pdo->prepare('SELECT nombre_completo FROM participante WHERE cuenta_id = ?');
    $mn->execute([$cuentaId]);
    $mine = [];
    foreach ($mn->fetchAll() as $m) { $c = canon_nombre($m['nombre_completo']); if ($c !== '') $mine[] = $c; }
    if (!$mine) return '';
    $st = $pdo->query("SELECT pt.franja, pt.pista, pt.p1, pt.p2, pt.orden, d.nombre AS disc, d.ambito
        FROM partido pt JOIN disciplina d ON d.id = pt.disciplina_id ORDER BY pt.orden, pt.pista");
    $rows = '';
    foreach ($st->fetchAll() as $r) {
        $c1 = canon_nombre($r['p1']); $c2 = canon_nombre($r['p2']);
        $in1 = false; $in2 = false;
        foreach ($mine as $nm) { if (mb_strpos($c1, $nm) !== false) $in1 = true; if (mb_strpos($c2, $nm) !== false) $in2 = true; }
        if (!$in1 && !$in2) continue;
        $rival = $in1 ? $r['p2'] : $r['p1'];
        $rows .= '<tr><td>' . e($r['franja']) . '</td><td>Pista ' . (int)$r['pista'] . '</td><td>' . e($rival)
               . '</td><td class="muted">' . e(cat_label($r['disc'], $r['ambito'])) . '</td></tr>';
    }
    if ($rows === '') return '';
    return '<table class="grid"><thead><tr><th>Hora</th><th>Pista</th><th>Rival</th><th>Categoría</th></tr></thead><tbody>'
         . $rows . '</tbody></table>';
}

/** Normaliza un teléfono a formato wa.me (solo dígitos, prefijo país). '' si no válido. */
function wa_phone(?string $tel): string {
    $d = preg_replace('/\D+/', '', (string)$tel);
    if ($d === '') return '';
    if (strpos($d, '00') === 0) $d = substr($d, 2);      // 0034... -> 34...
    if (strlen($d) === 9) $d = '34' . $d;                // móvil español sin prefijo
    return (strlen($d) >= 11 && strlen($d) <= 15) ? $d : '';
}

function cat_label(string $disc, string $ambito): string {
    if ($disc === 'Pádel' && $ambito === 'infantil') return 'Pádel infantil';
    if ($disc === 'Pádel' && $ambito === 'adulto')   return 'Pádel −4 (adultos)';
    if ($disc === 'Pádel+4')                          return 'Pádel +4 (adultos)';
    if ($disc === 'Tenis doble')                      return 'Tenis dobles';
    $suf = $ambito === 'infantil' ? ' (infantil)' : '';
    return $disc . $suf;
}

function estado_badge(string $estado): string {
    $map = [
        'preinscrita' => ['Pendiente de pago', 'warn'],
        'preinscrito' => ['Pendiente de pago', 'warn'],
        'pagada'      => ['Pagada', 'ok'],
        'pagado'      => ['Pagado', 'ok'],
        'anulada'     => ['Anulada', 'muted'],
        'anulado'     => ['Anulado', 'muted'],
    ];
    [$txt, $cls] = $map[$estado] ?? [$estado, 'muted'];
    return '<span class="badge ' . $cls . '">' . e($txt) . '</span>';
}

function grupos_de_cuenta(int $cuentaId): string {
    $pdo = db();
    // grupos (emparejamientos) donde participa la cuenta
    $st = $pdo->prepare('SELECT DISTINCT g.id, g.etiqueta, d.nombre AS disc, d.ambito
        FROM grupo g JOIN disciplina d ON d.id = g.disciplina_id
        JOIN grupo_miembro gm ON gm.grupo_id = g.id
        LEFT JOIN inscripcion i ON i.id = gm.inscripcion_id
        LEFT JOIN participante p ON p.id = i.participante_id
        LEFT JOIN equipo e ON e.id = gm.equipo_id
        WHERE p.cuenta_id = ? OR e.cuenta_id = ?
        ORDER BY d.nombre, g.etiqueta');
    $st->execute([$cuentaId, $cuentaId]);
    $groups = $st->fetchAll();
    if (!$groups) return '';
    // nombres propios (para resaltar la pareja del usuario)
    $mn = $pdo->prepare('SELECT nombre_completo FROM participante WHERE cuenta_id = ?');
    $mn->execute([$cuentaId]);
    $mine = [];
    foreach ($mn->fetchAll() as $m) $mine[canon_nombre($m['nombre_completo'])] = true;

    $mem = $pdo->prepare("SELECT p.nombre_completo AS nom, COALESCE(i.companero,'') AS comp, c.telefono AS tel
        FROM grupo_miembro gm JOIN inscripcion i ON i.id = gm.inscripcion_id
        JOIN participante p ON p.id = i.participante_id
        JOIN cuenta c ON c.id = p.cuenta_id
        WHERE gm.grupo_id = ? ORDER BY p.nombre_completo");
    $out = '';
    foreach ($groups as $g) {
        $mem->execute([$g['id']]);
        $seen = [];
        $items = [];
        foreach ($mem->fetchAll() as $r) {
            $nom = trim((string)$r['nom']);
            $comp = trim((string)$r['comp']);
            $names = [canon_nombre($nom)];
            if ($comp !== '') $names[] = canon_nombre($comp);
            sort($names);
            $key = implode('|', $names);
            if (isset($seen[$key])) continue;   // pareja ya listada por el otro miembro
            $seen[$key] = true;
            $isMe = isset($mine[canon_nombre($nom)]) || ($comp !== '' && isset($mine[canon_nombre($comp)]));
            $items[] = ['label' => e($nom) . ($comp !== '' ? ' / ' . e($comp) : ''), 'me' => $isMe,
                        'tel' => wa_phone($r['tel']), 'sort' => mb_strtolower($nom)];
        }
        usort($items, fn($a, $b) => strcmp($a['sort'], $b['sort']));
        $lis = '';
        foreach ($items as $it) {
            $wa = (!$it['me'] && $it['tel'] !== '')
                ? ' <a class="wa" href="https://wa.me/' . e($it['tel']) . '" target="_blank" rel="noopener">💬 WhatsApp</a>'
                : '';
            $lis .= '<li' . ($it['me'] ? ' class="me"' : '') . '>' . $it['label']
                  . ($it['me'] ? ' <span class="tag">tú</span>' : $wa) . '</li>';
        }
        $out .= '<article class="panel"><h3>' . e(cat_label($g['disc'], $g['ambito'])) . ' · Grupo ' . e($g['etiqueta'])
              . ' <span class="muted">(' . count($items) . ' parejas)</span></h3><ol class="listas">' . $lis . '</ol></article>';
    }
    return $out;
}

function horarios_html(): string {
    $st = db()->query('SELECT h.*, d.nombre AS disc FROM horario h LEFT JOIN disciplina d ON d.id = h.disciplina_id
        WHERE h.cuando >= NOW() - INTERVAL 1 DAY ORDER BY h.cuando LIMIT 100');
    $rows = '';
    foreach ($st->fetchAll() as $h) {
        $rows .= '<tr><td>' . e(date('d/m H:i', strtotime($h['cuando']))) . '</td><td>' . e($h['disc'] ?? '')
               . '</td><td>' . e($h['lugar'] ?? '') . '</td><td>' . e($h['nota'] ?? '') . '</td></tr>';
    }
    return $rows ? '<table class="grid"><thead><tr><th>Cuándo</th><th>Actividad</th><th>Lugar</th><th>Nota</th></tr></thead><tbody>' . $rows . '</tbody></table>' : '';
}
