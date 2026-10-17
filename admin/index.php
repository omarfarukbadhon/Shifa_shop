<?php
require __DIR__ . '/_layout.php';
require_admin();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$todayStats = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM orders WHERE created_at >= ? AND status <> 'cancelled'", [$today . ' 00:00:00']);
$monthStats = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM orders WHERE created_at >= ? AND status <> 'cancelled'", [$monthStart . ' 00:00:00']);
$pending = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$toShip = (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing')");
$customers = (int)val('SELECT COUNT(*) FROM customers');
$newCustomers = (int)val('SELECT COUNT(*) FROM customers WHERE created_at >= ?', [$monthStart . ' 00:00:00']);
$threshold = (int)setting('low_stock_threshold', '5');
$lowStock = rows('SELECT id, name, quantity FROM products WHERE is_active = 1 AND quantity <= ? ORDER BY quantity ASC, name LIMIT 8', [$threshold]);
$recent = rows('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$best = rows("SELECT oi.name, SUM(oi.quantity) qty, SUM(oi.line_total) amount FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.created_at >= ? AND o.status <> 'cancelled' GROUP BY oi.name ORDER BY qty DESC LIMIT 5", [date('Y-m-d', strtotime('-30 days')) . ' 00:00:00']);

// Sales of the last 14 days
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0.0;
}
foreach (rows("SELECT created_at, total FROM orders WHERE created_at >= ? AND status <> 'cancelled'", [array_key_first($days) . ' 00:00:00']) as $o) {
    $d = substr($o['created_at'], 0, 10);
    if (isset($days[$d])) {
        $days[$d] += (float)$o['total'];
    }
}
$maxDay = max(1, max($days));

admin_header('Dashboard', 'dashboard');
?>
<?php if (!setting('smtp_host')): ?>
  <div class="alert alert-warn">Emails are not set up yet, so order emails may not arrive. Go to <a href="settings.php#email">Settings → Email</a> and add your email details, then send a test email.</div>
<?php endif; ?>

<div class="stats">
  <a class="stat" href="orders.php?status=pending"><span>New orders (waiting)</span><strong><?= $pending ?></strong></a>
  <a class="stat" href="orders.php?status=confirmed"><span>To pack &amp; ship</span><strong><?= $toShip ?></strong></a>
  <div class="stat"><span>Today</span><strong><?= money($todayStats['s'], false) ?></strong><small><?= (int)$todayStats['n'] ?> orders</small></div>
  <div class="stat"><span>This month</span><strong><?= money($monthStats['s'], false) ?></strong><small><?= (int)$monthStats['n'] ?> orders</small></div>
  <a class="stat" href="customers.php"><span>Customers</span><strong><?= $customers ?></strong><small>+<?= $newCustomers ?> this month</small></a>
</div>

<div class="panel">
  <div class="panel-head"><h2>Sales — last 14 days</h2><span class="muted">Cancelled orders not counted</span></div>
  <div class="bars" role="img" aria-label="Daily sales for the last 14 days">
    <?php foreach ($days as $d => $amt): ?>
      <div class="bar-col" title="<?= e(date('d M', strtotime($d)) . ': ' . money($amt, false)) ?>">
        <div class="bar" style="height: <?= max(2, round($amt / $maxDay * 100)) ?>%"><?php if ($amt > 0): ?><span><?= e(number_format($amt)) ?></span><?php endif; ?></div>
        <small><?= e(date('d', strtotime($d))) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="cols">
  <div class="panel">
    <div class="panel-head"><h2>Latest orders</h2><a href="orders.php">All orders →</a></div>
    <?php if ($recent): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Serial</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><a href="order.php?id=<?= (int)$o['id'] ?>"><strong><?= e($o['serial']) ?></strong></a><br><small class="muted"><?= e(date('d M, h:i A', strtotime($o['created_at']))) ?></small></td>
          <td><?= e($o['name']) ?><br><small class="muted"><?= e($o['phone']) ?></small></td>
          <td><?= money($o['total'], false) ?></td>
          <td><span class="pill pill-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?><p class="muted">No orders yet. They will appear here.</p><?php endif; ?>
  </div>

  <div>
    <div class="panel">
      <div class="panel-head"><h2>Low stock (≤ <?= $threshold ?>)</h2><a href="stock.php">Update stock →</a></div>
      <?php if ($lowStock): ?>
        <ul class="list">
          <?php foreach ($lowStock as $p): ?>
            <li><a href="product-edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a><span class="pill <?= (int)$p['quantity'] === 0 ? 'pill-cancelled' : 'pill-warn' ?>"><?= (int)$p['quantity'] ?> left</span></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="muted">All products have enough stock. 👍</p><?php endif; ?>
    </div>
    <div class="panel">
      <div class="panel-head"><h2>Best sellers (30 days)</h2></div>
      <?php if ($best): ?>
        <ul class="list">
          <?php foreach ($best as $b): ?><li><span><?= e($b['name']) ?></span><span class="muted"><?= (int)$b['qty'] ?> sold</span></li><?php endforeach; ?>
        </ul>
      <?php else: ?><p class="muted">No sales yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php admin_footer(); ?>
