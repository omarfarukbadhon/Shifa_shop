<?php
/**
 * Shared helper functions.
 */

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    if ($key === '__reset__') {
        $cache = null;
        return null;
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '' ? $cache[$key] : $default;
}

function save_setting(string $key, $value): void
{
    $exists = val('SELECT COUNT(*) FROM settings WHERE k = ?', [$key]);
    if ($exists) {
        q('UPDATE settings SET v = ? WHERE k = ?', [(string)$value, $key]);
    } else {
        q('INSERT INTO settings (k, v) VALUES (?, ?)', [$key, (string)$value]);
    }
    setting('__reset__');
}

/* ------------------------------------------------------------------ */
/* Output, URLs                                                        */
/* ------------------------------------------------------------------ */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    global $CONFIG;
    $b = $CONFIG['base_path'] ?? '/';
    return '/' . trim($b, '/') . (trim($b, '/') === '' ? '' : '/');
}

function url(string $path = ''): string
{
    return base_path() . ltrim($path, '/');
}

function site_origin(): string
{
    global $CONFIG;
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    }
    $u = parse_url($CONFIG['site_url'] ?? 'http://localhost');
    return ($u['scheme'] ?? 'http') . '://' . ($u['host'] ?? 'localhost') . (isset($u['port']) ? ':' . $u['port'] : '');
}

function full_url(string $path = ''): string
{
    return site_origin() . url($path);
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~', $path) && ($path === '' || $path[0] !== '/')) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

/** Only allow redirects back into this site. */
function safe_return(?string $r, string $fallback = 'index.php'): string
{
    $r = (string)$r;
    if ($r === '' || !preg_match('~^[A-Za-z0-9_\-./?=&%]+$~', $r) || strpos($r, '//') !== false || strpos($r, '..') !== false) {
        return $fallback;
    }
    return ltrim($r, '/');
}

function img_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    return url($path ? $path : $fallback);
}

function logo_url(): string
{
    return url(setting('logo', 'assets/img/logo.svg'));
}

/* ------------------------------------------------------------------ */
/* Language                                                            */
/* ------------------------------------------------------------------ */

function load_language(): void
{
    global $LANG, $LANG_CODE;
    $code = $_SESSION['lang'] ?? ($_COOKIE['shifa_lang'] ?? 'en');
    if (!in_array($code, ['en', 'bn'], true)) {
        $code = 'en';
    }
    $LANG_CODE = $code;
    $LANG = require SHIFA_ROOT . '/includes/lang/' . $code . '.php';
    if ($code !== 'en') {
        $LANG = $LANG + (require SHIFA_ROOT . '/includes/lang/en.php');
    }
}

function lang(): string
{
    global $LANG_CODE;
    return $LANG_CODE ?? 'en';
}

function t(string $key, array $vars = []): string
{
    global $LANG;
    $s = $LANG[$key] ?? $key;
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

/** Translated database field, e.g. loc($product, 'name') uses name_bn in Bangla when filled. */
function loc(array $row, string $field): string
{
    if (lang() === 'bn' && !empty($row[$field . '_bn'])) {
        return (string)$row[$field . '_bn'];
    }
    return (string)($row[$field] ?? '');
}

function bn_digits(string $s): string
{
    return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function num($n, int $dec = 0): string
{
    $s = number_format((float)$n, $dec);
    return lang() === 'bn' ? bn_digits($s) : $s;
}

function money($amount, ?bool $bangla = null): string
{
    $amount = (float)$amount;
    $dec = (abs($amount - round($amount)) > 0.001) ? 2 : 0;
    $s = number_format($amount, $dec);
    if ($bangla ?? (lang() === 'bn')) {
        $s = bn_digits($s);
    }
    return '৳' . $s;
}

/* ------------------------------------------------------------------ */
/* Security: CSRF, flash, rate limiting                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '" role="status">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function too_many_attempts(string $kind, int $max = 8, int $minutes = 15): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    return (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND kind = ? AND created_at > ?', [client_ip(), $kind, $since]) >= $max;
}

function record_attempt(string $kind): void
{
    insert('login_attempts', ['ip' => client_ip(), 'kind' => $kind, 'created_at' => now()]);
    if (random_int(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function clear_attempts(string $kind): void
{
    q('DELETE FROM login_attempts WHERE ip = ? AND kind = ?', [client_ip(), $kind]);
}

/* ------------------------------------------------------------------ */
/* Auth                                                                */
/* ------------------------------------------------------------------ */

function current_customer(): ?array
{
    static $c = false;
    if ($c === false) {
        $c = !empty($_SESSION['customer_id']) ? row('SELECT * FROM customers WHERE id = ?', [$_SESSION['customer_id']]) : null;
    }
    return $c;
}

function require_customer(string $returnTo = ''): array
{
    $c = current_customer();
    if (!$c) {
        flash('info', t('login_required'));
        $r = $returnTo !== '' ? $returnTo : ltrim(substr($_SERVER['REQUEST_URI'] ?? '', strlen(base_path())), '/');
        redirect('login.php?r=' . urlencode($r));
    }
    return $c;
}

function login_customer(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
}

function current_admin(): ?array
{
    static $a = false;
    if ($a === false) {
        $a = !empty($_SESSION['admin_id']) ? row('SELECT * FROM admins WHERE id = ?', [$_SESSION['admin_id']]) : null;
    }
    return $a;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) {
        redirect('admin/login.php');
    }
    return $a;
}

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */

function clean(string $key, int $max = 500): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}

function valid_email(string $e): bool
{
    return (bool)filter_var($e, FILTER_VALIDATE_EMAIL);
}

/** Bangladeshi mobile numbers: 01XXXXXXXXX, +8801XXXXXXXXX or 8801XXXXXXXXX. Returns 01XXXXXXXXX or ''. */
function normalize_phone(string $p): string
{
    $p = strtr($p, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    $p = preg_replace('/[^0-9]/', '', $p);
    if (strpos($p, '880') === 0) {
        $p = substr($p, 2);
    }
    return preg_match('/^01[3-9][0-9]{8}$/', $p) ? $p : '';
}

function slugify(string $s): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $slug !== '' ? $slug : 'item';
}

function unique_slug(string $table, string $name, int $ignoreId = 0): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    while (val("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?", [$slug, $ignoreId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

/* ------------------------------------------------------------------ */
/* Image uploads                                                       */
/* ------------------------------------------------------------------ */

/**
 * Validate, resize and store one uploaded image. Returns the relative path (e.g. uploads/products/abc.jpg).
 * Throws RuntimeException with a readable message on failure.
 */
function save_uploaded_image(array $file, string $subdir = 'products', int $maxSide = 1200): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int)($file['error'] ?? 0) . '). The file may be too large.');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than 8 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }
    $ext = $types[$info[2]];
    $dir = SHIFA_ROOT . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create the uploads folder. Check folder permissions.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;

    [$w, $h] = $info;
    $resized = false;
    if ($ext !== 'gif' && function_exists('imagecreatetruecolor') && ($w > $maxSide || $h > $maxSide)) {
        $src = null;
        if ($ext === 'jpg') {
            $src = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($ext === 'png') {
            $src = @imagecreatefrompng($file['tmp_name']);
        } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($file['tmp_name']);
        }
        if ($src) {
            $ratio = min($maxSide / $w, $maxSide / $h);
            $nw = (int)round($w * $ratio);
            $nh = (int)round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if ($ext === 'jpg') {
                $resized = imagejpeg($dst, $dest, 85);
            } elseif ($ext === 'png') {
                $resized = imagepng($dst, $dest, 6);
            } else {
                $resized = imagewebp($dst, $dest, 85);
            }
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
    if (!$resized && !move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the image. Check folder permissions.');
    }
    @chmod($dest, 0644);
    return 'uploads/' . $subdir . '/' . $name;
}

/** Normalise the PHP $_FILES array for a multiple input (name="images[]"). */
function files_array(string $key): array
{
    $out = [];
    if (empty($_FILES[$key]) || !is_array($_FILES[$key]['name'])) {
        return $out;
    }
    foreach ($_FILES[$key]['name'] as $i => $n) {
        if ($_FILES[$key]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $n,
            'type' => $_FILES[$key]['type'][$i],
            'tmp_name' => $_FILES[$key]['tmp_name'][$i],
            'error' => $_FILES[$key]['error'][$i],
            'size' => $_FILES[$key]['size'][$i],
        ];
    }
    return $out;
}

function delete_upload(?string $path): void
{
    if ($path && strpos($path, 'uploads/') === 0 && strpos($path, '..') === false) {
        @unlink(SHIFA_ROOT . '/' . $path);
    }
}

/* ------------------------------------------------------------------ */
/* Catalogue                                                           */
/* ------------------------------------------------------------------ */

function main_categories(bool $activeOnly = true): array
{
    return rows('SELECT * FROM categories WHERE parent_id IS NULL' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name');
}

function sub_categories(int $parentId, bool $activeOnly = true): array
{
    return rows('SELECT * FROM categories WHERE parent_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name', [$parentId]);
}

/** IDs of a category plus all of its sub-categories. */
function category_tree_ids(int $id, bool $activeOnly = true): array
{
    $ids = [$id];
    foreach (sub_categories($id, $activeOnly) as $s) {
        $ids[] = (int)$s['id'];
    }
    return $ids;
}

function effective_price(array $p): float
{
    $sale = $p['sale_price'] !== null && $p['sale_price'] !== '' ? (float)$p['sale_price'] : 0.0;
    return ($sale > 0 && $sale < (float)$p['price']) ? $sale : (float)$p['price'];
}

function on_sale(array $p): bool
{
    return effective_price($p) < (float)$p['price'];
}

function discount_percent(array $p): int
{
    if (!on_sale($p) || (float)$p['price'] <= 0) {
        return 0;
    }
    return (int)round((1 - effective_price($p) / (float)$p['price']) * 100);
}

function product_card_sql(): string
{
    return 'SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id
            LEFT JOIN categories pc ON pc.id = c.parent_id
            WHERE p.is_active = 1 AND c.is_active = 1 AND (pc.id IS NULL OR pc.is_active = 1)';
}

/* ------------------------------------------------------------------ */
/* Cart                                                                */
/* ------------------------------------------------------------------ */

function cart_raw(): array
{
    return isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }
    $_SESSION['cart'][$productId] = min($qty, 999);
}

function cart_add(int $productId, int $qty = 1): void
{
    $current = cart_raw()[$productId] ?? 0;
    cart_set($productId, $current + max(1, $qty));
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_count(): int
{
    return array_sum(cart_raw());
}

/**
 * Cart lines with live product data. Quantities are capped by available stock;
 * missing/inactive products are dropped.
 */
function cart_lines(?bool &$changed = null): array
{
    $changed = false;
    $raw = cart_raw();
    if (!$raw) {
        return [];
    }
    $ids = array_map('intval', array_keys($raw));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $products = [];
    foreach (rows("SELECT * FROM products WHERE is_active = 1 AND id IN ($ph)", $ids) as $p) {
        $products[(int)$p['id']] = $p;
    }
    $lines = [];
    foreach ($raw as $pid => $qty) {
        $pid = (int)$pid;
        if (!isset($products[$pid]) || (int)$products[$pid]['quantity'] <= 0) {
            unset($_SESSION['cart'][$pid]);
            $changed = true;
            continue;
        }
        $p = $products[$pid];
        if ($qty > (int)$p['quantity']) {
            $qty = (int)$p['quantity'];
            $_SESSION['cart'][$pid] = $qty;
            $changed = true;
        }
        $price = effective_price($p);
        $lines[] = [
            'product' => $p,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $price * $qty,
            'weight' => (float)$p['weight'] * $qty,
        ];
    }
    return $lines;
}

function cart_totals(array $lines): array
{
    $sub = 0.0;
    $w = 0.0;
    foreach ($lines as $l) {
        $sub += $l['line_total'];
        $w += $l['weight'];
    }
    return ['subtotal' => $sub, 'weight' => $w];
}

/* ------------------------------------------------------------------ */
/* Delivery charge                                                     */
/* ------------------------------------------------------------------ */

function delivery_zones(): array
{
    return ['inside' => t('inside_dhaka'), 'outside' => t('outside_dhaka')];
}

function delivery_rates(string $zone): array
{
    return rows('SELECT * FROM delivery_rates WHERE zone = ? ORDER BY min_kg, max_kg', [$zone]);
}

/** Weight-slab based delivery charge. */
function delivery_charge(string $zone, float $weight, float $subtotal = 0.0): float
{
    $free = (float)setting('free_delivery_over', '0');
    if ($free > 0 && $subtotal >= $free) {
        return 0.0;
    }
    $rates = delivery_rates($zone);
    if (!$rates) {
        return 0.0;
    }
    $weight = round(max(0, $weight), 3);
    foreach ($rates as $r) {
        if ($weight >= (float)$r['min_kg'] && $weight <= (float)$r['max_kg']) {
            return (float)$r['charge'];
        }
    }
    // Heavier than the last slab: last slab price + extra per (started) kg
    $last = end($rates);
    if ($weight > (float)$last['max_kg']) {
        $extra = (float)setting('extra_per_kg_' . $zone, '0');
        return (float)$last['charge'] + ceil($weight - (float)$last['max_kg']) * $extra;
    }
    // Falls in a gap between slabs: use the next slab up
    foreach ($rates as $r) {
        if ($weight < (float)$r['min_kg']) {
            return (float)$r['charge'];
        }
    }
    return (float)$last['charge'];
}

/** Data for the live delivery calculator in the browser. */
function delivery_js_data(): array
{
    return [
        'inside' => array_map(fn($r) => [(float)$r['min_kg'], (float)$r['max_kg'], (float)$r['charge']], delivery_rates('inside')),
        'outside' => array_map(fn($r) => [(float)$r['min_kg'], (float)$r['max_kg'], (float)$r['charge']], delivery_rates('outside')),
        'extra' => ['inside' => (float)setting('extra_per_kg_inside', '0'), 'outside' => (float)setting('extra_per_kg_outside', '0')],
        'free_over' => (float)setting('free_delivery_over', '0'),
    ];
}

/* ------------------------------------------------------------------ */
/* Orders                                                              */
/* ------------------------------------------------------------------ */

function order_statuses(): array
{
    return ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
}

function status_label(string $s): string
{
    return t('status_' . $s);
}

/** Serial like SHF261017001 = SHF + YYMMDD + daily number (12 characters). */
function generate_serial(): string
{
    $prefix = 'SHF' . date('ymd');
    $last = val('SELECT serial FROM orders WHERE serial LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $n = $last ? ((int)substr($last, strlen($prefix))) + 1 : 1;
    return $prefix . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

function order_items(int $orderId): array
{
    return rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function log_stock(int $productId, int $change, int $after, string $reason): void
{
    insert('stock_log', [
        'product_id' => $productId,
        'change_qty' => $change,
        'qty_after' => $after,
        'reason' => mb_substr($reason, 0, 100),
        'created_at' => now(),
    ]);
}

/** Put the stock of a cancelled order back. */
function restock_order(int $orderId, string $serial): void
{
    foreach (order_items($orderId) as $it) {
        if ($it['product_id']) {
            q('UPDATE products SET quantity = quantity + ? WHERE id = ?', [(int)$it['quantity'], (int)$it['product_id']]);
            $after = (int)val('SELECT quantity FROM products WHERE id = ?', [(int)$it['product_id']]);
            log_stock((int)$it['product_id'], (int)$it['quantity'], $after, 'Order ' . $serial . ' cancelled');
        }
    }
}

/** Take stock again (when a cancelled order is re-opened). Returns false if not enough stock. */
function unrestock_order(int $orderId, string $serial): bool
{
    $items = order_items($orderId);
    db()->beginTransaction();
    try {
        foreach ($items as $it) {
            if (!$it['product_id']) {
                continue;
            }
            $ok = q('UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?', [(int)$it['quantity'], (int)$it['product_id'], (int)$it['quantity']])->rowCount();
            if (!$ok) {
                db()->rollBack();
                return false;
            }
            $after = (int)val('SELECT quantity FROM products WHERE id = ?', [(int)$it['product_id']]);
            log_stock((int)$it['product_id'], -(int)$it['quantity'], $after, 'Order ' . $serial . ' re-opened');
        }
        db()->commit();
        return true;
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/* ------------------------------------------------------------------ */
/* Pagination                                                          */
/* ------------------------------------------------------------------ */

function page_num(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}

function pagination(int $total, int $perPage, int $page): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $params = $_GET;
    $html = '<nav class="pagination" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 9 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 2) {
            if ($i === 3 || $i === $pages - 2) {
                $html .= '<span class="dots">…</span>';
            }
            continue;
        }
        $params['page'] = $i;
        $cls = $i === $page ? ' class="active" aria-current="page"' : '';
        $html .= '<a' . $cls . ' href="?' . e(http_build_query($params)) . '">' . num($i) . '</a>';
    }
    return $html . '</nav>';
}

/* ------------------------------------------------------------------ */
/* Marketing                                                           */
/* ------------------------------------------------------------------ */

function unsubscribe_token(string $email): string
{
    global $CONFIG;
    return substr(hash_hmac('sha256', strtolower($email), $CONFIG['secret']), 0, 32);
}

function unsubscribe_url(string $email): string
{
    return full_url('unsubscribe.php?e=' . urlencode($email) . '&t=' . unsubscribe_token($email));
}

/* ------------------------------------------------------------------ */
/* Password reset tokens                                               */
/* ------------------------------------------------------------------ */

function create_reset_token(string $type, int $userId): string
{
    q('UPDATE password_resets SET used = 1 WHERE user_type = ? AND user_id = ?', [$type, $userId]);
    $token = bin2hex(random_bytes(32));
    insert('password_resets', [
        'user_type' => $type,
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'used' => 0,
    ]);
    return $token;
}

function find_reset_token(string $type, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return row('SELECT * FROM password_resets WHERE user_type = ? AND token_hash = ? AND used = 0 AND expires_at > ?', [$type, hash('sha256', $token), now()]);
}
