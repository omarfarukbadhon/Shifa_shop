<?php
require __DIR__ . '/includes/bootstrap.php';

$customer = require_customer();
$tab = in_array($_GET['tab'] ?? '', ['orders', 'profile', 'password'], true) ? $_GET['tab'] : 'orders';
$errors = [];

if (is_post()) {
    csrf_check();
    $form = $_POST['form'] ?? '';
    if ($form === 'profile') {
        $tab = 'profile';
        $name = clean('name', 150);
        $email = strtolower(clean('email', 191));
        $phone = normalize_phone(clean('phone', 30));
        if ($name === '') {
            $errors[] = t('err_name');
        }
        if (!valid_email($email)) {
            $errors[] = t('err_email');
        } elseif (val('SELECT COUNT(*) FROM customers WHERE email = ? AND id <> ?', [$email, $customer['id']])) {
            $errors[] = t('err_email_taken');
        }
        if ($phone === '') {
            $errors[] = t('err_phone');
        }
        if (!$errors) {
            update('customers', [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => clean('address', 500),
                'city' => clean('city', 100),
                'zone' => ($_POST['zone'] ?? '') === 'outside' ? 'outside' : 'inside',
                'marketing_opt_in' => !empty($_POST['optin']) ? 1 : 0,
            ], 'id = ?', [$customer['id']]);
            flash('success', t('profile_saved'));
            redirect('account.php?tab=profile');
        }
    } elseif ($form === 'password') {
        $tab = 'password';
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        if (!password_verify($cur, $customer['password_hash'])) {
            $errors[] = t('err_current_password');
        } elseif (strlen($new) < 6) {
            $errors[] = t('err_password_len');
        } elseif ($new !== (string)($_POST['password2'] ?? '')) {
            $errors[] = t('err_password_match');
        } else {
            update('customers', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$customer['id']]);
            session_regenerate_id(true);
            flash('success', t('password_changed'));
            redirect('account.php?tab=password');
        }
    }
}

$orders = rows('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$customer['id']]);
$pageTitle = t('my_account');
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <div class="account-head">
    <h1 class="page-title"><?= e(t('hello_name', ['name' => $customer['name']])) ?></h1>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit"><?= e(t('logout')) ?></button></form>
  </div>
  <nav class="tabs" aria-label="<?= e(t('my_account')) ?>">
    <a class="<?= $tab === 'orders' ? 'active' : '' ?>" href="?tab=orders"><?= e(t('my_orders')) ?></a>
    <a class="<?= $tab === 'profile' ? 'active' : '' ?>" href="?tab=profile"><?= e(t('my_details')) ?></a>
    <a class="<?= $tab === 'password' ? 'active' : '' ?>" href="?tab=password"><?= e(t('change_password')) ?></a>
  </nav>
  <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>

  <?php if ($tab === 'orders'): ?>
    <?php if (!$orders): ?>
      <div class="empty-state"><p><?= e(t('no_orders_yet')) ?></p><a class="btn" href="<?= e(url()) ?>"><?= e(t('start_shopping')) ?></a></div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($orders as $o): ?>
          <div class="card order-row">
            <div><div class="serial"><?= e($o['serial']) ?></div><div class="muted small"><?= e(date('d M Y', strtotime($o['created_at']))) ?></div></div>
            <span class="status-pill status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
            <strong><?= e(money($o['total'])) ?></strong>
            <div class="btn-row tight">
              <a class="btn btn-sm" href="<?= e(url('track.php?serial=' . urlencode($o['serial']))) ?>"><?= e(t('track')) ?></a>
              <a class="btn btn-sm btn-light" href="<?= e(url('invoice.php?serial=' . urlencode($o['serial']))) ?>" target="_blank"><?= e(t('invoice')) ?></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($tab === 'profile'): $c = is_post() ? array_merge($customer, $_POST) : $customer; ?>
    <form method="post" class="card form narrow-form">
      <?= csrf_field() ?><input type="hidden" name="form" value="profile">
      <p class="muted"><?= e(t('details_used_at_checkout')) ?></p>
      <div class="grid2">
        <label><?= e(t('full_name')) ?><input name="name" value="<?= e($c['name']) ?>" required></label>
        <label><?= e(t('phone')) ?><input type="tel" name="phone" value="<?= e($c['phone']) ?>" required></label>
      </div>
      <label><?= e(t('email')) ?><input type="email" name="email" value="<?= e($c['email']) ?>" required></label>
      <label><?= e(t('address')) ?><textarea name="address" rows="2"><?= e($c['address']) ?></textarea></label>
      <div class="grid2">
        <label><?= e(t('city_district')) ?><input name="city" value="<?= e($c['city']) ?>"></label>
        <label><?= e(t('delivery_area')) ?>
          <select name="zone"><?php foreach (delivery_zones() as $k => $label): ?><option value="<?= $k ?>" <?= ($c['zone'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </label>
      </div>
      <label class="check"><input type="checkbox" name="optin" value="1" <?= !empty($c['marketing_opt_in']) ? 'checked' : '' ?>> <?= e(t('optin_label')) ?></label>
      <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>

  <?php else: ?>
    <form method="post" class="card form narrow-form">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <label><?= e(t('current_password')) ?><input type="password" name="current" required autocomplete="current-password"></label>
      <label><?= e(t('new_password')) ?><input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
      <label><?= e(t('repeat_password')) ?><input type="password" name="password2" minlength="6" required autocomplete="new-password"></label>
      <button class="btn" type="submit"><?= e(t('save_password')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
