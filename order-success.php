<?php
require __DIR__ . '/includes/bootstrap.php';

$customer = require_customer();
$serial = (string)($_GET['serial'] ?? '');
$order = row('SELECT * FROM orders WHERE serial = ? AND customer_id = ?', [$serial, $customer['id']]);
if (!$order) {
    redirect('account.php');
}

$pageTitle = t('order_placed');
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <div class="card success-card">
    <div class="success-icon" aria-hidden="true">✓</div>
    <h1><?= e(t('thank_you_name', ['name' => explode(' ', $order['name'])[0]])) ?></h1>
    <p><?= e(t('order_received_text')) ?></p>
    <div class="serial-box">
      <span><?= e(t('your_tracking_number')) ?></span>
      <strong><?= e($order['serial']) ?></strong>
      <button type="button" class="link-btn" data-copy="<?= e($order['serial']) ?>"><?= e(t('copy')) ?></button>
    </div>
    <p class="muted"><?= e(t('invoice_emailed_to', ['email' => $order['email']])) ?></p>
    <div class="sum-row total narrow"><span><?= e(t('amount_to_pay')) ?></span><strong><?= e(money($order['total'])) ?></strong></div>
    <div class="btn-row">
      <a class="btn" href="<?= e(url('track.php?serial=' . urlencode($order['serial']))) ?>"><?= e(t('track_order')) ?></a>
      <a class="btn btn-light" href="<?= e(url('invoice.php?serial=' . urlencode($order['serial']))) ?>" target="_blank"><?= e(t('view_invoice')) ?></a>
      <a class="btn btn-light" href="<?= e(url()) ?>"><?= e(t('continue_shopping')) ?></a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
