<?php
require __DIR__ . '/_layout.php';
if (is_post()) {
    csrf_check();
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
redirect('admin/login.php');
