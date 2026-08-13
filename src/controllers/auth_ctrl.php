<?php
declare(strict_types=1);

function ctrl_login(): void {
    if (current_user()) redirect('/mi-cuenta');
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        if (!rate_limit('login', 30, 900)) {
            $error = 'Demasiados intentos. Espera unos minutos.';
        } else {
            $email = (string)($_POST['email'] ?? '');
            $pass  = (string)($_POST['password'] ?? '');
            $u = attempt_login($email, $pass);
            if ($u) {
                login_session((int)$u['id']);
                redirect(in_array($u['rol'], ['staff', 'admin'], true) ? '/admin' : '/mi-cuenta');
            }
            $error = 'Email o contraseña incorrectos.';
        }
    }
    $err = $error ? '<div class="errors">' . e($error) . '</div>' : '';
    $csrf = csrf_field();
    $c = <<<HTML
<h1>Acceder</h1>
<p class="muted">El acceso se activa cuando el club confirma tu pago. Recibirás la contraseña por email.</p>
$err
<form method="post" action="/login" class="form narrow" novalidate>
  $csrf
  <label>Email o usuario <input type="text" name="email" required autocomplete="username"></label>
  <label>Contraseña <input type="password" name="password" required autocomplete="current-password"></label>
  <button class="primary" type="submit">Entrar</button>
</form>
HTML;
    render('Acceder', $c, null, 'noindex, nofollow');
}

function ctrl_logout(): void {
    csrf_check();
    logout();
    redirect('/');
}
