<?php
require __DIR__ . '/_layout.php';
require_admin();

$cat = (int)($_GET['cat'] ?? 0);
$onlyLow = !empty($_GET['low']);
$threshold = (int)setting('low_stock_threshold', '5');

if (is_post()) {
    csrf_check();
    $changed = 0;
    $addAll = (int)($_POST['add_all'] ?? 0);
    $qtyIn = (array)($_POST['qty'] ?? []);
    $addIn = (array)($_POST['add'] ?? []);
    $ids = array_unique(array_map('intval', array_merge(array_keys($qtyIn), array_keys($addIn))));
    db()->beginTransaction();
    try {
        foreach ($ids as $pid) {
            $p = row('SELECT id, quantity FROM products WHERE id = ?', [$pid]);
            if (!$p) {
                continue;
            }
            $old = (int)$p['quantity'];
            $new = $old;
            $setRaw = trim((string)($qtyIn[$pid] ?? ''));
            if ($setRaw !== '' && is_numeric($setRaw)) {
                $new = (int)$setRaw;
            }
            $addRaw = trim((string)($addIn[$pid] ?? ''));
            if ($addRaw !== '' && is_numeric($addRaw)) {
                $new += (int)$addRaw;
            }
            $new += $addAll;
            $new = max(0, $new);
            if ($new !== $old) {
                q('UPDATE products SET quantity = ?, updated_at = ? WHERE id = ?', [$new, now(), $pid]);
                log_stock($pid, $new - $old, $new, $addAll ? 'Stock update (category, +' . $addAll . ' to all)' : 'Stock update (category)');
                $changed++;
            }
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    flash('success', $changed ? "Stock updated for $changed product(s)." : 'Nothing changed.');
    redirect('admin/stock.php?' . http_build_query(['cat' => $cat ?: null, 'low' => $onlyLow ? 1 : null]));
}

$where = ['1=1'];
$params = [];
if ($cat) {
    $ids = category_tree_ids($cat, false);
    $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = $ids;
}
if ($onlyLow) {
    $where[] = 'p.quantity <= ?';
    $params[] = $threshold;
}
$products = rows('SELECT p.* FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort_order, c.name, p.name', $params);
$totalUnits = array_sum(array_map(fn($p) => (int)$p['quantity'], $products));

// Summary per main category
$summary = [];
foreach (main_categories(false) as $m) {
    $ids = category_tree_ids((int)$m['id'], false);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $summary[] = [
        'cat' => $m,
        'items' => (int)val("SELECT COUNT(*) FROM products WHERE category_id IN ($ph)", $ids),
        'units' => (int)val("SELECT COALESCE(SUM(quantity),0) FROM products WHERE category_id IN ($ph)", $ids),
        'low' => (int)val("SELECT COUNT(*) FROM products WHERE category_id IN ($ph) AND quantity <= ?", array_merge($ids, [$threshold])),
    ];
}

admin_header('Stock update', 'stock');
?>
<div class="stats">
  <?php foreach ($summary as $s): ?>
    <a class="stat <?= $cat === (int)$s['cat']['id'] ? 'active' : '' ?>" href="stock.php?cat=<?= (int)$s['cat']['id'] ?>">
      <span><?= e($s['cat']['name']) ?></span><strong><?= $s['units'] ?> <small>units</small></strong>
      <small><?= $s['items'] ?> products<?= $s['low'] ? ' · <b class="warn">' . $s['low'] . ' low</b>' : '' ?></small>
    </a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <select name="cat" onchange="this.form.submit()"><option value="">All categories</option><?= category_options($cat ?: null) ?></select>
  <label class="check-inline"><input type="checkbox" name="low" value="1" <?= $onlyLow ? 'checked' : '' ?> onchange="this.form.submit()"> Only low stock (≤ <?= $threshold ?>)</label>
  <noscript><button class="btn btn-sm">Show</button></noscript>
</form>

<form method="post" class="panel">
  <?= csrf_field() ?>
  <div class="panel-head">
    <h2><?= $cat ? e(category_path($cat)) : 'All products' ?> <span class="muted">· <?= count($products) ?> products, <?= $totalUnits ?> units</span></h2>
  </div>
  <?php if ($products): ?>
    <p class="hint">Type the <strong>new total</strong> in "Set to", or a number to <strong>add</strong> (use minus to remove, e.g. -2). Leave empty to keep as it is. Then press Save.</p>
    <div class="table-wrap"><table class="table stock-table">
      <thead><tr><th>Product</th><th>Category</th><th class="num">Now</th><th>Set to</th><th>Add / remove</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): $q = (int)$p['quantity']; ?>
          <tr>
            <td><a href="product-edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a><?php if (!$p['is_active']): ?> <span class="pill pill-cancelled">Hidden</span><?php endif; ?></td>
            <td class="muted"><?= e(category_path((int)$p['category_id'])) ?></td>
            <td class="num"><span class="pill <?= $q <= 0 ? 'pill-cancelled' : ($q <= $threshold ? 'pill-warn' : 'pill-delivered') ?>"><?= $q ?></span></td>
            <td><input class="mini" type="number" name="qty[<?= (int)$p['id'] ?>]" min="0" step="1" placeholder="<?= $q ?>" aria-label="Set stock for <?= e($p['name']) ?>"></td>
            <td><input class="mini" type="number" name="add[<?= (int)$p['id'] ?>]" step="1" placeholder="+0" aria-label="Add stock for <?= e($p['name']) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="stock-actions">
      <label>Add the same amount to <strong>every</strong> product above <input class="mini" type="number" name="add_all" step="1" placeholder="0"></label>
      <button class="btn" type="submit">Save stock</button>
    </div>
  <?php else: ?>
    <p class="muted empty">No products here.</p>
  <?php endif; ?>
</form>
<?php admin_footer(); ?>
