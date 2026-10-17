<?php
/** Printable invoice. Visible to the customer who placed the order, or to the admin. */
require __DIR__ . '/includes/bootstrap.php';

$serial = (string)($_GET['serial'] ?? '');
$order = row('SELECT * FROM orders WHERE serial = ?', [$serial]);
$customer = current_customer();
$admin = current_admin();
if (!$order || (!$admin && (!$customer || (int)$customer['id'] !== (int)$order['customer_id']))) {
    if (!$customer && !$admin) {
        require_customer('invoice.php?serial=' . urlencode($serial));
    }
    http_response_code(404);
    exit('Invoice not found.');
}
$items = order_items((int)$order['id']);
?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('invoice')) ?> <?= e($order['serial']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Hind Siliguri', Arial, sans-serif; background: #fdf2f6; margin: 0; padding: 24px; color: #3a2a31; }
  .sheet { max-width: 760px; margin: auto; background: #fff; border: 1px solid #f6d5e2; border-radius: 12px; padding: 32px; }
  .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 2px solid #f8c8dc; padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; }
  .top img { height: 52px; }
  .store { text-align: right; font-size: 13px; color: #7d6670; }
  .tools { text-align: center; margin: 18px auto; }
  .tools button, .tools a { background: #b8456f; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font: inherit; cursor: pointer; text-decoration: none; margin: 0 4px; }
  .tools a { background: #fff; color: #b8456f; border: 1px solid #b8456f; }
  .status { display:inline-block; padding: 2px 10px; border-radius: 99px; background:#fdf2f6; color:#8a2f55; font-size: 12px; }
  @media print { body { background: #fff; padding: 0; } .sheet { border: 0; padding: 0; } .tools { display: none; } }
</style>
</head>
<body>
<div class="tools">
  <button type="button" onclick="window.print()"><?= e(t('print_invoice')) ?></button>
  <a href="<?= e($admin ? url('admin/order.php?id=' . $order['id']) : url('account.php')) ?>"><?= e(t('back')) ?></a>
</div>
<div class="sheet">
  <div class="top">
    <img src="<?= e(logo_url()) ?>" alt="<?= e(setting('store_name', 'Shifa')) ?>">
    <div class="store">
      <strong style="color:#8a2f55;font-size:16px;"><?= e(setting('store_name', 'Shifa')) ?></strong><br>
      <?= e(setting('store_address')) ?><br>
      <?= e(setting('store_phone')) ?> <?= setting('store_email') ? ' · ' . e(setting('store_email')) : '' ?>
    </div>
  </div>
  <p><span class="status"><?= e(status_label($order['status'])) ?></span> · <?= e(t('payment')) ?>: <?= e(t('pay_status_' . $order['payment_status'])) ?></p>
  <?= invoice_html($order, $items) ?>
  <p style="margin-top:28px;font-size:13px;color:#7d6670;text-align:center;"><?= e(t('invoice_thanks', ['store' => setting('store_name', 'Shifa')])) ?></p>
</div>
</body>
</html>
