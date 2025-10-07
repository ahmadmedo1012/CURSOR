<?php
require_once '../config/config.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

// إنهاء جلسة الإدارة
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_user']);

header('Location: /admin/login.php?logged_out=1');
exit;
?>
