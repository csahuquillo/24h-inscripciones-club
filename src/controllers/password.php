<?php
declare(strict_types=1);

function ctrl_password(): void {
    $u = require_login();
    $err = ''; $ok = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $cur = (string)($_POST['actual'] ?? '');
        $n1  = (string)($_POST['nueva'] ?? '');
        $n2  = (string)($_POST['nueva2'] ?? '');
        $st = db()->prepare('SELECT password_hash FROM cuenta WHERE id = ?');
        $st->execute([$u['id']]);
        $hash = $st->fetchColumn();
        if (!$hash || !password_verify($cur, $hash)) {
            $err = 'La contraseña actual no es correcta.';
        } elseif (mb_strlen($n1) < 8) {
            $err = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($n1 !== $n2) {
            $err = 'La nueva contraseña y su confirmación no coinciden.';
        } else {
            db()->prepare('UPDATE cuenta SET password_hash = ? WHERE id = ?')
                ->execute([hash_password($n1), $u['id']]);
            audit('pwd_change', 'cuenta=' . $u['id']);
            $ok = 'Contraseña actualizada correctamente.';
        }
    }
    $msg = $err ? '<div class="errors">' . e($err) . '</div>' : ($ok ? '<div class="flash">' . e($ok) . '</div>' : '');
    $csrf = csrf_field();
    $c = <<<HTML
<h1>Cambiar contraseña</h1>
$msg
<form method="post" action="/mi-cuenta/password" class="form narrow" novalidate>
  $csrf
  <label>Contraseña actual <input type="password" name="actual" required autocomplete="current-password"></label>
  <label>Nueva contraseña <input type="password" name="nueva" required minlength="8" autocomplete="new-password"></label>
  <label>Repite la nueva <input type="password" name="nueva2" required minlength="8" autocomplete="new-password"></label>
  <button class="primary" type="submit">Guardar</button>
</form>
HTML;
    render('Cambiar contraseña', $c, $u, 'noindex, nofollow');
}
