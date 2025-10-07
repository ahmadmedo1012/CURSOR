<?php
// Base paths
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// إعدادات التطبيق
define('APP_NAME', 'GameBox — مركز أحمد للهاتف المحمول');
// تفعيل تحسينات الأداء لكل الزوار
if (!defined('ENABLE_PERF_TUNING')) {
    define('ENABLE_PERF_TUNING', true);
}
// Auto-detect base URL
function get_base_url()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $port = $_SERVER['SERVER_PORT'] ?? '';

    // Remove port if it's default
    if (($protocol === 'http' && $port === '80') || ($protocol === 'https' && $port === '443')) {
        $port = '';
    } else if ($port) {
        $port = ':' . $port;
    }

    return $protocol . '://' . $host . $port;
}

define('APP_URL', get_base_url());

// Asset URL helper for admin subfolder compatibility
function asset_url($path)
{
    $baseUrl = rtrim(APP_URL, '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

// Inline SVG helper
function inline_svg($path)
{
    $fullPath = BASE_PATH . '/' . ltrim($path, '/');
    if (file_exists($fullPath)) {
        return file_get_contents($fullPath);
    }
    return '';
}
define('BRAND_PRIMARY', '#1A3C8C'); // Royal Blue
define('BRAND_ACCENT', '#C9A227'); // Gold
define('DEV_MODE', false);

// إعدادات Peakerr API
define('PEAKERR_API_URL', 'https://mohammedstore.com/api/v2');
define('PEAKERR_API_KEY', 'Ox1yuOnDlJWTJc1gzQBJnLZsICmCES81hPtBICuKUxAITuVQS0anOlxv9D8o');

// إعدادات قاعدة البيانات MySQL
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_39751309_da3bool');
define('DB_USER', 'if0_39751309');
define('DB_PASS', 'jcLCAdbRmjLE38');

// إعدادات إضافية
define('TIMEZONE', 'Africa/Tripoli');
date_default_timezone_set(TIMEZONE);

// إعدادات التواصل
define('WHATSAPP_NUMBER', '218912345678'); // رقم واتساب للدعم الفني

// Load formatting helpers
require_once BASE_PATH . '/src/Utils/formatters.php';

// Error handling - hide stack traces in production
if (!DEV_MODE) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Load error handler
require_once BASE_PATH . '/src/Utils/error_handler.php';
ErrorHandler::setupGlobalHandlers();

// سعر الصرف USD إلى LYD
define('EXCHANGE_USD_TO_LYD', 12); // 1 USD = 12 LYD

// إعدادات الإدارة (Basic Auth)
define('ADMIN_USER', 'admin'); // يمكن تغييرها
define('ADMIN_PASS', 'admin123'); // يجب تغييرها في الإنتاج - استخدم كلمة مرور قوية!

// مفاتيح تشخيص API
define('API_TIMEOUT', 60);
define('DEBUG_API', DEV_MODE); // Only debug in development
