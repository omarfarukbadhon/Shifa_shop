<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$cat = row('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
if (!$cat) {
    http_response_code(404);
    $pageTitle = t('not_found');
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section empty-state"><h1>' . e(t('not_found')) . '</h1><p><a class="btn" href="' . e(url()) . '">' . e(t('back_home')) . '</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$parent = $cat['parent_id'] ? row('SELECT * FROM categories WHERE id = ?', [$cat['parent_id']]) : null;
if ($parent && !$parent['is_active']) {
    redirect('index.php');
}
$main = $parent ?: $cat;
$subs = sub_categories((int)$main['id']);
$comingSoon = (bool)$cat['coming_soon'] || ($parent && $parent['coming_soon']);

$ids = $parent ? [(int)$cat['id']] : category_tree_ids((int)$cat['id']);
$ph = implode(',', array_fill(0, count($ids), '?'));

$sorts = [
    'new' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END ASC',
    'price_desc' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END DESC',
    'name' => 'p.name ASC',
];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'new';
$inStockOnly = !empty($_GET['instock']);

$where = " AND p.category_id IN ($ph)" . ($inStockOnly ? ' AND p.quantity > 0' : '');
$total = (int)val('SELECT COUNT(*) FROM (' . product_card_sql() . $where . ') x', $ids);
$perPage = 12;
$page = page_num();
$offset = ($page - 1) * $perPage;
$products = $comingSoon ? [] : rows(product_card_sql() . $where . ' ORDER BY ' . $sorts[$sort] . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $ids);

$pageTitle = loc($cat, 'name');
if ($cat['description']) {
    $metaDesc = mb_substr($cat['description'], 0, 160);
}
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url()) ?>"><?= e(t('home')) ?></a> ›
    <?php if ($parent): ?><a href="<?= e(url('category.php?slug=' . urlencode($parent['slug']))) ?>"><?= e(loc($parent, 'name')) ?></a> › <?php endif; ?>
    <span><?= e(loc($cat, 'name')) ?></span>
  </nav>

  <h1 class="page-title"><?= e(loc($cat, 'name')) ?></h1>
  <?php if ($cat['description']): ?><p class="muted"><?= nl2br(e($cat['description'])) ?></p><?php endif; ?>

  <?php if ($subs && !$comingSoon): ?>
    <div class="chips" role="list">
      <a role="listitem" class="chip <?= !$parent ? 'active' : '' ?>" href="<?= e(url('category.php?slug=' . urlencode($main['slug']))) ?>"><?= e(t('all')) ?></a>
      <?php foreach ($subs as $s): ?>
        <a role="listitem" class="chip <?= (int)$s['id'] === (int)$cat['id'] ? 'active' : '' ?>" href="<?= e(url('category.php?slug=' . urlencode($s['slug']))) ?>"><?= e(loc($s, 'name')) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($comingSoon): ?>
    <div class="coming-soon-box">
      <img src="<?= e(url('assets/img/coming-soon.svg')) ?>" alt="" width="160" height="160">
      <h2><?= e(t('available_soon_title')) ?></h2>
      <p><?= e(t('available_soon_text')) ?></p>
      <a class="btn btn-light" href="<?= e(url()) ?>"><?= e(t('continue_shopping')) ?></a>
    </div>
  <?php else: ?>
    <form class="toolbar" method="get">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <span class="muted"><?= e(t('n_products', ['n' => num($total)])) ?></span>
      <label class="check-inline"><input type="checkbox" name="instock" value="1" <?= $inStockOnly ? 'checked' : '' ?> onchange="this.form.submit()"> <?= e(t('in_stock_only')) ?></label>
      <label class="sort"><?= e(t('sort_by')) ?>
        <select name="sort" onchange="this.form.submit()">
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>><?= e(t('sort_new')) ?></option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= e(t('sort_price_asc')) ?></option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= e(t('sort_price_desc')) ?></option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>><?= e(t('sort_name')) ?></option>
        </select>
      </label>
      <noscript><button class="btn btn-sm">OK</button></noscript>
    </form>
    <?php if ($products): ?>
      <div class="grid-products">
        <?php foreach ($products as $p) include __DIR__ . '/includes/product-card.php'; ?>
      </div>
      <?= pagination($total, $perPage, $page) ?>
    <?php else: ?>
      <div class="coming-soon-box">
        <img src="<?= e(url('assets/img/coming-soon.svg')) ?>" alt="" width="160" height="160">
        <h2><?= e(t('available_soon_title')) ?></h2>
        <p><?= e(t('no_products_in_cat')) ?></p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
