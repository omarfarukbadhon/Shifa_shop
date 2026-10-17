<?php
/**
 * One-time installer. Open https://your-domain/install.php after uploading the files.
 * It creates the database tables, your admin account and the starting categories,
 * then locks itself (it will not run again once includes/config.php exists).
 */
declare(strict_types=1);
define('SHIFA_ROOT', __DIR__);
date_default_timezone_set('Asia/Dhaka');

$configFile = __DIR__ . '/includes/config.php';
if (is_file($configFile)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Already installed</title><body style="font-family:sans-serif;padding:40px;max-width:560px;margin:auto">
    <h2>Shifa is already installed</h2><p>For security the installer is locked. You can delete <code>install.php</code> from your hosting now.</p>
    <p><a href="index.php">Go to the shop</a> · <a href="admin/">Go to admin panel</a></p></body>';
    exit;
}

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/schema.php';

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$checks = [
    'PHP 8.0 or newer' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO database extension' => extension_loaded('pdo'),
    'GD image extension (for resizing photos)' => extension_loaded('gd'),
    'mbstring extension' => extension_loaded('mbstring'),
    '"includes" folder is writable' => is_writable(__DIR__ . '/includes'),
    '"uploads" folder is writable' => is_writable(__DIR__ . '/uploads'),
    '"data" folder is writable' => is_writable(__DIR__ . '/data'),
];
$mysqlOk = extension_loaded('pdo_mysql');
$sqliteOk = extension_loaded('pdo_sqlite');

$errors = [];
$f = [
    'driver' => $_POST['driver'] ?? ($mysqlOk ? 'mysql' : 'sqlite'),
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'db_pass' => $_POST['db_pass'] ?? '',
    'store_name' => $_POST['store_name'] ?? 'Shifa',
    'notify_email' => $_POST['notify_email'] ?? '',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
    'sample' => isset($_POST['sample']) || ($_SERVER['REQUEST_METHOD'] !== 'POST'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string)($_POST['admin_pass'] ?? '');
    $pass2 = (string)($_POST['admin_pass2'] ?? '');
    foreach ($checks as $label => $ok) {
        if (!$ok && strpos($label, 'GD') === false) {
            $errors[] = 'Requirement missing: ' . $label;
        }
    }
    if (!in_array($f['driver'], ['mysql', 'sqlite'], true)) {
        $errors[] = 'Choose a database type.';
    }
    if ($f['driver'] === 'mysql' && ($f['db_name'] === '' || $f['db_user'] === '')) {
        $errors[] = 'Enter the MySQL database name and user (you create these in cPanel → MySQL Databases).';
    }
    if (trim($f['store_name']) === '') {
        $errors[] = 'Enter the shop name.';
    }
    if (!filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid admin email.';
    }
    if ($f['notify_email'] !== '' && !filter_var($f['notify_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The order notification email is not valid.';
    }
    if (trim($f['admin_name']) === '') {
        $errors[] = 'Enter your name.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'The two admin passwords do not match.';
    }

    if (!$errors) {
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $config = [
            'db_driver' => $f['driver'],
            'db_host' => $f['db_host'],
            'db_port' => (int)$f['db_port'],
            'db_name' => $f['db_name'],
            'db_user' => $f['db_user'],
            'db_pass' => $f['db_pass'],
            'db_path' => 'data/shifa.sqlite',
            'base_path' => $basePath,
            'site_url' => ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath,
            'secret' => bin2hex(random_bytes(32)),
            'debug' => false,
        ];
        try {
            $pdo = db_make_pdo($config);
            $GLOBALS['PDO'] = $pdo;
            $exists = false;
            try {
                $pdo->query('SELECT 1 FROM settings LIMIT 1');
                $exists = true;
            } catch (Throwable $ignored) {
            }
            if ($exists) {
                throw new RuntimeException('This database already has Shifa tables. Use an empty database, or remove the old tables first.');
            }
            foreach (shifa_schema($f['driver']) as $sql) {
                $pdo->exec($sql);
            }
            $now = date('Y-m-d H:i:s');

            insert('admins', [
                'name' => trim($f['admin_name']),
                'email' => strtolower(trim($f['admin_email'])),
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'created_at' => $now,
            ]);

            $settings = [
                'store_name' => trim($f['store_name']),
                'store_tagline' => 'Lovely things for her, him and the little ones',
                'store_tagline_bn' => 'তার, তাদের এবং ছোট্ট সোনামণিদের জন্য প্রিয় সব জিনিস',
                'store_phone' => '',
                'store_email' => $f['notify_email'] ?: strtolower(trim($f['admin_email'])),
                'store_address' => 'Dhaka, Bangladesh',
                'notify_email' => $f['notify_email'] ?: strtolower(trim($f['admin_email'])),
                'smtp_host' => '',
                'smtp_port' => '587',
                'smtp_secure' => 'tls',
                'smtp_user' => '',
                'smtp_pass' => '',
                'mail_from_email' => '',
                'mail_from_name' => trim($f['store_name']),
                'low_stock_threshold' => '5',
                'free_delivery_over' => '0',
                'extra_per_kg_inside' => '10',
                'extra_per_kg_outside' => '20',
                'status_emails' => '1',
                'facebook_url' => '',
                'logo' => '',
            ];
            foreach ($settings as $k => $v) {
                insert('settings', ['k' => $k, 'v' => $v]);
            }

            foreach ([['inside', 0, 2, 60], ['inside', 2, 5, 80], ['outside', 0, 2, 120], ['outside', 2, 5, 150]] as $r) {
                insert('delivery_rates', ['zone' => $r[0], 'min_kg' => $r[1], 'max_kg' => $r[2], 'charge' => $r[3]]);
            }

            $cat = function (?int $parent, string $name, string $nameBn, string $slug, int $sort, int $soon = 0, string $image = '') use ($now): int {
                return insert('categories', [
                    'parent_id' => $parent, 'name' => $name, 'name_bn' => $nameBn, 'slug' => $slug,
                    'description' => '', 'image' => $image, 'sort_order' => $sort, 'is_active' => 1,
                    'coming_soon' => $soon, 'created_at' => $now,
                ]);
            };
            $women = $cat(null, 'Women Items', 'মহিলাদের পণ্য', 'women-items', 1, 0, 'assets/img/cat-women.svg');
            $baby = $cat(null, 'Baby Toy', 'শিশুদের খেলনা', 'baby-toy', 2, 0, 'assets/img/cat-baby.svg');
            $cat(null, 'Men Items', 'পুরুষদের পণ্য', 'men-items', 3, 1, 'assets/img/cat-men.svg');
            $bag = $cat($women, 'Bag', 'ব্যাগ', 'bag', 1);
            $edu = $cat($baby, 'Educational Toy', 'শিক্ষামূলক খেলনা', 'educational-toy', 1);
            $robot = $cat($baby, 'Robot', 'রোবট', 'robot', 2);
            $soft = $cat($baby, 'Soft Toy', 'নরম খেলনা', 'soft-toy', 3);

            if ($f['sample']) {
                $samples = [
                    [$bag, 'Pink Leather Handbag', 'গোলাপি চামড়ার হ্যান্ডব্যাগ', 'sample-bag-1.svg', 1850, 1590, 12, 0.6, 1, 'Soft faux-leather handbag with a zip top, inside pocket and detachable strap. Fits a phone, wallet and daily essentials.', 'জিপ, ভেতরের পকেট ও খোলা যায় এমন ফিতাসহ নরম ফক্স-লেদার হ্যান্ডব্যাগ। ফোন, ওয়ালেট ও প্রতিদিনের প্রয়োজনীয় জিনিস রাখা যায়।'],
                    [$bag, 'Everyday Tote Bag', 'প্রতিদিনের টোট ব্যাগ', 'sample-bag-2.svg', 1250, null, 20, 0.5, 1, 'Roomy canvas tote for shopping, office or university. Strong handles and an inner zip pocket.', 'বাজার, অফিস বা ইউনিভার্সিটির জন্য বড় ক্যানভাস টোট ব্যাগ। মজবুত হাতল ও ভেতরে জিপ পকেট।'],
                    [$bag, 'Mini Crossbody Bag', 'মিনি ক্রসবডি ব্যাগ', 'sample-bag-3.svg', 990, 850, 3, 0.3, 0, 'Small crossbody bag with an adjustable strap — perfect for outings.', 'অ্যাডজাস্টেবল ফিতাসহ ছোট ক্রসবডি ব্যাগ — বেড়াতে যাওয়ার জন্য দারুণ।'],
                    [$edu, 'Wooden Alphabet Blocks', 'কাঠের বর্ণমালা ব্লক', 'sample-blocks.svg', 750, null, 25, 1.2, 1, 'Set of 26 smooth wooden blocks with letters and pictures. Helps with early reading and motor skills. Age 2+.', 'অক্ষর ও ছবিসহ ২৬টি মসৃণ কাঠের ব্লক। পড়া শেখা ও হাতের দক্ষতায় সাহায্য করে। বয়স ২+।'],
                    [$edu, 'Shape Sorter Toy', 'শেপ সর্টার খেলনা', 'sample-shapes.svg', 620, 550, 15, 0.8, 0, 'Colourful shape sorter that teaches shapes and colours. Non-toxic plastic. Age 1+.', 'রঙিন শেপ সর্টার যা আকার ও রং চিনতে শেখায়। বিষমুক্ত প্লাস্টিক। বয়স ১+।'],
                    [$robot, 'Dancing Robot with Lights', 'লাইটসহ নাচের রোবট', 'sample-robot.svg', 1450, 1290, 8, 0.9, 1, 'Battery-powered robot that dances, plays music and flashes lights. Batteries not included. Age 3+.', 'ব্যাটারিচালিত রোবট যা নাচে, গান বাজায় ও লাইট জ্বলে। ব্যাটারি দেওয়া নেই। বয়স ৩+।'],
                    [$robot, 'Remote Control Robot', 'রিমোট কন্ট্রোল রোবট', 'sample-robot-2.svg', 2350, null, 2, 1.6, 0, 'Walks, turns and talks with a simple remote. Rechargeable. Age 5+.', 'সহজ রিমোট দিয়ে হাঁটে, ঘোরে ও কথা বলে। রিচার্জেবল। বয়স ৫+।'],
                    [$soft, 'Cuddly Teddy Bear', 'নরম টেডি বিয়ার', 'sample-teddy.svg', 890, 790, 18, 0.4, 1, 'Super-soft 30 cm teddy bear, safe stitched eyes. A perfect gift.', 'খুব নরম ৩০ সেমি টেডি বিয়ার, নিরাপদ সেলাই করা চোখ। উপহারের জন্য দারুণ।'],
                ];
                $i = 0;
                foreach ($samples as $s) {
                    $i++;
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s[1]), '-'));
                    $created = date('Y-m-d H:i:s', time() - (count($samples) - $i) * 60);
                    insert('products', [
                        'category_id' => $s[0], 'name' => $s[1], 'name_bn' => $s[2], 'slug' => $slug,
                        'sku' => 'SHF-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                        'description' => $s[9], 'description_bn' => $s[10],
                        'price' => $s[4], 'sale_price' => $s[5], 'quantity' => $s[6], 'weight' => $s[7],
                        'image' => 'assets/img/sample/' . $s[3], 'is_featured' => $s[8], 'is_active' => 1,
                        'created_at' => $created, 'updated_at' => $created,
                    ]);
                }
            }

            $php = "<?php\n// Shifa configuration — created by the installer on " . $now . ".\n// Keep this file private. Do not share it.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configFile, $php) === false) {
                throw new RuntimeException('Could not write includes/config.php — make the "includes" folder writable and try again.');
            }
            @chmod($configFile, 0640);
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Shifa</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="install">
<main class="install-box">
  <img src="assets/img/logo.svg" alt="Shifa" class="install-logo">
<?php if (!empty($done)): ?>
  <h1>Installation complete 🎉</h1>
  <p>Your shop is ready. Next steps:</p>
  <ol>
    <li>Log in to the <a href="admin/">admin panel</a> with <strong><?= h($f['admin_email']) ?></strong>.</li>
    <li>Open <strong>Settings → Email</strong> and enter your email (SMTP) details, then press <em>Send test email</em>.</li>
    <li>Delete <code>install.php</code> from your hosting (it is already locked, this is just extra safety).</li>
  </ol>
  <p><a class="btn" href="admin/">Open admin panel</a> <a class="btn btn-light" href="index.php">View shop</a></p>
<?php else: ?>
  <h1>Install your shop</h1>
  <p class="muted">This takes about one minute. You only do it once.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . h($er) . '</div>'; ?></div>
  <?php endif; ?>

  <details class="checks" <?= in_array(false, $checks, true) ? 'open' : '' ?>>
    <summary>Server check <?= in_array(false, $checks, true) ? '⚠️' : '✅' ?></summary>
    <ul>
      <?php foreach ($checks as $label => $ok): ?>
        <li><?= $ok ? '✅' : '❌' ?> <?= h($label) ?></li>
      <?php endforeach; ?>
      <li><?= $mysqlOk ? '✅' : '❌' ?> MySQL support</li>
      <li><?= $sqliteOk ? '✅' : '❌' ?> SQLite support</li>
    </ul>
  </details>

  <form method="post" class="form" autocomplete="off">
    <fieldset>
      <legend>1. Database</legend>
      <div class="radio-row">
        <label><input type="radio" name="driver" value="mysql" <?= $f['driver'] === 'mysql' ? 'checked' : '' ?> <?= $mysqlOk ? '' : 'disabled' ?>> MySQL (recommended — from cPanel)</label>
        <label><input type="radio" name="driver" value="sqlite" <?= $f['driver'] === 'sqlite' ? 'checked' : '' ?> <?= $sqliteOk ? '' : 'disabled' ?>> SQLite (no setup, good for small shops)</label>
      </div>
      <div class="mysql-fields grid2">
        <label>Database host<input name="db_host" value="<?= h($f['db_host']) ?>"></label>
        <label>Port<input name="db_port" value="<?= h($f['db_port']) ?>" inputmode="numeric"></label>
        <label>Database name<input name="db_name" value="<?= h($f['db_name']) ?>"></label>
        <label>Database user<input name="db_user" value="<?= h($f['db_user']) ?>"></label>
        <label class="span2">Database password<input type="password" name="db_pass" value="<?= h($f['db_pass']) ?>"></label>
      </div>
    </fieldset>
    <fieldset>
      <legend>2. Shop</legend>
      <label>Shop name<input name="store_name" value="<?= h($f['store_name']) ?>" required></label>
      <label>Email for new-order notifications<input type="email" name="notify_email" value="<?= h($f['notify_email']) ?>" placeholder="you@example.com"></label>
      <label class="check"><input type="checkbox" name="sample" <?= $f['sample'] ? 'checked' : '' ?>> Add a few sample products so I can see how the shop looks (you can delete them later)</label>
    </fieldset>
    <fieldset>
      <legend>3. Your admin login</legend>
      <label>Your name<input name="admin_name" value="<?= h($f['admin_name']) ?>" required></label>
      <label>Admin email<input type="email" name="admin_email" value="<?= h($f['admin_email']) ?>" required></label>
      <div class="grid2">
        <label>Password (8+ characters)<input type="password" name="admin_pass" minlength="8" required></label>
        <label>Repeat password<input type="password" name="admin_pass2" minlength="8" required></label>
      </div>
    </fieldset>
    <button class="btn btn-block" type="submit">Install Shifa</button>
  </form>
  <script>
    (function () {
      var radios = document.querySelectorAll('input[name=driver]');
      var box = document.querySelector('.mysql-fields');
      function sync() { box.style.display = document.querySelector('input[name=driver]:checked').value === 'mysql' ? '' : 'none'; }
      radios.forEach(function (r) { r.addEventListener('change', sync); });
      sync();
    })();
  </script>
<?php endif; ?>
</main>
</body>
</html>
