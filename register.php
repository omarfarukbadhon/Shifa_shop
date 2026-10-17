<?php
require __DIR__ . '/includes/bootstrap.php';

$return = safe_return($_GET['r'] ?? ($_POST['r'] ?? ''), 'account.php');
if (current_customer()) {
    redirect($return);
}

$errors = [];
$f = ['name' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '', 'zone' => 'inside', 'optin' => true];
if (is_post()) {
    csrf_check();
    $f = [
        'name' => clean('name', 150),
        'email' => strtolower(clean('email', 191)),
        'phone' => clean('phone', 30),
        'address' => clean('address', 500),
        'city' => clean('city', 100),
        'zone' => ($_POST['zone'] ?? '') === 'outside' ? 'outside' : 'inside',
        'optin' => !empty($_POST['optin']),
    ];
    $pass = (string)($_POST['password'] ?? '');
    $phone = normalize_phone($f['phone']);
    if ($f['name'] === '') {
        $errors[] = t('err_name');
    }
    if (!valid_email($f['email'])) {
        $errors[] = t('err_email');
    } elseif (val('SELECT COUNT(*) FROM customers WHERE email = ?', [$f['email']])) {
        $errors[] = t('err_email_taken');
    }
    if ($phone === '') {
        $errors[] = t('err_phone');
    }
    if (strlen($pass) < 6) {
        $errors[] = t('err_password_len');
    }
    if (too_many_attempts('register', 10, 60)) {
        $errors[] = t('too_many_attempts');
    }
    if (!$errors) {
        record_attempt('register');
        $id = insert('customers', [
            'name' => $f['name'],
            'email' => $f['email'],
            'phone' => $phone,
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'address' => $f['address'],
            'city' => $f['city'],
            'zone' => $f['zone'],
            'marketing_opt_in' => $f['optin'] ? 1 : 0,
            'created_at' => now(),
        ]);
        login_customer($id);
        flash('success', t('account_created'));
        redirect($return);
    }
}

$pageTitle = t('create_account');
require __DIR__ . '/includes/header.php';
?>
<div class="container section auth-wrap">
  <div class="card auth-card wide">
    <h1><?= e(t('create_account')) ?></h1>
    <p class="muted"><?= e(t('register_intro')) ?></p>
    <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="r" value="<?= e($return) ?>">
      <div class="grid2">
        <label><?= e(t('full_name')) ?> *<input name="name" value="<?= e($f['name']) ?>" required autocomplete="name"></label>
        <label><?= e(t('phone')) ?> *<input type="tel" name="phone" value="<?= e($f['phone']) ?>" required placeholder="01XXXXXXXXX" autocomplete="tel" inputmode="tel"></label>
        <label><?= e(t('email')) ?> *<input type="email" name="email" value="<?= e($f['email']) ?>" required autocomplete="email"></label>
        <label><?= e(t('password')) ?> *<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
      </div>
      <label><?= e(t('address')) ?><textarea name="address" rows="2" autocomplete="street-address" placeholder="<?= e(t('address_placeholder')) ?>"><?= e($f['address']) ?></textarea></label>
      <div class="grid2">
        <label><?= e(t('city_district')) ?><input name="city" value="<?= e($f['city']) ?>" autocomplete="address-level2"></label>
        <label><?= e(t('delivery_area')) ?>
          <select name="zone">
            <?php foreach (delivery_zones() as $k => $label): ?><option value="<?= $k ?>" <?= $f['zone'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="check"><input type="checkbox" name="optin" value="1" <?= $f['optin'] ? 'checked' : '' ?>> <?= e(t('optin_label')) ?></label>
      <button class="btn btn-block" type="submit"><?= e(t('create_account')) ?></button>
    </form>
    <p class="center"><?= e(t('have_account')) ?> <a href="<?= e(url('login.php?r=' . urlencode($return))) ?>"><?= e(t('login')) ?></a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
