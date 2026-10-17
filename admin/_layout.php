<?php
/** Admin layout helpers. Every admin page includes this file. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// The admin panel is always in English.
with_lang_force_en();

function with_lang_force_en(): void
{
    global $LANG, $LANG_CODE;
    $LANG_CODE = 'en';
    $LANG = require SHIFA_ROOT . '/includes/lang/en.php';
}

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $pending = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $low = (int)val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND quantity <= ?', [(int)setting('low_stock_threshold', '5')]);
    $nav = [
        'dashboard' => ['index.php', 'Dashboard', '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>'],
        'orders' => ['orders.php', 'Orders', '<path d="M6 2h12l2 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zM4 6h16M9 11h6M9 15h6"/>', $pending],
        'products' => ['products.php', 'Products', '<path d="M21 8l-9-5-9 5 9 5zM3 8v8l9 5 9-5V8M12 13v8"/>'],
        'categories' => ['categories.php', 'Categories', '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>'],
        'stock' => ['stock.php', 'Stock update', '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>', $low],
        'customers' => ['customers.php', 'Customers', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>'],
        'marketing' => ['marketing.php', 'Marketing email', '<path d="M4 4h16v16H4zM4 6l8 7 8-7"/>'],
        'delivery' => ['delivery.php', 'Delivery charges', '<path d="M1 4h14v12H1zM15 9h4l3 4v3h-7M5.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM18.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>'],
        'settings' => ['settings.php', 'Settings', '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'],
    ];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(setting('store_name', 'Shifa')) ?> Admin</title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=<?= SHIFA_VERSION ?>">
</head>
<body class="admin">
<aside class="sidebar" id="sidebar">
  <a class="side-logo" href="<?= e(url('admin/')) ?>"><img src="<?= e(logo_url()) ?>" alt="<?= e(setting('store_name', 'Shifa')) ?>" height="38"><span>Admin</span></a>
  <nav>
    <?php foreach ($nav as $key => $n): ?>
      <a href="<?= e(url('admin/' . $n[0])) ?>" class="<?= $active === $key ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $n[2] ?></svg>
        <span><?= e($n[1]) ?></span>
        <?php if (!empty($n[3])): ?><em class="count" title="<?= $key === 'stock' ? 'Low stock items' : 'New orders' ?>"><?= (int)$n[3] ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= e(url()) ?>" target="_blank">View shop ↗</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit" class="linklike">Log out</button></form>
  </div>
</aside>
<div class="main">
  <header class="topline">
    <button class="burger" type="button" aria-label="Menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <h1><?= e($title) ?></h1>
    <span class="who"><?= e($admin['name'] ?? '') ?></span>
  </header>
  <div class="content">
  <?= render_flash() ?>
<?php
}

function admin_footer(string $extra = ''): void
{
    ?>
  </div>
</div>
<script>window.SHIFA_ADMIN = { csrf: <?= json_encode(csrf_token()) ?>, base: <?= json_encode(base_path()) ?> };</script>
<script src="<?= e(url('assets/js/admin.js')) ?>?v=<?= SHIFA_VERSION ?>"></script>
<?= $extra ?>
</body>
</html>
<?php
}

function status_options(string $selected = ''): string
{
    $h = '';
    foreach (order_statuses() as $s) {
        $h .= '<option value="' . $s . '"' . ($s === $selected ? ' selected' : '') . '>' . e(status_label($s)) . '</option>';
    }
    return $h;
}

/** <option> list of categories grouped by main category. */
function category_options(?int $selected = null, bool $subsOnlyWhenAvailable = false): string
{
    $h = '';
    foreach (main_categories(false) as $m) {
        $subs = sub_categories((int)$m['id'], false);
        if ($subs) {
            $h .= '<optgroup label="' . e($m['name']) . '">';
            if (!$subsOnlyWhenAvailable) {
                $h .= '<option value="' . (int)$m['id'] . '"' . ((int)$m['id'] === $selected ? ' selected' : '') . '>' . e($m['name']) . ' (main)</option>';
            }
            foreach ($subs as $s) {
                $h .= '<option value="' . (int)$s['id'] . '"' . ((int)$s['id'] === $selected ? ' selected' : '') . '>' . e($s['name']) . '</option>';
            }
            $h .= '</optgroup>';
        } else {
            $h .= '<option value="' . (int)$m['id'] . '"' . ((int)$m['id'] === $selected ? ' selected' : '') . '>' . e($m['name']) . '</option>';
        }
    }
    return $h;
}

function category_path(int $catId): string
{
    $c = row('SELECT c.name, p.name AS parent FROM categories c LEFT JOIN categories p ON p.id = c.parent_id WHERE c.id = ?', [$catId]);
    if (!$c) {
        return '—';
    }
    return $c['parent'] ? $c['parent'] . ' › ' . $c['name'] : $c['name'];
}
