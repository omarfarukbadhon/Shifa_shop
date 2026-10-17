</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <img src="<?= e(logo_url()) ?>" alt="<?= e(setting('store_name', 'Shifa')) ?>" height="40" class="footer-logo">
      <p><?= e(lang() === 'bn' ? setting('store_tagline_bn', setting('store_tagline')) : setting('store_tagline')) ?></p>
    </div>
    <div>
      <h3><?= e(t('shop')) ?></h3>
      <ul>
        <?php foreach (main_categories() as $mc): ?>
          <li><a href="<?= e(url('category.php?slug=' . urlencode($mc['slug']))) ?>"><?= e(loc($mc, 'name')) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3><?= e(t('help')) ?></h3>
      <ul>
        <li><a href="<?= e(url('track.php')) ?>"><?= e(t('track_order')) ?></a></li>
        <li><a href="<?= e(url('account.php')) ?>"><?= e(t('my_account')) ?></a></li>
        <li><a href="<?= e(url('cart.php')) ?>"><?= e(t('cart')) ?></a></li>
      </ul>
    </div>
    <div>
      <h3><?= e(t('contact_us')) ?></h3>
      <ul class="contact">
        <?php if (setting('store_phone')): ?><li><a href="tel:<?= e(setting('store_phone')) ?>"><?= e(setting('store_phone')) ?></a></li><?php endif; ?>
        <?php if (setting('store_email')): ?><li><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li><?php endif; ?>
        <?php if (setting('store_address')): ?><li><?= e(setting('store_address')) ?></li><?php endif; ?>
        <?php if (setting('facebook_url')): ?><li><a href="<?= e(setting('facebook_url')) ?>" rel="noopener" target="_blank">Facebook</a></li><?php endif; ?>
      </ul>
      <p class="cod-badge"><?= e(t('cod_available')) ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">© <?= lang() === 'bn' ? bn_digits(date('Y')) : date('Y') ?> <?= e(setting('store_name', 'Shifa')) ?>. <?= e(t('rights')) ?></div>
  </div>
</footer>
<script>window.SHIFA = <?= json_encode([
    'base' => base_path(),
    'csrf' => csrf_token(),
    'lang' => lang(),
    'i18n' => ['added' => t('added_to_cart'), 'view_cart' => t('view_cart'), 'error' => t('something_wrong')],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= SHIFA_VERSION ?>"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
