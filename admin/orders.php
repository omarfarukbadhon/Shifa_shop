<?php
require __DIR__ . '/_layout.php';
require_admin();

$status = in_array($_GET['status'] ?? '', order_statuses(), true) ? $_GET['status'] : '';
$qtext = trim((string)($_GET['q'] ?? ''));
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

$where = ['1=1'];
$params = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($qtext !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $qtext) . '%';
    $where[] = "(serial LIKE ? ESCAPE '!' OR name LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";
    array_push($params, $like, $like, $like, $like);
}
if ($from) {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$w = implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel shows Bangla correctly
    fputcsv($out, ['Serial', 'Date', 'Status', 'Payment', 'Name', 'Phone', 'Email', 'Address', 'City', 'Area', 'Items', 'Subtotal', 'Delivery', 'Total', 'Weight kg', 'Note']);
    foreach (rows("SELECT * FROM orders WHERE $w ORDER BY id DESC", $params) as $o) {
        $items = implode('; ', array_map(fn($i) => $i['name'] . ' x' . $i['quantity'], order_items((int)$o['id'])));
        fputcsv($out, [$o['serial'], $o['created_at'], status_label($o['status']), $o['payment_status'], $o['name'], $o['phone'], $o['email'], $o['address'], $o['city'], $o['zone'] === 'inside' ? 'Inside Dhaka' : 'Outside Dhaka', $items, $o['subtotal'], $o['delivery_charge'], $o['total'], $o['total_weight'], $o['note']]);
    }
    exit;
}

$total = (int)val("SELECT COUNT(*) FROM orders WHERE $w", $params);
$perPage = 25;
$page = page_num();
$orders = rows("SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['n'];
}

admin_header('Orders', 'orders');
?>
<div class="status-tabs">
  <a href="orders.php" class="<?= $status === '' ? 'active' : '' ?>">All <em><?= array_sum($counts) ?></em></a>
  <?php foreach (order_statuses() as $s): ?>
    <a href="orders.php?status=<?= $s ?>" class="<?= $status === $s ? 'active' : '' ?>"><?= e(status_label($s)) ?> <em><?= $counts[$s] ?? 0 ?></em></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= e($qtext) ?>" placeholder="Serial, name, phone or email">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn btn-sm" type="submit">Filter</button>
  <?php if ($qtext || $from || $to): ?><a href="orders.php<?= $status ? '?status=' . e($status) : '' ?>">Clear</a><?php endif; ?>
  <a class="btn btn-sm btn-light push-right" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Download CSV</a>
</form>

<div class="panel">
<?php if ($orders): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Serial</th><th>Date</th><th>Customer</th><th>Area</th><th class="num">Total</th><th>Status</th><th>Payment</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr class="clickable" onclick="if(!event.target.closest('a'))location='order.php?id=<?= (int)$o['id'] ?>'">
        <td><a href="order.php?id=<?= (int)$o['id'] ?>"><strong><?= e($o['serial']) ?></strong></a></td>
        <td><?= e(date('d M Y', strtotime($o['created_at']))) ?><br><small class="muted"><?= e(date('h:i A', strtotime($o['created_at']))) ?></small></td>
        <td><?= e($o['name']) ?><br><small class="muted"><?= e($o['phone']) ?></small></td>
        <td><?= $o['zone'] === 'inside' ? 'Inside Dhaka' : 'Outside Dhaka' ?><br><small class="muted"><?= e($o['city']) ?></small></td>
        <td class="num"><strong><?= money($o['total'], false) ?></strong></td>
        <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
        <td><?= $o['payment_status'] === 'paid' ? '<span class="pill pill-delivered">Paid</span>' : '<span class="muted">COD – unpaid</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pagination($total, $perPage, $page) ?>
<?php else: ?>
  <p class="muted empty">No orders found.</p>
<?php endif; ?>
</div>
<?php admin_footer(); ?>
