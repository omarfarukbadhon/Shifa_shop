<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? '';
$metaDesc = $metaDesc ?? (lang() === 'bn' ? setting('store_tagline_bn', setting('store_tagline')) : setting('store_tagline'));
$storeName = setting('store_name', 'Shifa');
$customer = current_customer();
$navCats = main_categories();
$currentUri = ltrim(substr($_SERVER['REQUEST_URI'] ?? '', strlen(base_path())), '/');
$otherLang = lang() === 'bn' ? 'en' : 'bn';
?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ? $pageTitle . ' | ' : '') . $storeName) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#f8c8dc">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= SHIFA_VERSION ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e(($pageTitle ? $pageTitle . ' | ' : '') . $storeName) ?>">
</head>
<body class="lang-<?= lang() ?>">
<a class="skip" href="#main"><?= e(t('skip_to_content')) ?></a>
<div class="topbar">
  <div class="container topbar-inner">
    <span class="topbar-msg"><?= e(t('topbar_msg')) ?></span>
    <nav class="topbar-links">
      <?php if (setting('store_phone')): ?><a href="tel:<?= e(setting('store_phone')) ?>">☎ <?= e(setting('store_phone')) ?></a><?php endif; ?>
      <a href="<?= e(url('track.php')) ?>"><?= e(t('track_order')) ?></a>
      <a href="<?= e(url('lang.php?l=' . $otherLang . '&r=' . urlencode($currentUri))) ?>" class="lang-switch" lang="<?= $otherLang ?>"><?= $otherLang === 'bn' ? 'বাংলা' : 'English' ?></a>
    </nav>
  </div>
</div>
<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" type="button" aria-label="<?= e(t('menu')) ?>" aria-expanded="false" aria-controls="mainnav">
      <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
    <a class="logo" href="<?= e(url()) ?>"><img src="<?= e(logo_url()) ?>" alt="<?= e($storeName) ?>" height="44"></a>
    <form class="search" action="<?= e(url('search.php')) ?>" method="get" role="search">
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(t('search_placeholder')) ?>" aria-label="<?= e(t('search')) ?>">
      <button type="submit" aria-label="<?= e(t('search')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
    </form>
    <div class="header-actions">
      <?php if ($customer): ?>
        <a class="icon-link" href="<?= e(url('account.php')) ?>" title="<?= e(t('my_account')) ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span class="hide-sm"><?= e(explode(' ', $customer['name'])[0]) ?></span>
        </a>
      <?php else: ?>
        <a class="icon-link" href="<?= e(url('login.php')) ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span class="hide-sm"><?= e(t('login')) ?></span>
        </a>
      <?php endif; ?>
      <a class="icon-link cart-link" href="<?= e(url('cart.php')) ?>" aria-label="<?= e(t('cart')) ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 7V6a3 3 0 0 1 6 0v1" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <span class="cart-count" data-cart-count><?= cart_count() ? num(cart_count()) : '' ?></span>
        <span class="hide-sm"><?= e(t('cart')) ?></span>
      </a>
    </div>
  </div>
  <nav class="mainnav" id="mainnav" aria-label="<?= e(t('categories')) ?>">
    <div class="container">
      <ul>
        <li><a href="<?= e(url()) ?>"><?= e(t('home')) ?></a></li>
        <?php foreach ($navCats as $mc): $subs = sub_categories((int)$mc['id']); ?>
          <li class="<?= $subs ? 'has-sub' : '' ?>">
            <a href="<?= e(url('category.php?slug=' . urlencode($mc['slug']))) ?>"><?= e(loc($mc, 'name')) ?></a>
            <?php if ($subs): ?>
              <ul class="sub">
                <?php foreach ($subs as $sc): ?>
                  <li><a href="<?= e(url('category.php?slug=' . urlencode($sc['slug']))) ?>"><?= e(loc($sc, 'name')) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
        <li><a href="<?= e(url('search.php?sale=1')) ?>"><?= e(t('offers')) ?></a></li>
      </ul>
    </div>
  </nav>
</header>
<main id="main" class="site-main">
<div class="container flash-wrap"><?= render_flash() ?></div>
