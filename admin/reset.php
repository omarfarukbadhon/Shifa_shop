<?php
require __DIR__ . '/_layout.php';

$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$reset = find_reset_token('admin', $token);
$error = '';
if ($reset && is_post()) {
    csrf_check();
    $pass = (string)($_POST['password'] ?? '');
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== (string)($_POST['password2'] ?? '')) {
        $error = 'The two passwords do not match.';
    } else {
        update('admins', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$reset['user_id']]);
        update('password_resets', ['used' => 1], 'id = ?', [$reset['id']]);
        flash('success', 'Password changed. Please log in.');
        redirect('admin/login.php');
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Reset password</title><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>"></head>
<body class="install"><main class="install-box narrow">
  <img src="<?= e(logo_url()) ?>" alt="" class="install-logo">
  <h1>Set a new admin password</h1>
  <?php if (!$reset): ?>
    <div class="alert alert-error">This link is invalid or has expired.</div>
    <p class="center"><a href="login.php?mode=forgot">Request a new link</a></p>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <label>New password (8+ characters)<input type="password" name="password" minlength="8" required></label>
      <label>Repeat password<input type="password" name="password2" minlength="8" required></label>
      <button class="btn btn-block" type="submit">Save password</button>
    </form>
  <?php endif; ?>
</main></body></html>
