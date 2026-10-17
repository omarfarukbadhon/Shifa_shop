<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
    flash('success', t('logged_out'));
}
redirect('index.php');
