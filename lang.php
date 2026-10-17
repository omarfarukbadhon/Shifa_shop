<?php
require __DIR__ . '/includes/bootstrap.php';
$l = ($_GET['l'] ?? 'en') === 'bn' ? 'bn' : 'en';
$_SESSION['lang'] = $l;
setcookie('shifa_lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
redirect(safe_return($_GET['r'] ?? '', 'index.php'));
