<?php
require __DIR__ . '/includes/bootstrap.php';

$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$reset = find_reset_token('customer', $token);
$error = '';
if ($reset && is_post()) {
    csrf_check();
    $pass = (string)($_POST['password'] ?? '');
    if (strlen($pass) < 6) {
        $error = t('err_password_len');
    } elseif ($pass !== (string)($_POST['password2'] ?? '')) {
        $error = t('err_password_match');
    } else {
        update('customers', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$reset['user_id']]);
        update('password_resets', ['used' => 1], 'id = ?', [$reset['id']]);
        login_customer((int)$reset['user_id']);
        flash('success', t('password_changed'));
        redirect('account.php');
    }
}

$pageTitle = t('reset_password');
require __DIR__ . '/includes/header.php';
?>
<div class="container section auth-wrap">
  <div class="card auth-card">
    <h1><?= e(t('reset_password')) ?></h1>
    <?php if (!$reset): ?>
      <div class="alert alert-error"><?= e(t('reset_invalid')) ?></div>
      <p class="center"><a href="<?= e(url('forgot.php')) ?>"><?= e(t('send_reset_link')) ?></a></p>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label><?= e(t('new_password')) ?><input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
        <label><?= e(t('repeat_password')) ?><input type="password" name="password2" minlength="6" required autocomplete="new-password"></label>
        <button class="btn btn-block" type="submit"><?= e(t('save_password')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
