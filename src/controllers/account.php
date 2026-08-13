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

    // Emparejamientos (grupos) donde participa
    $grHtml = grupos_de_cuenta((int)$u['id']);
    // Horarios próximos
    $hoHtml = horarios_html();

    $nombre = e($u['nombre_completo']);
    $c = "<h1>Hola, $nombre</h1>"
       . '<p><a href="/mi-cuenta/password">Cambiar contraseña</a></p>'
       . ($partHtml ?: '')
       . ($eqHtml ?: '')
       . ($partHtml || $eqHtml ? '' : '<p>No hay inscripciones asociadas a tu cuenta.</p>')
       . ($grHtml ? "<h2>Emparejamientos</h2>$grHtml" : '')
       . ($hoHtml ? "<h2>Horarios</h2>$hoHtml" : '');
    render('Mi cuenta', $c, $u, 'noindex, nofollow');
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
    $st = $pdo->prepare('SELECT DISTINCT g.id, g.etiqueta, d.nombre AS disc
        FROM grupo g JOIN disciplina d ON d.id = g.disciplina_id
        JOIN grupo_miembro gm ON gm.grupo_id = g.id
        LEFT JOIN inscripcion i ON i.id = gm.inscripcion_id
        LEFT JOIN participante p ON p.id = i.participante_id
        LEFT JOIN equipo e ON e.id = gm.equipo_id
        WHERE p.cuenta_id = ? OR e.cuenta_id = ?');
    $st->execute([$cuentaId, $cuentaId]);
    $out = '';
    foreach ($st->fetchAll() as $g) {
        $out .= '<p><strong>' . e($g['disc']) . '</strong> — grupo ' . e($g['etiqueta']) . '</p>';
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
