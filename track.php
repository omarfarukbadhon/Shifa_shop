<?php
require __DIR__ . '/includes/bootstrap.php';

$serial = strtoupper(trim((string)($_GET['serial'] ?? '')));
$phoneIn = trim((string)($_GET['phone'] ?? ''));
$customer = current_customer();
$order = null;
$error = '';

if ($serial !== '') {
    $candidate = row('SELECT * FROM orders WHERE serial = ?', [$serial]);
    // Logged-in owners see their order directly; everyone else must also give the order phone number.
    if ($candidate && $customer && (int)$candidate['customer_id'] === (int)$customer['id']) {
        $order = $candidate;
    } elseif ($phoneIn !== '') {
        if (too_many_attempts('track', 20, 15)) {
            $error = t('too_many_attempts');
        } elseif ($candidate && normalize_phone($phoneIn) !== '' && normalize_phone($phoneIn) === $candidate['phone']) {
            $order = $candidate;
        } else {
            record_attempt('track');
            $error = t('track_not_found');
        }
    }
}

$steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$pageTitle = t('track_order');
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e(t('track_order')) ?></h1>
  <?php if (!$order): ?>
    <div class="card auth-card">
      <p class="muted"><?= e(t('track_intro')) ?></p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="get" class="form">
        <label><?= e(t('tracking_number')) ?><input name="serial" value="<?= e($serial) ?>" placeholder="SHF261017001" required pattern="[A-Za-z0-9]{6,20}" autocapitalize="characters"></label>
        <label><?= e(t('phone_used_for_order')) ?><input type="tel" name="phone" value="<?= e($phoneIn) ?>" placeholder="01XXXXXXXXX" required inputmode="tel"></label>
        <button class="btn btn-block" type="submit"><?= e(t('track')) ?></button>
      </form>
    </div>
  <?php else:
      $history = rows('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id', [$order['id']]);
      $items = order_items((int)$order['id']);
      $cancelled = $order['status'] === 'cancelled';
      $currentIdx = array_search($order['status'], $steps, true);
  ?>
    <div class="card track-card">
      <div class="track-head">
        <div>
          <div class="muted small"><?= e(t('tracking_number')) ?></div>
          <div class="serial"><?= e($order['serial']) ?></div>
          <div class="muted small"><?= e(t('order_date')) ?>: <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></div>
        </div>
        <span class="status-pill status-<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span>
      </div>

      <?php if ($cancelled): ?>
        <div class="alert alert-error"><?= e(t('order_cancelled_text')) ?></div>
      <?php else: ?>
        <ol class="progress">
          <?php foreach ($steps as $i => $s): ?>
            <li class="<?= $i <= $currentIdx ? 'done' : '' ?> <?= $i === $currentIdx ? 'current' : '' ?>"><span class="dot" aria-hidden="true"></span><span class="lbl"><?= e(status_label($s)) ?></span></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <div class="track-grid">
        <div>
          <h2><?= e(t('customer_details')) ?></h2>
          <p><strong><?= e($order['name']) ?></strong><br><?= e($order['phone']) ?><br><?= e($order['email']) ?><br><?= nl2br(e($order['address'])) ?><?= $order['city'] ? ', ' . e($order['city']) : '' ?><br>
          <span class="muted"><?= e($order['zone'] === 'inside' ? t('inside_dhaka') : t('outside_dhaka')) ?></span></p>
        </div>
        <div>
          <h2><?= e(t('history')) ?></h2>
          <ul class="timeline">
            <?php foreach (array_reverse($history) as $h): ?>
              <li><strong><?= e(status_label($h['status'])) ?></strong> <span class="muted small"><?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?></span>
                <?php if ($h['note'] && $h['note'] !== 'Order placed'): ?><div class="small"><?= nl2br(e($h['note'])) ?></div><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <h2><?= e(t('items')) ?></h2>
      <ul class="mini-lines">
        <?php foreach ($items as $it): ?>
          <li><span><?= e($it['name']) ?> × <?= e(num($it['quantity'])) ?></span><span><?= e(money($it['line_total'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row"><span><?= e(t('subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
      <div class="sum-row"><span><?= e(t('delivery_charge')) ?></span><span><?= e(money($order['delivery_charge'])) ?></span></div>
      <div class="sum-row total"><span><?= e(t('grand_total')) ?></span><strong><?= e(money($order['total'])) ?></strong></div>
      <div class="btn-row">
        <?php if ($customer && (int)$customer['id'] === (int)$order['customer_id']): ?>
          <a class="btn btn-light" href="<?= e(url('invoice.php?serial=' . urlencode($order['serial']))) ?>" target="_blank"><?= e(t('view_invoice')) ?></a>
        <?php endif; ?>
        <a class="btn btn-light" href="<?= e(url('track.php')) ?>"><?= e(t('track_another')) ?></a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
