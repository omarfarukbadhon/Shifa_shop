<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$p = row(product_card_sql() . ' AND p.slug = ?', [$slug]);
if (!$p) {
    http_response_code(404);
    $pageTitle = t('not_found');
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section empty-state"><h1>' . e(t('not_found')) . '</h1><p><a class="btn" href="' . e(url()) . '">' . e(t('back_home')) . '</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$cat = row('SELECT * FROM categories WHERE id = ?', [$p['category_id']]);
$parent = $cat && $cat['parent_id'] ? row('SELECT * FROM categories WHERE id = ?', [$cat['parent_id']]) : null;

$images = [];
if ($p['image']) {
    $images[] = $p['image'];
}
foreach (rows('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$p['id']]) as $im) {
    $images[] = $im['path'];
}
if (!$images) {
    $images[] = '';
}

$related = rows(product_card_sql() . ' AND p.category_id = ? AND p.id <> ? ORDER BY p.quantity > 0 DESC, p.created_at DESC LIMIT 4', [$p['category_id'], $p['id']]);
$stock = (int)$p['quantity'];
$low = (int)setting('low_stock_threshold', '5');

$pageTitle = loc($p, 'name');
$metaDesc = mb_substr(strip_tags(loc($p, 'description')), 0, 160);
$ogImage = $p['image'] ? full_url($p['image']) : '';
require __DIR__ . '/includes/header.php';
?>
<div class="container section">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url()) ?>"><?= e(t('home')) ?></a> ›
    <?php if ($parent): ?><a href="<?= e(url('category.php?slug=' . urlencode($parent['slug']))) ?>"><?= e(loc($parent, 'name')) ?></a> › <?php endif; ?>
    <?php if ($cat): ?><a href="<?= e(url('category.php?slug=' . urlencode($cat['slug']))) ?>"><?= e(loc($cat, 'name')) ?></a> › <?php endif; ?>
    <span><?= e(loc($p, 'name')) ?></span>
  </nav>

  <div class="product-detail">
    <div class="gallery">
      <div class="gallery-main">
        <img id="mainImg" src="<?= e(img_url($images[0])) ?>" alt="<?= e(loc($p, 'name')) ?>" width="600" height="600">
        <?php if (on_sale($p) && $stock > 0): ?><span class="badge badge-lg"><?= e(t('percent_off', ['n' => num(discount_percent($p))])) ?></span><?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $im): ?>
            <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(img_url($im)) ?>" aria-label="<?= e(t('image_n', ['n' => num($i + 1)])) ?>">
              <img src="<?= e(img_url($im)) ?>" alt="" width="80" height="80" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="pd-info">
      <h1><?= e(loc($p, 'name')) ?></h1>
      <div class="price price-lg">
        <span class="now"><?= e(money(effective_price($p))) ?></span>
        <?php if (on_sale($p)): ?><s class="was"><?= e(money($p['price'])) ?></s> <span class="save"><?= e(t('you_save', ['amount' => money((float)$p['price'] - effective_price($p))])) ?></span><?php endif; ?>
      </div>

      <p class="stock <?= $stock <= 0 ? 'out' : ($stock <= $low ? 'low' : 'in') ?>">
        <?php if ($stock <= 0): ?>
          <?= e(t('out_of_stock')) ?>
        <?php elseif ($stock <= $low): ?>
          <?= e(t('only_n_left', ['n' => num($stock)])) ?>
        <?php else: ?>
          <?= e(t('in_stock')) ?>
        <?php endif; ?>
      </p>

      <?php if ($stock > 0): ?>
        <form method="post" action="<?= e(url('cart.php')) ?>" class="buy-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <div class="qty-box">
            <label for="qty"><?= e(t('quantity')) ?></label>
            <div class="qty-stepper">
              <button type="button" data-step="-1" aria-label="−">−</button>
              <input id="qty" type="number" name="qty" value="1" min="1" max="<?= $stock ?>" inputmode="numeric">
              <button type="button" data-step="1" aria-label="+">+</button>
            </div>
          </div>
          <div class="buy-buttons">
            <button class="btn btn-outline" type="submit" name="go" value="cart"><?= e(t('add_to_cart')) ?></button>
            <button class="btn" type="submit" name="go" value="checkout"><?= e(t('buy_now')) ?></button>
          </div>
        </form>
      <?php endif; ?>

      <ul class="pd-meta">
        <?php if ($cat): ?><li><span><?= e(t('category')) ?></span> <a href="<?= e(url('category.php?slug=' . urlencode($cat['slug']))) ?>"><?= e(loc($cat, 'name')) ?></a></li><?php endif; ?>
        <?php if ($p['sku']): ?><li><span><?= e(t('sku')) ?></span> <?= e($p['sku']) ?></li><?php endif; ?>
        <?php if ((float)$p['weight'] > 0): ?><li><span><?= e(t('weight')) ?></span> <?= e(num($p['weight'], (float)$p['weight'] == floor((float)$p['weight']) ? 0 : 2)) ?> <?= e(t('kg')) ?></li><?php endif; ?>
        <li><span><?= e(t('payment')) ?></span> <?= e(t('pay_cod')) ?></li>
        <li><span><?= e(t('delivery')) ?></span> <?= e(t('delivery_from', ['in' => money(delivery_charge('inside', 0.001)), 'out' => money(delivery_charge('outside', 0.001))])) ?></li>
      </ul>

      <?php if (loc($p, 'description') !== ''): ?>
        <div class="pd-desc">
          <h2><?= e(t('description')) ?></h2>
          <p><?= nl2br(e(loc($p, 'description'))) ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="section">
      <h2 class="section-title"><?= e(t('you_may_also_like')) ?></h2>
      <div class="grid-products">
        <?php foreach ($related as $p) include __DIR__ . '/includes/product-card.php'; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
