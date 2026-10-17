<?php
/** @var array $p product row */
$inStock = (int)$p['quantity'] > 0;
$link = url('product.php?slug=' . urlencode($p['slug']));
?>
<article class="card product-card">
  <a href="<?= e($link) ?>" class="pc-img">
    <img src="<?= e(img_url($p['image'])) ?>" alt="<?= e(loc($p, 'name')) ?>" loading="lazy" width="400" height="400">
    <?php if (!$inStock): ?>
      <span class="badge badge-muted"><?= e(t('out_of_stock')) ?></span>
    <?php elseif (on_sale($p)): ?>
      <span class="badge"><?= e(t('percent_off', ['n' => num(discount_percent($p))])) ?></span>
    <?php endif; ?>
  </a>
  <div class="pc-body">
    <h3 class="pc-title"><a href="<?= e($link) ?>"><?= e(loc($p, 'name')) ?></a></h3>
    <div class="price">
      <span class="now"><?= e(money(effective_price($p))) ?></span>
      <?php if (on_sale($p)): ?><s class="was"><?= e(money($p['price'])) ?></s><?php endif; ?>
    </div>
    <?php if ($inStock): ?>
      <form method="post" action="<?= e(url('cart.php')) ?>" class="add-form" data-ajax-cart>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="qty" value="1">
        <button class="btn btn-sm btn-block" type="submit"><?= e(t('add_to_cart')) ?></button>
      </form>
    <?php else: ?>
      <button class="btn btn-sm btn-block btn-light" type="button" disabled><?= e(t('out_of_stock')) ?></button>
    <?php endif; ?>
  </div>
</article>
