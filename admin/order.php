<?php
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = row('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $new = in_array($_POST['status'] ?? '', order_statuses(), true) ? $_POST['status'] : $order['status'];
        $note = clean('note', 1000);
        $notify = !empty($_POST['notify']);
        $ok = true;
        if ($new !== $order['status']) {
            if ($new === 'cancelled') {
                restock_order($id, $order['serial']);
            } elseif ($order['status'] === 'cancelled') {
                $ok = unrestock_order($id, $order['serial']);
                if (!$ok) {
                    flash('error', 'Not enough stock to re-open this order. Add stock first, then try again.');
                }
            }
        }
        if ($ok && ($new !== $order['status'] || $note !== '')) {
            $upd = ['status' => $new, 'updated_at' => now()];
            if ($new === 'delivered' && $order['payment_method'] === 'cod') {
                $upd['payment_status'] = 'paid';
            }
            update('orders', $upd, 'id = ?', [$id]);
            insert('order_status_history', ['order_id' => $id, 'status' => $new, 'note' => $note, 'created_at' => now()]);
            $msg = 'Order updated to "' . status_label($new) . '".';
            if ($notify) {
                $msg .= send_status_email($id, $note) ? ' Customer was emailed.' : ' (Email could not be sent: ' . $GLOBALS['MAIL_ERROR'] . ')';
            }
            flash('success', $msg);
        }
    } elseif ($action === 'payment') {
        update('orders', ['payment_status' => $order['payment_status'] === 'paid' ? 'unpaid' : 'paid', 'updated_at' => now()], 'id = ?', [$id]);
        flash('success', 'Payment status changed.');
    } elseif ($action === 'note') {
        update('orders', ['admin_note' => clean('admin_note', 2000), 'updated_at' => now()], 'id = ?', [$id]);
        flash('success', 'Private note saved.');
    } elseif ($action === 'details') {
        $phone = normalize_phone(clean('phone', 30));
        $email = strtolower(clean('email', 191));
        if (clean('name', 150) === '' || $phone === '' || !valid_email($email) || clean('address', 500) === '') {
            flash('error', 'Please fill name, a valid mobile number, a valid email and the address.');
        } else {
            $zone = ($_POST['zone'] ?? '') === 'outside' ? 'outside' : 'inside';
            $upd = [
                'name' => clean('name', 150), 'phone' => $phone, 'email' => $email,
                'address' => clean('address', 500), 'city' => clean('city', 100), 'zone' => $zone, 'updated_at' => now(),
            ];
            if (!empty($_POST['recalc']) && $zone !== $order['zone']) {
                $dc = delivery_charge($zone, (float)$order['total_weight'], (float)$order['subtotal']);
                $upd['delivery_charge'] = $dc;
                $upd['total'] = (float)$order['subtotal'] + $dc;
            }
            update('orders', $upd, 'id = ?', [$id]);
            flash('success', 'Delivery details updated.');
        }
    } elseif ($action === 'resend') {
        [$c, $a] = send_order_emails($id);
        flash($c ? 'success' : 'error', $c ? 'Invoice email sent again to the customer.' : 'Could not send email: ' . $GLOBALS['MAIL_ERROR']);
    }
    redirect('admin/order.php?id=' . $id);
}

$items = order_items($id);
$history = rows('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id DESC', [$id]);
$customer = row('SELECT * FROM customers WHERE id = ?', [$order['customer_id']]);
$prevOrders = $customer ? (int)val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND id <> ?', [$customer['id'], $id]) : 0;
$wa = '88' . $order['phone'];

admin_header('Order ' . $order['serial'], 'orders');
?>
<p><a href="orders.php">← All orders</a></p>
<div class="order-top">
  <div>
    <div class="serial-big"><?= e($order['serial']) ?></div>
    <div class="muted">Placed <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?> · <?= $order['lang'] === 'bn' ? 'Bangla' : 'English' ?> customer</div>
  </div>
  <div class="btn-row">
    <span class="pill pill-lg pill-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span>
    <a class="btn btn-light btn-sm" href="<?= e(url('invoice.php?serial=' . urlencode($order['serial']))) ?>" target="_blank">Print invoice</a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-light btn-sm" type="submit">Resend invoice email</button></form>
  </div>
</div>

<div class="cols">
  <div>
    <div class="panel">
      <h2>Items</h2>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?php if ($it['product_id']): ?><a href="product-edit.php?id=<?= (int)$it['product_id'] ?>"><?= e($it['name']) ?></a><?php else: ?><?= e($it['name']) ?><?php endif; ?>
                <?php if ((float)$it['weight'] > 0): ?><br><small class="muted"><?= e((float)$it['weight']) ?> kg each</small><?php endif; ?></td>
              <td class="num"><?= money($it['price'], false) ?></td>
              <td class="num"><?= (int)$it['quantity'] ?></td>
              <td class="num"><?= money($it['line_total'], false) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3" class="num">Subtotal</td><td class="num"><?= money($order['subtotal'], false) ?></td></tr>
          <tr><td colspan="3" class="num">Delivery (<?= e($order['zone'] === 'inside' ? 'Inside Dhaka' : 'Outside Dhaka') ?>, <?= e((float)$order['total_weight']) ?> kg)</td><td class="num"><?= money($order['delivery_charge'], false) ?></td></tr>
          <tr class="grand"><td colspan="3" class="num">Total to collect</td><td class="num"><?= money($order['total'], false) ?></td></tr>
        </tfoot>
      </table></div>
      <?php if ($order['note']): ?><div class="note-box"><strong>Customer note:</strong> <?= nl2br(e($order['note'])) ?></div><?php endif; ?>
    </div>

    <div class="panel">
      <h2>Update status</h2>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <div class="grid2">
          <label>New status<select name="status"><?= status_options($order['status']) ?></select></label>
          <label class="check" style="align-self:end"><input type="checkbox" name="notify" value="1" <?= setting('status_emails', '1') === '1' ? 'checked' : '' ?>> Email the customer about this update</label>
        </div>
        <label>Message for the customer (optional)<textarea name="note" rows="2" placeholder="e.g. Your parcel is with Pathao, tracking ID 12345"></textarea></label>
        <button class="btn" type="submit">Save status</button>
        <p class="hint">Cancelling an order puts its items back into stock automatically.</p>
      </form>
    </div>

    <div class="panel">
      <h2>History</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li><strong><?= e(status_label($h['status'])) ?></strong> <span class="muted"><?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?></span><?php if ($h['note']): ?><div><?= nl2br(e($h['note'])) ?></div><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2>Customer</h2>
      <p class="cust">
        <strong><?= e($order['name']) ?></strong><br>
        <a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a> · <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><br>
        <a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br>
        <?= nl2br(e($order['address'])) ?><?= $order['city'] ? ', ' . e($order['city']) : '' ?><br>
        <span class="muted"><?= $order['zone'] === 'inside' ? 'Inside Dhaka' : 'Outside Dhaka' ?></span>
      </p>
      <?php if ($customer): ?><p class="muted small"><?= $prevOrders ? $prevOrders . ' other order(s) — ' : 'First order — ' ?><a href="customers.php?q=<?= urlencode($customer['email']) ?>">view customer</a></p><?php endif; ?>
      <details>
        <summary>Edit delivery details</summary>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="details">
          <label>Name<input name="name" value="<?= e($order['name']) ?>" required></label>
          <label>Mobile<input name="phone" value="<?= e($order['phone']) ?>" required></label>
          <label>Email<input type="email" name="email" value="<?= e($order['email']) ?>" required></label>
          <label>Address<textarea name="address" rows="2" required><?= e($order['address']) ?></textarea></label>
          <label>City / District<input name="city" value="<?= e($order['city']) ?>"></label>
          <label>Area<select name="zone"><option value="inside" <?= $order['zone'] === 'inside' ? 'selected' : '' ?>>Inside Dhaka</option><option value="outside" <?= $order['zone'] === 'outside' ? 'selected' : '' ?>>Outside Dhaka</option></select></label>
          <label class="check"><input type="checkbox" name="recalc" value="1" checked> Recalculate delivery charge if area changes</label>
          <button class="btn btn-sm" type="submit">Save details</button>
        </form>
      </details>
    </div>

    <div class="panel">
      <h2>Payment</h2>
      <p>Cash on delivery · <?= $order['payment_status'] === 'paid' ? '<span class="pill pill-delivered">Paid</span>' : '<span class="pill pill-pending">Not collected yet</span>' ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><button class="btn btn-light btn-sm" type="submit">Mark as <?= $order['payment_status'] === 'paid' ? 'unpaid' : 'paid' ?></button></form>
      <p class="hint">Orders marked "Delivered" are marked paid automatically.</p>
    </div>

    <div class="panel">
      <h2>Private note</h2>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="admin_note" rows="3" placeholder="Only you can see this"><?= e($order['admin_note']) ?></textarea>
        <button class="btn btn-light btn-sm" type="submit">Save note</button>
      </form>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
