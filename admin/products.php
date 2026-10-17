<?php
require __DIR__ . '/_layout.php';
require_admin();

$cat = (int)($_GET['cat'] ?? 0);
$qtext = trim((string)($_GET['q'] ?? ''));
$stockF = $_GET['stock'] ?? '';
$threshold = (int)setting('low_stock_threshold', '5');

$where = ['1=1'];
$params = [];
if ($cat) {
    $ids = category_tree_ids($cat, false);
    $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = array_merge($params, $ids);
}
if ($qtext !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $qtext) . '%';
    $where[] = "(p.name LIKE ? ESCAPE '!' OR p.name_bn LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!')";
    array_push($params, $like, $like, $like);
}
if ($stockF === 'low') {
    $where[] = 'p.quantity > 0 AND p.quantity <= ?';
    $params[] = $threshold;
} elseif ($stockF === 'out') {
    $where[] = 'p.quantity <= 0';
} elseif ($stockF === 'hidden') {
    $where[] = 'p.is_active = 0';
}
$w = implode(' AND ', $where);
$total = (int)val("SELECT COUNT(*) FROM products p WHERE $w", $params);
$perPage = 30;
$page = page_num();
$products = rows("SELECT p.* FROM products p WHERE $w ORDER BY p.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

admin_header('Products', 'products');
?>
<form class="filters" method="get">
  <input type="search" name="q" value="<?= e($qtext) ?>" placeholder="Search name or code">
  <select name="cat"><option value="">All categories</option><?= category_options($cat ?: null) ?></select>
  <select name="stock">
    <option value="">Any stock</option>
    <option value="low" <?= $stockF === 'low' ? 'selected' : '' ?>>Low stock (≤ <?= $threshold ?>)</option>
    <option value="out" <?= $stockF === 'out' ? 'selected' : '' ?>>Out of stock</option>
    <option value="hidden" <?= $stockF === 'hidden' ? 'selected' : '' ?>>Hidden from shop</option>
  </select>
  <button class="btn btn-sm" type="submit">Filter</button>
  <a class="btn btn-sm push-right" href="product-edit.php<?= $cat ? '?cat=' . $cat : '' ?>">+ Add product</a>
</form>

<div class="panel">
  <?php if ($products): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th class="num">Weight</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><img class="thumb" src="<?= e(img_url($p['image'])) ?>" alt="" width="48" height="48"></td>
          <td><a href="product-edit.php?id=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a>
            <?php if ($p['sku']): ?><br><small class="muted"><?= e($p['sku']) ?></small><?php endif; ?>
            <div class="tags">
              <?php if (!$p['is_active']): ?><span class="pill pill-cancelled">Hidden</span><?php endif; ?>
              <?php if ($p['is_featured']): ?><span class="pill pill-confirmed">Featured</span><?php endif; ?>
              <?php if (on_sale($p)): ?><span class="pill pill-pending">Sale</span><?php endif; ?>
            </div>
          </td>
          <td><?= e(category_path((int)$p['category_id'])) ?></td>
          <td class="num"><?php if (on_sale($p)): ?><s class="muted"><?= money($p['price'], false) ?></s><br><?php endif; ?><strong><?= money(effective_price($p), false) ?></strong></td>
          <td class="num"><span class="pill <?= (int)$p['quantity'] <= 0 ? 'pill-cancelled' : ((int)$p['quantity'] <= $threshold ? 'pill-warn' : 'pill-delivered') ?>"><?= (int)$p['quantity'] ?></span></td>
          <td class="num"><?= e((float)$p['weight']) ?> kg</td>
          <td class="num"><a class="btn btn-sm btn-light" href="product-edit.php?id=<?= (int)$p['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pagination($total, $perPage, $page) ?>
  <?php else: ?>
    <p class="muted empty">No products found. <a href="product-edit.php">Add your first product</a>.</p>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
