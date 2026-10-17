<?php
require __DIR__ . '/includes/bootstrap.php';

$qtext = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$saleOnly = !empty($_GET['sale']);

$where = '';
$params = [];
if ($qtext !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $qtext) . '%';
    $where .= " AND (p.name LIKE ? ESCAPE '!' OR p.name_bn LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.description LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!' OR c.name_bn LIKE ? ESCAPE '!')";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($saleOnly) {
    $where .= ' AND p.sale_price > 0 AND p.sale_price < p.price';
}
$sorts = [
    'new' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END ASC',
    'price_desc' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END DESC',
];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'new';

$total = (int)val('SELECT COUNT(*) FROM (' . product_card_sql() . $where . ') x', $params);
$perPage = 12;
$page = page_num();
$products = rows(product_card_sql() . $where . ' ORDER BY ' . $sorts[$sort] . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

$pageTitle = $saleOnly ? t('offers') : ($qtext !== '' ? t('results_for', ['q' => $qtext]) : t('all_products'));
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <h1 class="page-title"><?= e($pageTitle) ?></h1>
  <form class="toolbar" method="get">
    <?php if ($qtext !== ''): ?><input type="hidden" name="q" value="<?= e($qtext) ?>"><?php endif; ?>
    <?php if ($saleOnly): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
    <span class="muted"><?= e(t('n_products', ['n' => num($total)])) ?></span>
    <label class="sort"><?= e(t('sort_by')) ?>
      <select name="sort" onchange="this.form.submit()">
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>><?= e(t('sort_new')) ?></option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= e(t('sort_price_asc')) ?></option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= e(t('sort_price_desc')) ?></option>
      </select>
    </label>
  </form>
  <?php if ($products): ?>
    <div class="grid-products">
      <?php foreach ($products as $p) include __DIR__ . '/includes/product-card.php'; ?>
    </div>
    <?= pagination($total, $perPage, $page) ?>
  <?php else: ?>
    <div class="empty-state">
      <p><?= e(t('no_results')) ?></p>
      <a class="btn btn-light" href="<?= e(url()) ?>"><?= e(t('back_home')) ?></a>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
