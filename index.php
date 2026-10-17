<?php
require __DIR__ . '/includes/bootstrap.php';

$mains = main_categories();
$newArrivals = rows(product_card_sql() . ' ORDER BY p.created_at DESC, p.id DESC LIMIT 8');
$featured = rows(product_card_sql() . ' AND p.is_featured = 1 ORDER BY p.updated_at DESC LIMIT 8');
$deals = rows(product_card_sql() . ' AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price AND p.quantity > 0 ORDER BY p.updated_at DESC LIMIT 4');

$pageTitle = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <h1><?= e(t('hero_title', ['store' => setting('store_name', 'Shifa')])) ?></h1>
      <p><?= e(lang() === 'bn' ? setting('store_tagline_bn', setting('store_tagline')) : setting('store_tagline')) ?></p>
      <div class="hero-points">
        <span>✓ <?= e(t('cod_available')) ?></span>
        <span>✓ <?= e(t('all_bd_delivery')) ?></span>
      </div>
      <?php if ($mains): ?><a class="btn" href="<?= e(url('category.php?slug=' . urlencode($mains[0]['slug']))) ?>"><?= e(t('shop_now')) ?></a><?php endif; ?>
    </div>
    <img class="hero-art" src="<?= e(url('assets/img/hero.svg')) ?>" alt="" width="420" height="320">
  </div>
</section>

<section class="container section">
  <h2 class="section-title"><?= e(t('shop_by_category')) ?></h2>
  <div class="cat-tiles">
    <?php foreach ($mains as $mc): ?>
      <a class="cat-tile" href="<?= e(url('category.php?slug=' . urlencode($mc['slug']))) ?>">
        <img src="<?= e(img_url($mc['image'], 'assets/img/cat-default.svg')) ?>" alt="" width="120" height="120">
        <span class="cat-name"><?= e(loc($mc, 'name')) ?></span>
        <?php if ($mc['coming_soon']): ?><span class="soon"><?= e(t('coming_soon')) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featured): ?>
<section class="container section">
  <div class="section-head"><h2 class="section-title"><?= e(t('featured')) ?></h2></div>
  <div class="grid-products">
    <?php foreach ($featured as $p) include __DIR__ . '/includes/product-card.php'; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($deals): ?>
<section class="container section">
  <div class="section-head"><h2 class="section-title"><?= e(t('todays_deals')) ?></h2><a href="<?= e(url('search.php?sale=1')) ?>"><?= e(t('see_all')) ?> →</a></div>
  <div class="grid-products">
    <?php foreach ($deals as $p) include __DIR__ . '/includes/product-card.php'; ?>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="section-head"><h2 class="section-title"><?= e(t('new_arrivals')) ?></h2><a href="<?= e(url('search.php')) ?>"><?= e(t('see_all')) ?> →</a></div>
  <?php if ($newArrivals): ?>
    <div class="grid-products">
      <?php foreach ($newArrivals as $p) include __DIR__ . '/includes/product-card.php'; ?>
    </div>
  <?php else: ?>
    <p class="empty"><?= e(t('no_products_yet')) ?></p>
  <?php endif; ?>
</section>

<section class="container section trust">
  <div><strong><?= e(t('trust_cod')) ?></strong><span><?= e(t('trust_cod_text')) ?></span></div>
  <div><strong><?= e(t('trust_delivery')) ?></strong><span><?= e(t('trust_delivery_text')) ?></span></div>
  <div><strong><?= e(t('trust_track')) ?></strong><span><?= e(t('trust_track_text')) ?></span></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
