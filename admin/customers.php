<?php
require __DIR__ . '/_layout.php';
require_admin();

$qtext = trim((string)($_GET['q'] ?? ''));
$optF = $_GET['optin'] ?? '';
$where = ['1=1'];
$params = [];
if ($qtext !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $qtext) . '%';
    $where[] = "(c.name LIKE ? ESCAPE '!' OR c.email LIKE ? ESCAPE '!' OR c.phone LIKE ? ESCAPE '!' OR c.city LIKE ? ESCAPE '!')";
    array_push($params, $like, $like, $like, $like);
}
if ($optF === '1') {
    $where[] = 'c.marketing_opt_in = 1';
} elseif ($optF === '0') {
    $where[] = 'c.marketing_opt_in = 0';
}
$w = implode(' AND ', $where);
$sql = "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') AS orders_count,
               (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') AS spent
        FROM customers c WHERE $w";

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'Mobile', 'Address', 'City', 'Area', 'Wants offers', 'Orders', 'Total spent', 'Joined', 'Last order']);
    foreach (rows($sql . ' ORDER BY c.id DESC', $params) as $c) {
        fputcsv($out, [$c['name'], $c['email'], $c['phone'], $c['address'], $c['city'], $c['zone'] === 'outside' ? 'Outside Dhaka' : 'Inside Dhaka', $c['marketing_opt_in'] ? 'Yes' : 'No', $c['orders_count'], $c['spent'], $c['created_at'], $c['last_order_at']]);
    }
    exit;
}

$total = (int)val("SELECT COUNT(*) FROM customers c WHERE $w", $params);
$perPage = 30;
$page = page_num();
$customers = rows($sql . " ORDER BY c.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$optCount = (int)val('SELECT COUNT(*) FROM customers WHERE marketing_opt_in = 1');

admin_header('Customers', 'customers');
?>
<div class="stats">
  <div class="stat"><span>All customers</span><strong><?= (int)val('SELECT COUNT(*) FROM customers') ?></strong></div>
  <div class="stat"><span>Agreed to offer emails</span><strong><?= $optCount ?></strong></div>
  <a class="stat" href="marketing.php"><span>Send them news</span><strong>✉</strong><small>Marketing email →</small></a>
</div>

<form class="filters" method="get">
  <input type="search" name="q" value="<?= e($qtext) ?>" placeholder="Name, email, mobile or city">
  <select name="optin">
    <option value="">Everyone</option>
    <option value="1" <?= $optF === '1' ? 'selected' : '' ?>>Wants offer emails</option>
    <option value="0" <?= $optF === '0' ? 'selected' : '' ?>>Does not want offers</option>
  </select>
  <button class="btn btn-sm" type="submit">Filter</button>
  <a class="btn btn-sm btn-light push-right" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Download CSV (Excel)</a>
</form>

<div class="panel">
  <?php if ($customers): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Customer</th><th>Mobile</th><th>Location</th><th class="num">Orders</th><th class="num">Spent</th><th>Offers</th><th>Joined</th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong><br><a href="mailto:<?= e($c['email']) ?>" class="small"><?= e($c['email']) ?></a></td>
          <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></td>
          <td><?= e($c['city'] ?: '—') ?><br><small class="muted"><?= $c['zone'] === 'outside' ? 'Outside Dhaka' : 'Inside Dhaka' ?></small></td>
          <td class="num"><?php if ($c['orders_count']): ?><a href="orders.php?q=<?= urlencode($c['email']) ?>"><?= (int)$c['orders_count'] ?></a><?php else: ?>0<?php endif; ?></td>
          <td class="num"><?= money($c['spent'], false) ?></td>
          <td><?= $c['marketing_opt_in'] ? '<span class="pill pill-delivered">Yes</span>' : '<span class="pill pill-cancelled">No</span>' ?></td>
          <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pagination($total, $perPage, $page) ?>
  <?php else: ?><p class="muted empty">No customers yet. They appear here when they create an account.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
