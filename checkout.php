<?php
require __DIR__ . '/includes/bootstrap.php';

$customer = require_customer('checkout.php');
$lines = cart_lines($changed);
if (!$lines) {
    flash('info', t('cart_empty'));
    redirect('cart.php');
}

$errors = [];
$f = [
    'name' => $customer['name'],
    'phone' => $customer['phone'],
    'email' => $customer['email'],
    'address' => (string)$customer['address'],
    'city' => (string)$customer['city'],
    'zone' => $customer['zone'] ?: 'inside',
    'note' => '',
    'save' => true,
];

if (is_post()) {
    csrf_check();
    $f = [
        'name' => clean('name', 150),
        'phone' => clean('phone', 30),
        'email' => strtolower(clean('email', 191)),
        'address' => clean('address', 500),
        'city' => clean('city', 100),
        'zone' => ($_POST['zone'] ?? '') === 'outside' ? 'outside' : 'inside',
        'note' => clean('note', 1000),
        'save' => !empty($_POST['save']),
    ];
    $phone = normalize_phone($f['phone']);
    if ($f['name'] === '') {
        $errors[] = t('err_name');
    }
    if ($phone === '') {
        $errors[] = t('err_phone');
    }
    if (!valid_email($f['email'])) {
        $errors[] = t('err_email');
    }
    if (mb_strlen($f['address']) < 5) {
        $errors[] = t('err_address');
    }
    if ($f['city'] === '') {
        $errors[] = t('err_city');
    }
    $token = (string)($_POST['checkout_token'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['checkout_token'] ?? '', $token)) {
        // Probably a double click — the first submit already placed the order.
        if (!empty($_SESSION['last_order_serial'])) {
            redirect('order-success.php?serial=' . urlencode($_SESSION['last_order_serial']));
        }
        $errors[] = t('something_wrong');
    }
    if ($changed) {
        $errors[] = t('cart_adjusted');
    }

    if (!$errors) {
        unset($_SESSION['checkout_token']);
        $totals = cart_totals($lines);
        $delivery = delivery_charge($f['zone'], $totals['weight'], $totals['subtotal']);
        $pdo = db();
        $orderId = 0;
        $serial = '';
        try {
            $pdo->beginTransaction();
            foreach ($lines as $l) {
                $ok = q('UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?', [$l['qty'], $l['product']['id'], $l['qty']])->rowCount();
                if (!$ok) {
                    throw new DomainException(t('err_stock_changed', ['name' => loc($l['product'], 'name')]));
                }
            }
            $now = now();
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $serial = generate_serial();
                try {
                    $orderId = insert('orders', [
                        'serial' => $serial,
                        'customer_id' => $customer['id'],
                        'name' => $f['name'],
                        'phone' => $phone,
                        'email' => $f['email'],
                        'address' => $f['address'],
                        'city' => $f['city'],
                        'zone' => $f['zone'],
                        'note' => $f['note'],
                        'subtotal' => round($totals['subtotal'], 2),
                        'delivery_charge' => round($delivery, 2),
                        'total' => round($totals['subtotal'] + $delivery, 2),
                        'total_weight' => round($totals['weight'], 3),
                        'payment_method' => 'cod',
                        'payment_status' => 'unpaid',
                        'status' => 'pending',
                        'lang' => lang(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    break;
                } catch (PDOException $ex) {
                    if ($attempt === 4 || stripos($ex->getMessage(), 'unique') === false && stripos($ex->getMessage(), 'duplicate') === false) {
                        throw $ex;
                    }
                }
            }
            foreach ($lines as $l) {
                $p = $l['product'];
                insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $p['id'],
                    'name' => $p['name'] . ($p['name_bn'] && lang() === 'bn' ? ' (' . $p['name_bn'] . ')' : ''),
                    'price' => $l['price'],
                    'quantity' => $l['qty'],
                    'weight' => (float)$p['weight'],
                    'line_total' => round($l['line_total'], 2),
                ]);
                log_stock((int)$p['id'], -$l['qty'], (int)$p['quantity'] - $l['qty'], 'Order ' . $serial);
            }
            insert('order_status_history', ['order_id' => $orderId, 'status' => 'pending', 'note' => 'Order placed', 'created_at' => $now]);

            $profile = ['last_order_at' => $now];
            if ($f['save']) {
                $profile += ['name' => $f['name'], 'phone' => $phone, 'address' => $f['address'], 'city' => $f['city'], 'zone' => $f['zone']];
            }
            update('customers', $profile, 'id = ?', [$customer['id']]);
            $pdo->commit();
        } catch (DomainException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout failed: ' . $ex->getMessage());
            $errors[] = t('something_wrong');
        }

        if (!$errors) {
            cart_clear();
            $_SESSION['last_order_serial'] = $serial;
            // Emails go out after the order is safely saved. A mail problem never loses an order.
            send_order_emails($orderId);
            redirect('order-success.php?serial=' . urlencode($serial));
        }
        $lines = cart_lines($changed);
    }
}

$_SESSION['checkout_token'] = bin2hex(random_bytes(16));
$totals = cart_totals($lines);
$delivery = delivery_charge($f['zone'], $totals['weight'], $totals['subtotal']);

$pageTitle = t('checkout');
$extraScripts = '<script>window.SHIFA_DELIVERY = ' . json_encode(delivery_js_data()) . ';</script>';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e(t('checkout')) ?></h1>
  <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
  <form method="post" class="checkout-layout" id="checkoutForm" data-subtotal="<?= e((string)$totals['subtotal']) ?>" data-weight="<?= e((string)$totals['weight']) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_token" value="<?= e($_SESSION['checkout_token']) ?>">
    <div class="card checkout-form form">
      <h2><?= e(t('delivery_details')) ?></h2>
      <div class="grid2">
        <label><?= e(t('full_name')) ?> *<input name="name" value="<?= e($f['name']) ?>" required autocomplete="name"></label>
        <label><?= e(t('phone')) ?> *<input type="tel" name="phone" value="<?= e($f['phone']) ?>" required placeholder="01XXXXXXXXX" autocomplete="tel" inputmode="tel"></label>
      </div>
      <label><?= e(t('email')) ?> *<input type="email" name="email" value="<?= e($f['email']) ?>" required autocomplete="email">
        <small class="muted"><?= e(t('invoice_sent_here')) ?></small></label>
      <label><?= e(t('full_address')) ?> *<textarea name="address" rows="3" required autocomplete="street-address" placeholder="<?= e(t('address_placeholder')) ?>"><?= e($f['address']) ?></textarea></label>
      <div class="grid2">
        <label><?= e(t('city_district')) ?> *<input name="city" value="<?= e($f['city']) ?>" required autocomplete="address-level2"></label>
        <fieldset class="zone-pick">
          <legend><?= e(t('delivery_area')) ?> *</legend>
          <?php foreach (delivery_zones() as $k => $label): ?>
            <label class="radio-card"><input type="radio" name="zone" value="<?= $k ?>" <?= $f['zone'] === $k ? 'checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </fieldset>
      </div>
      <label><?= e(t('order_note')) ?> <span class="muted">(<?= e(t('optional')) ?>)</span><textarea name="note" rows="2" placeholder="<?= e(t('note_placeholder')) ?>"><?= e($f['note']) ?></textarea></label>
      <label class="check"><input type="checkbox" name="save" value="1" <?= $f['save'] ? 'checked' : '' ?>> <?= e(t('save_details')) ?></label>

      <h2><?= e(t('payment_method')) ?></h2>
      <label class="radio-card selected"><input type="radio" name="payment" value="cod" checked> <strong><?= e(t('pay_cod')) ?></strong> — <span class="muted"><?= e(t('cod_desc')) ?></span></label>
    </div>

    <aside class="card summary">
      <h2><?= e(t('order_summary')) ?></h2>
      <ul class="mini-lines">
        <?php foreach ($lines as $l): ?>
          <li><span><?= e(loc($l['product'], 'name')) ?> × <?= e(num($l['qty'])) ?></span><span><?= e(money($l['line_total'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row"><span><?= e(t('subtotal')) ?></span><span><?= e(money($totals['subtotal'])) ?></span></div>
      <div class="sum-row"><span><?= e(t('delivery_charge')) ?> <small class="muted">(<?= e(num($totals['weight'], 2)) ?> <?= e(t('kg')) ?>)</small></span><span data-delivery><?= e(money($delivery)) ?></span></div>
      <div class="sum-row total"><span><?= e(t('grand_total')) ?></span><strong data-total><?= e(money($totals['subtotal'] + $delivery)) ?></strong></div>
      <button class="btn btn-block btn-lg" type="submit" data-once><?= e(t('place_order')) ?></button>
      <p class="small muted center"><?= e(t('place_order_note')) ?></p>
      <a class="small center block" href="<?= e(url('cart.php')) ?>">← <?= e(t('edit_cart')) ?></a>
    </aside>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
