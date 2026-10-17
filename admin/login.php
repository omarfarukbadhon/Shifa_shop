<?php
require __DIR__ . '/_layout.php';

if (current_admin()) {
    redirect('admin/index.php');
}
$error = '';
$email = '';
$mode = $_GET['mode'] ?? 'login';
$notice = '';

if (is_post()) {
    csrf_check();
    if (($_POST['mode'] ?? '') === 'forgot') {
        $mode = 'forgot';
        $email = strtolower(clean('email', 191));
        if (!too_many_attempts('admin_reset', 5, 30)) {
            record_attempt('admin_reset');
            $a = row('SELECT * FROM admins WHERE email = ?', [$email]);
            if ($a) {
                $token = create_reset_token('admin', (int)$a['id']);
                send_reset_email($a['email'], $a['name'], full_url('admin/reset.php?token=' . $token), 'en');
            }
        }
        $notice = 'If that email belongs to an admin, a reset link has been sent.';
    } else {
        $email = strtolower(clean('email', 191));
        if (too_many_attempts('admin_login', 6, 15)) {
            $error = 'Too many attempts. Please wait 15 minutes.';
        } else {
            $a = row('SELECT * FROM admins WHERE email = ?', [$email]);
            if ($a && password_verify((string)($_POST['password'] ?? ''), $a['password_hash'])) {
                clear_attempts('admin_login');
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$a['id'];
                redirect('admin/index.php');
            }
            record_attempt('admin_login');
            $error = 'Wrong email or password.';
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin login · <?= e(setting('store_name', 'Shifa')) ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=<?= SHIFA_VERSION ?>">
</head>
<body class="install">
<main class="install-box narrow">
  <img src="<?= e(logo_url()) ?>" alt="<?= e(setting('store_name', 'Shifa')) ?>" class="install-logo">
  <?= render_flash() ?>
  <?php if ($mode === 'forgot'): ?>
    <h1>Reset admin password</h1>
    <?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="mode" value="forgot">
      <label>Admin email<input type="email" name="email" value="<?= e($email) ?>" required></label>
      <button class="btn btn-block" type="submit">Send reset link</button>
    </form>
    <p class="center"><a href="login.php">Back to login</a></p>
  <?php else: ?>
    <h1>Admin login</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-block" type="submit">Log in</button>
    </form>
    <p class="center"><a href="login.php?mode=forgot">Forgot password?</a></p>
  <?php endif; ?>
</main>
</body>
</html>
