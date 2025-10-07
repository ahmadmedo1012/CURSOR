<?php
if (!defined('ENABLE_PERF_TUNING')) {
    define('ENABLE_PERF_TUNING', false);
}
// إعداد headers لمنع التخزين المؤقت للصفحات التي تحتوي على معلومات المصادقة
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// تضمين auth.php لبدء الجلسة
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Components/NotificationDisplay.php';
Auth::startSession();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="is-preload">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'منصة شاملة لخدمات وسائل التواصل الاجتماعي والألعاب - متابعين، إعجابات، مشاهدات، عملات الألعاب وأكثر'; ?>">
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">

    <!-- Open Graph tags -->
    <meta property="og:title" content="<?php echo isset($pageTitle) ? htmlspecialchars($pageTitle . ' - ' . APP_NAME) : htmlspecialchars(APP_NAME); ?>">
    <meta property="og:description" content="<?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'منصة شاملة لخدمات وسائل التواصل الاجتماعي والألعاب - متابعين، إعجابات، مشاهدات، عملات الألعاب وأكثر'; ?>">
    <meta property="og:type" content="<?php echo isset($ogType) ? $ogType : 'website'; ?>">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:site_name" content="<?php echo APP_NAME; ?>">
    <meta property="og:locale" content="ar_LY">
    <?php if (isset($ogImage)): ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
        <meta property="og:image:alt" content="<?php echo htmlspecialchars($pageTitle ?? APP_NAME); ?>">
    <?php endif; ?>

    <!-- Twitter Card tags -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo isset($pageTitle) ? htmlspecialchars($pageTitle . ' - ' . APP_NAME) : htmlspecialchars(APP_NAME); ?>">
    <meta name="twitter:description" content="<?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'منصة شاملة لخدمات وسائل التواصل الاجتماعي والألعاب - متابعين، إعجابات، مشاهدات، عملات الألعاب وأكثر'; ?>">
    <?php if (isset($ogImage)): ?>
        <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>

    <!-- Critical CSS - preload then stylesheet for anti-FOUC -->
    <link rel="preload" as="style" href="<?php echo asset_url('assets/css/site.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">

    <!-- UX Tweaks CSS - Loaded after existing CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/ux.tweaks.css'); ?>">

    <!-- DNS prefetch for external domains -->
    <link rel="dns-prefetch" href="https://wa.me">

    <!-- Preconnect to external domains -->

    <!-- Prefetch non-critical assets -->
    <link rel="prefetch" href="<?php echo asset_url('assets/js/main.min.js'); ?>">
    <link rel="prefetch" href="<?php echo asset_url('assets/js/instant-nav.js'); ?>">
    <link rel="prefetch" href="<?php echo asset_url('assets/js/perf.js'); ?>">

    <!-- Prefetch likely next page resources -->
    <link rel="prefetch" href="/catalog.php">
    <link rel="prefetch" href="/auth/login.php">

    <!-- Critical JS (navigation, theme) - defer for non-blocking -->
    <script src="<?php echo asset_url('assets/js/app.min.js'); ?>" defer></script>

    <!-- UX Tweaks JS - Loaded with defer for non-blocking -->
    <script src="<?php echo asset_url('assets/js/ux.tweaks.js'); ?>" defer></script>

    <!-- Theme initialization -->
    <script>
        // Apply theme immediately
        (function() {
            try {
                var saved = localStorage.getItem('theme');
                var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = saved || (prefersLight ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);

                // Update theme-color meta tag
                var metaThemeColor = document.querySelector('meta[name="theme-color"]');
                if (metaThemeColor) {
                    metaThemeColor.setAttribute('content', theme === 'light' ? '#F7F9FD' : '#1A3C8C');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- PWA Manifest -->
    <!-- <link rel="manifest" href="/manifest.json"> -->
    <meta name="theme-color" content="#1A3C8C">

    <!-- JSON-LD Organization Schema -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "<?php echo APP_NAME; ?>",
            "description": "منصة شاملة لخدمات وسائل التواصل الاجتماعي والألعاب",
            "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>",
            "sameAs": [],
            "contactPoint": {
                "@type": "ContactPoint",
                "contactType": "customer service",
                "availableLanguage": "Arabic"
            }
        }
    </script>

    <?php if (isset($service) && is_array($service)): ?>
        <!-- JSON-LD Product Schema for Service -->
        <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Product",
                "name": "<?php echo htmlspecialchars($service['name_ar'] ?? $service['name']); ?>",
                "description": "<?php echo htmlspecialchars($service['description_ar'] ?? $service['description'] ?? 'خدمة ' . ($service['name_ar'] ?? $service['name'])); ?>",
                "category": "<?php echo htmlspecialchars($service['category_ar'] ?? $service['category']); ?>",
                "offers": {
                    "@type": "Offer",
                    "price": "<?php echo number_format(floatval($service['rate_per_1k_lyd'] ?? ($service['rate_per_1k'] * EXCHANGE_USD_TO_LYD)), 2); ?>",
                    "priceCurrency": "LYD",
                    "availability": "https://schema.org/InStock",
                    "priceSpecification": {
                        "@type": "UnitPriceSpecification",
                        "price": "<?php echo number_format(floatval($service['rate_per_1k_lyd'] ?? ($service['rate_per_1k'] * EXCHANGE_USD_TO_LYD)), 2); ?>",
                        "priceCurrency": "LYD",
                        "unitText": "per 1000"
                    }
                },
                "aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": "4.8",
                    "reviewCount": "150"
                }
            }
        </script>
    <?php endif; ?>
</head>

<body>
    <!-- Loading Screen -->
    <div id="loader">
        <div class="pulse"></div>
    </div>

    <!-- Top Actions Bar -->
    <div class="top-bar">
        <div class="container">
            <nav class="top-actions" dir="rtl">
                <!-- التبويبات الجديدة -->
                <nav class="top-tabs" dir="rtl">
                    <!-- Theme toggle -->
                    <button type="button" class="top-tab" data-theme-toggle aria-label="تبديل الثيم">
                        <span class="icon"><?php echo inline_svg('/assets/svg/theme.svg'); ?></span>
                        <span class="label">الثيم</span>
                    </button>

                    <!-- Home -->
                    <a href="/" class="top-tab" data-route="home" aria-label="الرئيسية">
                        <span class="icon"><?php echo inline_svg('/assets/svg/home.svg'); ?></span>
                        <span class="label">الرئيسية</span>
                    </a>

                    <!-- Leaderboard -->
                    <a href="/leaderboard.php" class="top-tab" data-route="leaderboard" aria-label="المتصدرون">
                        <span class="icon"><?php echo inline_svg('/assets/svg/trophy.svg'); ?></span>
                        <span class="label">المتصدرون</span>
                    </a>
                </nav>

                <!-- الخدمات -->
                <a href="/catalog.php" class="top-action" data-services aria-label="الخدمات">
                    <span class="icon"><?php echo inline_svg('/assets/svg/grid.svg'); ?></span>
                    <span class="label">الخدمات</span>
                </a>

                <?php
                // التحقق من تسجيل الدخول
                $user = Auth::currentUser();
                $isLoggedIn = Auth::is_logged_in();
                ?>

                <?php if ($isLoggedIn): ?>
                    <!-- المحفظة + الرصيد (للمستخدمين المسجلين فقط) -->
                    <a href="/wallet/" class="top-action" data-wallet aria-label="المحفظة">
                        <span class="icon"><?php echo inline_svg('/assets/svg/wallet.svg'); ?></span>
                        <span class="label">المحفظة</span>
                        <span class="badge" id="wallet-balance" aria-live="polite" aria-atomic="true">LYD —</span>
                    </a>

                    <!-- اسم المستخدم -->
                    <a href="/account/" class="top-action" data-account aria-label="حسابي">
                        <span class="icon"><?php echo inline_svg('/assets/svg/user.svg'); ?></span>
                        <span class="label"><?php echo htmlspecialchars($user['name']); ?></span>
                    </a>

                    <!-- تسجيل الخروج -->
                    <a href="/auth/logout.php" class="top-action" data-logout aria-label="تسجيل الخروج">
                        <span class="icon"><?php echo inline_svg('/assets/svg/exit.svg'); ?></span>
                        <span class="label">خروج</span>
                    </a>
                <?php else: ?>
                    <!-- أزرار الدخول والتسجيل (للزوار فقط) -->
                    <a href="/auth/login.php" class="top-action" data-login aria-label="تسجيل الدخول">
                        <span class="icon"><?php echo inline_svg('/assets/svg/login.svg'); ?></span>
                        <span class="label">دخول</span>
                    </a>
                    <a href="/auth/register.php" class="top-action" data-register aria-label="إنشاء حساب">
                        <span class="icon"><?php echo inline_svg('/assets/svg/plus.svg'); ?></span>
                        <span class="label">حساب جديد</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <main id="main-content" role="main">

        <?php
        // عرض الإشعارات (مع حماية من الأخطاء)
        try {
            $currentPage = basename($_SERVER['PHP_SELF']);
            $targetAudience = isset($_SESSION['user_id']) ? 'logged_in' : 'guests';
            echo NotificationDisplay::render($targetAudience, $currentPage);
            echo NotificationDisplay::getCSS();
            echo NotificationDisplay::getJavaScript();
        } catch (Exception $e) {
            // تجاهل أخطاء الإشعارات لمنع توقف تحميل الصفحة
            error_log("Notification loading error: " . $e->getMessage());
        }
        ?>

        <!-- Mobile Sidebar (moved to body level for full-screen positioning) -->