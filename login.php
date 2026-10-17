<?php
require __DIR__ . '/includes/bootstrap.php';

$return = safe_return($_GET['r'] ?? ($_POST['r'] ?? ''), 'account.php');
if (current_customer()) {
    redirect($return);
}

$error = '';
$email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(clean('email', 191));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts('customer_login')) {
        $error = t('too_many_attempts');
    } else {
        $c = row('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($c && password_verify($pass, $c['password_hash'])) {
            clear_attempts('customer_login');
            if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
                update('customers', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$c['id']]);
            }
            login_customer((int)$c['id']);
            flash('success', t('welcome_back', ['name' => explode(' ', $c['name'])[0]]));
            redirect($return);
        }
        record_attempt('customer_login');
        $error = t('wrong_login');
    }
}

$pageTitle = t('login');
require __DIR__ . '/includes/header.php';
?>
<div class="container section auth-wrap">
  <div class="card auth-card">
    <h1><?= e(t('login')) ?></h1>
    <p class="muted"><?= e(t('login_intro')) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="r" value="<?= e($return) ?>">
      <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
      <label><?= e(t('password')) ?><input type="password" name="password" required autocomplete="current-password"></label>
      <div class="form-row-between">
        <a href="<?= e(url('forgot.php')) ?>"><?= e(t('forgot_password')) ?></a>
      </div>
      <button class="btn btn-block" type="submit"><?= e(t('login')) ?></button>
    </form>
    <p class="center"><?= e(t('no_account')) ?> <a href="<?= e(url('register.php?r=' . urlencode($return))) ?>"><?= e(t('create_account')) ?></a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
