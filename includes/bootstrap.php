<?php
/**
 * Loaded at the top of every page. Sets up config, database, session and helpers.
 */
declare(strict_types=1);

define('SHIFA_ROOT', dirname(__DIR__));
define('SHIFA_VERSION', '1.0.0');

date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

$configFile = SHIFA_ROOT . '/includes/config.php';
if (!is_file($configFile)) {
    // Not installed yet — send the visitor to the installer.
    $depth = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../' : '';
    header('Location: ' . $depth . 'install.php');
    exit;
}
$CONFIG = require $configFile;

if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require_once SHIFA_ROOT . '/includes/db.php';
require_once SHIFA_ROOT . '/includes/functions.php';
require_once SHIFA_ROOT . '/includes/mailer.php';
require_once SHIFA_ROOT . '/includes/emails.php';

// Secure session
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('shifa_sess');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

db_connect($CONFIG);
load_language();
