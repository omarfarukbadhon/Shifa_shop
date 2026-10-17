<?php
require __DIR__ . '/_layout.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $section = $_POST['section'] ?? '';

    if ($section === 'shop') {
        foreach (['store_name' => 100, 'store_tagline' => 200, 'store_tagline_bn' => 200, 'store_phone' => 30, 'store_email' => 191, 'store_address' => 300, 'facebook_url' => 300] as $k => $max) {
            save_setting($k, clean($k, $max));
        }
        save_setting('low_stock_threshold', (string)max(0, (int)($_POST['low_stock_threshold'] ?? 5)));
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['logo'];
            $isSvg = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'svg';
            try {
                if ($isSvg) {
                    // SVG logos are allowed only if they contain no scripts
                    $svg = (string)file_get_contents($f['tmp_name']);
                    if ($f['size'] > 500000 || stripos($svg, '<svg') === false || preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignObject/i', $svg)) {
                        throw new RuntimeException('This SVG file is not allowed. Please upload a PNG or JPG logo instead.');
                    }
                    @mkdir(SHIFA_ROOT . '/uploads/logo', 0755, true);
                    $path = 'uploads/logo/logo-' . bin2hex(random_bytes(4)) . '.svg';
                    file_put_contents(SHIFA_ROOT . '/' . $path, $svg);
                } else {
                    $path = save_uploaded_image($f, 'logo', 600);
                }
                delete_upload(setting('logo'));
                save_setting('logo', $path);
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        } elseif (!empty($_POST['reset_logo'])) {
            delete_upload(setting('logo'));
            save_setting('logo', '');
        }
        flash('success', 'Shop details saved.');
        redirect('admin/settings.php');
    }

    if ($section === 'email') {
        $notify = strtolower(clean('notify_email', 191));
        if ($notify !== '' && !valid_email($notify)) {
            flash('error', 'The notification email is not valid.');
            redirect('admin/settings.php#email');
        }
        save_setting('notify_email', $notify);
        save_setting('smtp_host', clean('smtp_host', 191));
        save_setting('smtp_port', (string)(int)($_POST['smtp_port'] ?? 587));
        save_setting('smtp_secure', in_array($_POST['smtp_secure'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls');
        save_setting('smtp_user', clean('smtp_user', 191));
        if (($pw = (string)($_POST['smtp_pass'] ?? '')) !== '') {
            save_setting('smtp_pass', $pw);
        }
        save_setting('mail_from_email', strtolower(clean('mail_from_email', 191)));
        save_setting('mail_from_name', clean('mail_from_name', 100));
        save_setting('status_emails', !empty($_POST['status_emails']) ? '1' : '0');
        if (($_POST['do'] ?? '') === 'test') {
            $to = setting('notify_email', $admin['email']);
            $ok = send_mail($to, 'Test email from ' . setting('store_name', 'Shifa'), email_layout('It works! 🎉', '<p style="font-size:15px;line-height:1.6">Your shop can send emails. Order notifications will arrive at <strong>' . e($to) . '</strong>, and customers will get their invoices.</p>'));
            flash($ok ? 'success' : 'error', $ok ? "Settings saved and a test email was sent to $to. Check your inbox (and spam folder)." : 'Settings saved, but the test email failed: ' . $GLOBALS['MAIL_ERROR']);
        } else {
            flash('success', 'Email settings saved.');
        }
        redirect('admin/settings.php#email');
    }

    if ($section === 'account') {
        $name = clean('name', 150);
        $email = strtolower(clean('email', 191));
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        if (!password_verify($cur, $admin['password_hash'])) {
            flash('error', 'Your current password is not correct. Nothing was changed.');
        } elseif ($name === '' || !valid_email($email)) {
            flash('error', 'Please enter your name and a valid email.');
        } elseif ($new !== '' && strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== '' && $new !== (string)($_POST['password2'] ?? '')) {
            flash('error', 'The two new passwords do not match.');
        } else {
            $upd = ['name' => $name, 'email' => $email];
            if ($new !== '') {
                $upd['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
            update('admins', $upd, 'id = ?', [$admin['id']]);
            session_regenerate_id(true);
            flash('success', 'Your admin account was updated.');
        }
        redirect('admin/settings.php#account');
    }
}

$mailLog = is_file(SHIFA_ROOT . '/data/mail.log') ? array_slice(file(SHIFA_ROOT . '/data/mail.log', FILE_IGNORE_NEW_LINES), -8) : [];

admin_header('Settings', 'settings');
?>
<nav class="sub-nav"><a href="#shop">Shop details</a><a href="#email">Email</a><a href="#account">My login</a></nav>

<form method="post" enctype="multipart/form-data" class="panel form" id="shop">
  <?= csrf_field() ?><input type="hidden" name="section" value="shop">
  <h2>Shop details</h2>
  <div class="grid2">
    <label>Shop name<input name="store_name" value="<?= e(setting('store_name', 'Shifa')) ?>" required></label>
    <label>Phone number (shown in the shop)<input name="store_phone" value="<?= e(setting('store_phone')) ?>" placeholder="01XXXXXXXXX"></label>
    <label>Tagline (English)<input name="store_tagline" value="<?= e(setting('store_tagline')) ?>"></label>
    <label>Tagline (বাংলা)<input name="store_tagline_bn" value="<?= e(setting('store_tagline_bn')) ?>"></label>
    <label>Shop email (shown in the shop)<input type="email" name="store_email" value="<?= e(setting('store_email')) ?>"></label>
    <label>Facebook page link<input type="url" name="facebook_url" value="<?= e(setting('facebook_url')) ?>" placeholder="https://facebook.com/…"></label>
  </div>
  <label>Address<input name="store_address" value="<?= e(setting('store_address')) ?>"></label>
  <div class="grid2">
    <label>Logo
      <span class="thumb-row"><img src="<?= e(logo_url()) ?>" alt="Current logo" height="44" style="width:auto"><?php if (setting('logo')): ?><label class="check"><input type="checkbox" name="reset_logo" value="1"> Go back to the default logo</label><?php endif; ?></span>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
      <small class="hint">PNG with a transparent background works best (about 340 × 96 pixels).</small>
    </label>
    <label>Low-stock warning when quantity is at or below<input type="number" name="low_stock_threshold" min="0" value="<?= e(setting('low_stock_threshold', '5')) ?>"></label>
  </div>
  <button class="btn" type="submit">Save shop details</button>
</form>

<form method="post" class="panel form" id="email">
  <?= csrf_field() ?><input type="hidden" name="section" value="email">
  <h2>Email</h2>
  <label>Send new-order notifications to *<input type="email" name="notify_email" value="<?= e(setting('notify_email')) ?>" required placeholder="you@gmail.com"></label>
  <label class="check"><input type="checkbox" name="status_emails" value="1" <?= setting('status_emails', '1') === '1' ? 'checked' : '' ?>> When I change an order's status, email the customer by default</label>

  <h3>Sending server (SMTP)</h3>
  <p class="hint">This is the account the shop uses to <strong>send</strong> emails. Easiest: a Gmail account with an <em>App password</em> (Google Account → Security → 2-Step Verification → App passwords). Or use the email account from your hosting cPanel.</p>
  <p><button type="button" class="btn btn-light btn-sm" data-preset="gmail">Fill in Gmail settings</button> <button type="button" class="btn btn-light btn-sm" data-preset="cpanel">Fill in hosting (cPanel) settings</button></p>
  <div class="grid2">
    <label>SMTP server<input name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.gmail.com"></label>
    <div class="grid2 inner">
      <label>Port<input name="smtp_port" type="number" value="<?= e(setting('smtp_port', '587')) ?>"></label>
      <label>Security<select name="smtp_secure">
        <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('smtp_secure', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <label>Username (usually the full email)<input name="smtp_user" value="<?= e(setting('smtp_user')) ?>" autocomplete="off"></label>
    <label>Password / App password<input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= setting('smtp_pass') ? '•••••••• (saved — leave empty to keep)' : '' ?>"></label>
    <label>"From" email (leave empty to use the username)<input type="email" name="mail_from_email" value="<?= e(setting('mail_from_email')) ?>"></label>
    <label>"From" name<input name="mail_from_name" value="<?= e(setting('mail_from_name', setting('store_name', 'Shifa'))) ?>"></label>
  </div>
  <div class="btn-row left">
    <button class="btn" type="submit" name="do" value="save">Save</button>
    <button class="btn btn-light" type="submit" name="do" value="test">Save &amp; send test email</button>
  </div>
  <?php if ($mailLog): ?>
    <details class="mt"><summary>Recent email log</summary><pre class="log"><?= e(implode("\n", array_reverse($mailLog))) ?></pre></details>
  <?php endif; ?>
</form>

<form method="post" class="panel form" id="account">
  <?= csrf_field() ?><input type="hidden" name="section" value="account">
  <h2>My admin login</h2>
  <div class="grid2">
    <label>Name<input name="name" value="<?= e($admin['name']) ?>" required></label>
    <label>Login email<input type="email" name="email" value="<?= e($admin['email']) ?>" required></label>
    <label>New password (leave empty to keep)<input type="password" name="password" minlength="8" autocomplete="new-password"></label>
    <label>Repeat new password<input type="password" name="password2" minlength="8" autocomplete="new-password"></label>
  </div>
  <label>Current password (needed to save) *<input type="password" name="current" required autocomplete="current-password"></label>
  <button class="btn" type="submit">Update my login</button>
</form>
<?php admin_footer(); ?>
