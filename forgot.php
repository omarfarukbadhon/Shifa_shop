<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
if (is_post()) {
    csrf_check();
    $email = strtolower(clean('email', 191));
    if (!too_many_attempts('reset', 5, 30)) {
        record_attempt('reset');
        $c = valid_email($email) ? row('SELECT * FROM customers WHERE email = ?', [$email]) : null;
        if ($c) {
            $token = create_reset_token('customer', (int)$c['id']);
            send_reset_email($c['email'], $c['name'], full_url('reset.php?token=' . $token), lang());
        }
    }
    $sent = true; // same message either way, so nobody can find out which emails are registered
}

$pageTitle = t('forgot_password');
require __DIR__ . '/includes/header.php';
?>
<div class="container section auth-wrap">
  <div class="card auth-card">
    <h1><?= e(t('forgot_password')) ?></h1>
    <?php if ($sent): ?>
      <div class="alert alert-success"><?= e(t('reset_sent')) ?></div>
      <p class="center"><a href="<?= e(url('login.php')) ?>"><?= e(t('back_to_login')) ?></a></p>
    <?php else: ?>
      <p class="muted"><?= e(t('forgot_intro')) ?></p>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label><?= e(t('email')) ?><input type="email" name="email" required autocomplete="email"></label>
        <button class="btn btn-block" type="submit"><?= e(t('send_reset_link')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
