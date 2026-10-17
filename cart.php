<?php
require __DIR__ . '/includes/bootstrap.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $msg = '';
    $ok = true;
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $p = row('SELECT id, quantity FROM products WHERE id = ? AND is_active = 1', [$pid]);
        if (!$p || (int)$p['quantity'] <= 0) {
            $ok = false;
            $msg = t('out_of_stock');
        } else {
            $inCart = cart_raw()[$pid] ?? 0;
            $newQty = min($inCart + $qty, (int)$p['quantity']);
            cart_set($pid, $newQty);
            $msg = ($inCart + $qty > (int)$p['quantity']) ? t('only_n_left', ['n' => num($p['quantity'])]) : t('added_to_cart');
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok, 'count' => cart_count(), 'count_label' => num(cart_count()), 'message' => $msg]);
            exit;
        }
        if (($_POST['go'] ?? '') === 'checkout' && $ok) {
            redirect('checkout.php');
        }
        flash($ok ? 'success' : 'error', $msg);
        redirect('cart.php');
    }
    if ($action === 'update' && isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $pid => $qty) {
            cart_set((int)$pid, (int)$qty);
        }
        cart_lines($changed);
        flash('success', $changed ? t('cart_adjusted') : t('cart_updated'));
        redirect(($_POST['go'] ?? '') === 'checkout' ? 'checkout.php' : 'cart.php');
    }
    if ($action === 'remove') {
        cart_set((int)($_POST['product_id'] ?? 0), 0);
        flash('success', t('item_removed'));
        redirect('cart.php');
    }
    redirect('cart.php');
}

$lines = cart_lines($changed);
if ($changed) {
    flash('info', t('cart_adjusted'));
}
$totals = cart_totals($lines);
$pageTitle = t('cart');
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e(t('your_cart')) ?></h1>
  <?= render_flash() ?>
  <?php if (!$lines): ?>
    <div class="empty-state">
      <img src="<?= e(url('assets/img/empty-cart.svg')) ?>" alt="" width="140" height="140">
      <p><?= e(t('cart_empty')) ?></p>
      <a class="btn" href="<?= e(url()) ?>"><?= e(t('continue_shopping')) ?></a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <form method="post" class="cart-items">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <?php foreach ($lines as $l): $p = $l['product']; ?>
          <div class="cart-row">
            <a href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" class="cart-img"><img src="<?= e(img_url($p['image'])) ?>" alt="" width="80" height="80"></a>
            <div class="cart-info">
              <a class="cart-name" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>"><?= e(loc($p, 'name')) ?></a>
              <div class="muted"><?= e(money($l['price'])) ?> <?php if (on_sale($p)): ?><s><?= e(money($p['price'])) ?></s><?php endif; ?></div>
              <button class="link-btn" type="submit" form="remove-<?= (int)$p['id'] ?>"><?= e(t('remove')) ?></button>
            </div>
            <div class="qty-stepper small">
              <button type="button" data-step="-1" aria-label="−">−</button>
              <input type="number" name="qty[<?= (int)$p['id'] ?>]" value="<?= (int)$l['qty'] ?>" min="0" max="<?= (int)$p['quantity'] ?>" aria-label="<?= e(t('quantity')) ?>">
              <button type="button" data-step="1" aria-label="+">+</button>
            </div>
            <div class="cart-line-total"><?= e(money($l['line_total'])) ?></div>
          </div>
        <?php endforeach; ?>
        <div class="cart-actions">
          <a href="<?= e(url()) ?>">← <?= e(t('continue_shopping')) ?></a>
          <button class="btn btn-light" type="submit"><?= e(t('update_cart')) ?></button>
        </div>
      </form>
      <?php foreach ($lines as $l): ?>
        <form method="post" id="remove-<?= (int)$l['product']['id'] ?>" hidden>
          <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int)$l['product']['id'] ?>">
        </form>
      <?php endforeach; ?>

      <aside class="summary card">
        <h2><?= e(t('order_summary')) ?></h2>
        <div class="sum-row"><span><?= e(t('subtotal')) ?></span><strong><?= e(money($totals['subtotal'])) ?></strong></div>
        <div class="sum-row muted"><span><?= e(t('total_weight')) ?></span><span><?= e(num($totals['weight'], 2)) ?> <?= e(t('kg')) ?></span></div>
        <div class="sum-row muted"><span><?= e(t('inside_dhaka')) ?></span><span><?= e(money(delivery_charge('inside', $totals['weight'], $totals['subtotal']))) ?></span></div>
        <div class="sum-row muted"><span><?= e(t('outside_dhaka')) ?></span><span><?= e(money(delivery_charge('outside', $totals['weight'], $totals['subtotal']))) ?></span></div>
        <p class="small muted"><?= e(t('delivery_note_checkout')) ?></p>
        <a class="btn btn-block" href="<?= e(url('checkout.php')) ?>"><?= e(t('proceed_checkout')) ?></a>
        <?php if (!current_customer()): ?><p class="small muted center"><?= e(t('login_needed_note')) ?></p><?php endif; ?>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
