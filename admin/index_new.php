<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';

Auth::startSession();

// إذا كان مسجل دخول بالفعل، توجه للوحة التحكم
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

// إذا لم يكن مسجل دخول، توجه لصفحة تسجيل الدخول الجديدة
header('Location: /admin/login_new.php');
exit;
?>

