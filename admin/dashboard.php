<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Utils/db.php';
require_once __DIR__ . '/../src/Services/StatsService.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'لوحة التحكم الرئيسية';
$pageDescription = 'لوحة تحكم الإدارة - إحصائيات شاملة ومراقبة النظام';
$ogType = 'website';
$period = $_GET['period'] ?? '30';

// جلب الإحصائيات
$stats = StatsService::getComprehensiveReport($period);

// Check if admin session exists
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin) {
    include __DIR__ . '/../templates/partials/header.php';
} else {
    // Admin-specific header with performance optimizations
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    
    <!-- Preload critical fonts -->
    <link rel="preload" href="<?php echo asset_url('assets/fonts/inter-var.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    
    <!-- Critical CSS inline -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --text-primary: #212529;
            --card-bg: #ffffff;
            --color-elev: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            background: var(--color-elev);
            font-display: swap;
        }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-topbar { 
            background: var(--card-bg); 
            border-bottom: 1px solid var(--border-color); 
            padding: 1rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .btn { 
            padding: 0.5rem 1rem; 
            border: 1px solid transparent; 
            border-radius: 6px; 
            background: var(--card-bg); 
            color: var(--text-primary); 
            text-decoration: none; 
            cursor: pointer; 
            transition: all 0.3s ease; 
        }
        
        .btn:hover, .btn:focus-visible { 
            outline: 2px solid var(--primary-color); 
            outline-offset: 2px; 
        }
        
        @media (max-width: 430px) {
            .admin-topbar { padding: 0.5rem; }
        }
    </style>
    
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle . ' - ' . APP_NAME) : htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
</head>
<body>
<?php } ?>

<!-- Include admin shell partial -->
<?php if ($isAdmin): ?>
<style>
/* Include admin shell styles from index.php */
.admin-wrapper {
    display: flex;
    min-height: 100vh;
    position: relative;
}

.admin-topbar {
    position: fixed;
    top: 0;
    inset-inline-start: 0;
    right: 0;
    height: 64px;
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
    backdrop-filter: blur(20px);
    z-index: 1000;
    display: flex;
    align-items: center;
    padding: 0 20px;
    gap: 1rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.admin-content {
    margin-top: 64px;
    margin-right: 0;
    min-height: calc(100vh - 64px);
    transition: margin-right 0.3s ease;
    padding: 20px;
}
</style>

<div class="admin-wrapper">
    <header class="admin-topbar">
        <button type="button" onclick="location.href='/admin/'" class="btn btn-sm">← العودة للصفحة الرئيسية</button>
        <div style="flex: 1; text-align: center; font-weight: 700; color: var(--primary-color);">
            <?php echo APP_NAME; ?> - لوحة التحكم
        </div>
    </header>
    
    <main class="admin-content">
<?php endif; ?>

<div class="<?php echo !$isAdmin ? 'container-fluid' : ''; ?>">
    <!-- شريط التنقل العلوي -->
    <div class="admin-topbar">
        <div class="admin-topbar-left">
            <h1 class="admin-title">لوحة التحكم</h1>
            <p class="admin-subtitle">مرحباً بك في مركز إدارة GameBox</p>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-period-selector">
                <label>الفترة الزمنية:</label>
                <select id="period-selector" class="form-control">
                    <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>آخر 7 أيام</option>
                    <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>آخر 30 يوم</option>
                    <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>آخر 3 أشهر</option>
                </select>
            </div>
            <div class="admin-user-info">
                <span class="admin-user-name"><?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'مدير'); ?></span>
                <a href="/admin/logout.php" class="btn btn-sm btn-outline">تسجيل الخروج</a>
            </div>
        </div>
    </div>

    <!-- بطاقات الإحصائيات الرئيسية -->
    <div class="stats-grid">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo Formatters::formatQuantity($stats['general']['users']['total']); ?></h3>
                <p class="stat-label">إجمالي المستخدمين</p>
                <div class="stat-change positive">
                                    +<?php echo Formatters::formatQuantity($stats['general']['users']['new_this_month']); ?> هذا الشهر
                </div>
            </div>
        </div>
        
        <!-- زر تنفيذ جوائز الشهر الماضي -->
        <div class="stat-card stat-card-rewards">
            <div class="stat-icon">🏆</div>
            <div class="stat-content">
                <h3 class="stat-number">جوائز</h3>
                <p class="stat-label">الشهر الماضي</p>
                <div class="stat-change">
                    <button class="btn btn-sm btn-primary" onclick="showRewardsModal()">
                        تنفيذ الجوائز
                    </button>
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-success">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo Formatters::formatQuantity($stats['general']['orders']['total']); ?></h3>
                <p class="stat-label">إجمالي الطلبات</p>
                <div class="stat-change positive">
                                    <?php echo Formatters::formatQuantity($stats['general']['orders']['today']); ?> اليوم
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-warning">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo number_format($stats['general']['wallets']['total_balance'], 2, '.', ','); ?> LYD</h3>
                <p class="stat-label">إجمالي المحافظ (LYD)</p>
                <div class="stat-change">
                    <?php echo Formatters::formatQuantity($stats['general']['wallets']['total_users']); ?> مستخدم نشط
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-info">
            <div class="stat-icon">⚙️</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo Formatters::formatQuantity($stats['general']['services']['total']); ?></h3>
                <p class="stat-label">الخدمات المتاحة</p>
                <div class="stat-change">
                                    <?php echo Formatters::formatQuantity($stats['general']['services']['translated']); ?> مترجمة
                </div>
            </div>
        </div>
    </div>

    <!-- الصف الثاني من الإحصائيات -->
    <div class="stats-grid">
        <div class="stat-card stat-card-purple">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo Formatters::formatQuantity($stats['general']['orders']['completed']); ?></h3>
                <p class="stat-label">طلبات مكتملة</p>
                <div class="stat-change positive">
                    <?php echo $stats['general']['orders']['total'] > 0 ? number_format(($stats['general']['orders']['completed'] / $stats['general']['orders']['total']) * 100, 1, '.', ',') : 0; ?>% نجاح
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-danger">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo number_format($stats['general']['orders']['pending']); ?></h3>
                <p class="stat-label">طلبات معلقة</p>
                <div class="stat-change">
                    في الانتظار
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-dark">
            <div class="stat-icon">🔔</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo Formatters::formatQuantity($stats['general']['notifications']['active']); ?></h3>
                <p class="stat-label">إشعارات نشطة</p>
                <div class="stat-change">
                    <?php echo Formatters::formatQuantity($stats['general']['notifications']['total_views']); ?> مشاهدة
                </div>
            </div>
        </div>

        <div class="stat-card stat-card-accent">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo number_format($stats['performance']['overall_success_rate'], 1); ?>%</h3>
                <p class="stat-label">معدل النجاح</p>
                <div class="stat-change">
                    متوسط <?php echo number_format($stats['performance']['avg_execution_time'], 0); ?> دقيقة
                </div>
            </div>
        </div>
    </div>

    <!-- الرسوم البيانية والجداول -->
    <div class="dashboard-content">
        <div class="dashboard-row">
            <!-- رسم بياني للطلبات -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3>إحصائيات الطلبات</h3>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-outline" onclick="exportChart('orders')">تصدير</button>
                    </div>
                </div>
                <div class="dashboard-card-body">
                    <div class="chart-container">
                        <canvas id="ordersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- أفضل الخدمات -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3>أفضل الخدمات</h3>
                    <div class="card-actions">
                        <a href="/admin/services_report.php" class="btn btn-sm btn-outline">عرض التفاصيل</a>
                    </div>
                </div>
                <div class="dashboard-card-body">
                    <div class="top-services-list">
                        <?php foreach (array_slice($stats['services']['popular_services'], 0, 5) as $index => $service): ?>
                            <div class="top-service-item">
                                <div class="service-rank"><?php echo $index + 1; ?></div>
                                <div class="service-info">
                                    <div class="service-name"><?php echo htmlspecialchars($service['service_name'] ?: 'غير محدد'); ?></div>
                                    <div class="service-category"><?php echo htmlspecialchars($service['category'] ?: 'عام'); ?></div>
                                </div>
                                <div class="service-stats">
                                    <div class="service-orders"><?php echo Formatters::formatQuantity($service['orders_count']); ?> طلب</div>
                                    <div class="service-revenue"><?php echo Formatters::formatMoney($service['total_revenue']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-row">
            <!-- إحصائيات الطلبات حسب الحالة -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3>الطلبات حسب الحالة</h3>
                </div>
                <div class="dashboard-card-body">
                    <div class="status-stats-grid">
                        <?php foreach ($stats['orders']['status_stats'] as $status): ?>
                            <div class="status-stat-item">
                                <div class="status-stat-label"><?php echo htmlspecialchars($status['status']); ?></div>
                                <div class="status-stat-number"><?php echo Formatters::formatQuantity($status['count']); ?></div>
                                <div class="status-stat-amount"><?php echo Formatters::formatMoney($status['total_amount']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- المستخدمين الأكثر نشاطاً -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h3>المستخدمين الأكثر نشاطاً</h3>
                    <div class="card-actions">
                        <a href="/admin/users_report.php" class="btn btn-sm btn-outline">عرض الكل</a>
                    </div>
                </div>
                <div class="dashboard-card-body">
                    <div class="active-users-list">
                        <?php foreach (array_slice($stats['users']['active_users'], 0, 5) as $index => $user): ?>
                            <div class="active-user-item">
                                <div class="user-rank"><?php echo $index + 1; ?></div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($user['name'] ?: $user['phone']); ?></div>
                                    <div class="user-phone"><?php echo htmlspecialchars($user['phone']); ?></div>
                                </div>
                                <div class="user-stats">
                                    <div class="user-orders"><?php echo Formatters::formatQuantity($user['orders_count']); ?> طلب</div>
                                    <div class="user-spent"><?php echo Formatters::formatMoney($user['total_spent']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- الإجراءات السريعة -->
        <div class="dashboard-row">
            <div class="dashboard-card dashboard-card-full">
                <div class="dashboard-card-header">
                    <h3>الإجراءات السريعة</h3>
                </div>
                <div class="dashboard-card-body">
                    <div class="quick-actions-grid">
                        <a href="/admin/orders.php" class="quick-action-btn">
                            <div class="quick-action-icon">📦</div>
                            <div class="quick-action-text">إدارة الطلبات</div>
                        </a>
                        
                        <a href="/admin/notifications.php" class="quick-action-btn">
                            <div class="quick-action-icon">🔔</div>
                            <div class="quick-action-text">الإشعارات</div>
                        </a>
                        
                        <a href="/admin/wallet_approvals.php" class="quick-action-btn">
                            <div class="quick-action-icon">💰</div>
                            <div class="quick-action-text">اعتماد المحافظ</div>
                        </a>
                        
                        <a href="/admin/sync.php" class="quick-action-btn">
                            <div class="quick-action-icon">🔄</div>
                            <div class="quick-action-text">مزامنة الخدمات</div>
                        </a>
                        
                        <a href="/admin/translations.php" class="quick-action-btn">
                            <div class="quick-action-icon">🌐</div>
                            <div class="quick-action-text">الترجمات</div>
                        </a>
                        
                        <a href="/catalog.php" class="quick-action-btn">
                            <div class="quick-action-icon">🛍️</div>
                            <div class="quick-action-text">عرض الخدمات</div>
                        </a>
                        
                        <a href="/admin/setup_advanced_services.php" class="quick-action-btn">
                            <div class="quick-action-icon">⚙️</div>
                            <div class="quick-action-text">إعداد متقدم</div>
                        </a>
                        
                        <a href="/admin/reports.php" class="quick-action-btn">
                            <div class="quick-action-icon">📊</div>
                            <div class="quick-action-text">التقارير</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS للوحة التحكم -->
<style>
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* شريط التنقل العلوي */
.admin-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
}

.admin-topbar-left h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.admin-subtitle {
    margin: 0.5rem 0 0 0;
    color: var(--text-secondary);
    font-size: 1rem;
}

.admin-topbar-right {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.admin-period-selector {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-period-selector label {
    font-weight: 600;
    color: var(--text-primary);
}

.admin-user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-user-name {
    font-weight: 600;
    color: var(--text-primary);
}

/* شبكة الإحصائيات */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    inset-inline-start: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-color), #e6b800);
}

.stat-card:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    font-size: 3rem;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0.5rem 0;
    font-weight: 500;
}

.stat-change {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.stat-change.positive {
    color: #28a745;
}

/* ألوان البطاقات */
.stat-card-primary { border-inline-start: 4px solid var(--primary-color); }
.stat-card-success { border-inline-start: 4px solid #28a745; }
.stat-card-warning { border-inline-start: 4px solid #ffc107; }
.stat-card-info { border-inline-start: 4px solid #17a2b8; }
.stat-card-purple { border-inline-start: 4px solid #6f42c1; }
.stat-card-danger { border-inline-start: 4px solid #dc3545; }
.stat-card-dark { border-inline-start: 4px solid #343a40; }
.stat-card-accent { border-inline-start: 4px solid var(--accent-color); }
.stat-card-rewards { border-inline-start: 4px solid #ff6b6b; }

/* محتوى لوحة التحكم */
.dashboard-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.dashboard-card {
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
    overflow: hidden;
}

.dashboard-card-full {
    grid-column: 1 / -1;
}

.dashboard-card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
}

.dashboard-card-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.dashboard-card-body {
    padding: 2rem;
}

/* الرسوم البيانية */
.chart-container {
    height: 300px;
    position: relative;
}

/* قائمة أفضل الخدمات */
.top-services-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.top-service-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(201, 162, 39, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(201, 162, 39, 0.1);
}

.service-rank {
    width: 30px;
    height: 30px;
    background: var(--accent-color);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
}

.service-info {
    flex: 1;
}

.service-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.service-category {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.service-stats {
    text-align: left;
}

.service-orders {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.service-revenue {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* إحصائيات الحالة */
.status-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.status-stat-item {
    text-align: center;
    padding: 1.5rem 1rem;
    background: rgba(201, 162, 39, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(201, 162, 39, 0.1);
}

.status-stat-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    text-transform: capitalize;
}

.status-stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-color);
    margin-bottom: 0.25rem;
}

.status-stat-amount {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* المستخدمين النشطين */
.active-users-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.active-user-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(26, 60, 140, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(26, 60, 140, 0.1);
}

.user-rank {
    width: 30px;
    height: 30px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-phone {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.user-stats {
    text-align: left;
}

.user-orders {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.user-spent {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* الإجراءات السريعة */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 2rem 1rem;
    background: rgba(201, 162, 39, 0.05);
    border: 2px solid rgba(201, 162, 39, 0.1);
    border-radius: 16px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.quick-action-btn:hover {
    background: rgba(201, 162, 39, 0.1);
    border-color: var(--accent-color);
    transform: translateY(-4px) scale(1.05);
    color: var(--text-primary);
    text-decoration: none;
}

.quick-action-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.quick-action-text {
    font-weight: 600;
    text-align: center;
    font-size: 0.9rem;
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 15px;
    }

    .admin-topbar {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
        padding: 1.5rem;
    }
    
    .admin-topbar-left h1 {
        font-size: 1.75rem;
    }
    
    .admin-topbar-right {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
    }

    .admin-period-selector {
        width: 100%;
    }
    
    .admin-period-selector select {
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .stat-card {
        padding: 1.5rem;
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .stat-icon {
        font-size: 2.5rem;
    }

    .stat-number {
        font-size: 2rem;
    }
    
    .dashboard-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .dashboard-card-header {
        padding: 1rem 1.5rem;
    }

    .dashboard-card-header h3 {
        font-size: 1.1rem;
    }

    .dashboard-card-body {
        padding: 1.5rem;
    }

    .chart-container {
        height: 250px;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .quick-action-btn {
        padding: 1.5rem 0.75rem;
    }

    .quick-action-icon {
        font-size: 2rem;
    }

    .quick-action-text {
        font-size: 0.8rem;
    }

    .top-service-item,
    .active-user-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
        padding: 1rem;
    }

    .status-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .status-stat-item {
        padding: 1rem 0.5rem;
    }

    .status-stat-number {
        font-size: 1.5rem;
    }
}

/* شاشات صغيرة جداً (360px-430px) */
@media (max-width: 430px) {
    .container-fluid {
        padding: 0 10px;
    }

    .admin-topbar {
        padding: 1rem;
    }

    .admin-topbar-left h1 {
        font-size: 1.5rem;
    }

    .stats-grid {
        gap: 0.75rem;
    }

    .stat-card {
        padding: 1rem;
    }

    .stat-icon {
        font-size: 2rem;
    }

    .stat-number {
        font-size: 1.75rem;
    }

    .dashboard-card-header {
        padding: 0.75rem;
    }

    .dashboard-card-body {
        padding: 1rem;
    }

    .chart-container {
        height: 200px;
    }

    .quick-actions-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .quick-action-btn {
        padding: 1rem;
        flex-direction: row;
        text-align: right;
    }

    .quick-action-icon {
        font-size: 1.5rem;
        position: absolute;
        inset-inline-start: 1rem;
    }

    .quick-action-text {
        margin-right: auto;
    }

    .status-stats-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .top-services-list,
    .active-users-list {
        gap: 0.75rem;
    }

    .top-service-item,
    .active-user-item {
        padding: 0.75rem;
    }
}

/* تحسينات إضافية للشاشات شديدة الصغيرة */
@media (max-width: 360px) {
    .admin-topbar-left h1 {
        font-size: 1.25rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .quick-action-btn {
        padding: 0.75rem;
    }

    .chart-container {
        height: 180px;
    }
}
</style>

<!-- JavaScript للرسوم البيانية -->
<script>
// PRODUCTION GUARDS - Disable all debug output
(function() {
    'use strict';
    
    window.__DEBUG__ = false;
    window.__PROD__ = true;
    
    // Disable console methods in production
    if (!window.__DEBUG__) {
        for (const m of ['log','debug','info','warn','table','trace','time','timeEnd']) {
            console[m] = ()=>{};
        }
        if (window.performance) {
            ['mark','measure','clearMarks','clearMeasures'].forEach(k=>{
                if(performance[k]) performance[k]=()=>{};
            });
        }
    }
})();

'use strict';

// نموذج رسوم بيانية فانيلا بدلاً من Chart.js
class VanillaChart {
    constructor(canvas, data) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.data = data;
        this.padding = { top: 20, right: 40, bottom: 40, left: 60 };
        this.colors = {
            primary: '#C9A227',
            secondary: '#1A3C8C',
            background: 'rgba(201, 162, 39, 0.1)',
            background2: 'rgba(26, 60, 140, 0.1)',
            text: '#EDEFF4',
            grid: 'rgba(255, 255, 255, 0.1)'
        };
        
        this.resizeCanvas();
        this.draw();
        
        window.addEventListener("resize", () => {
            this.resizeCanvas();
            this.draw();
        });
    }

    resizeCanvas() {
        const rect = this.canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        this.canvas.width = rect.width * dpr;
        this.canvas.height = rect.height * dpr;
        this.ctx.scale(dpr, dpr);
        this.canvas.style.width = rect.width + 'px';

        this.canvas.style.height = rect.height + 'px';
    }

    calculateScales() {
        const labels = this.data.map(item => item.date).reverse();
        const values1 = this.data.map(item => item.orders_count).reverse();
        const values2 = this.data.map(item => item.daily_revenue).reverse();
        
        const maxValue1 = Math.max(...values1, 1);
        const maxValue2 = Math.max(...values2, 1);
        
        const rect = this.canvas.getBoundingClientRect();
        
        return {
            labels,
            values1,
            values2,
            maxValue1,
            maxValue2,
            width: rect.width - this.padding.left - this.padding.right,
            height: rect.height - this.padding.top - this.padding.bottom
        };
    }

    draw() {
        const scales = this.calculateScales();
        
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.drawGrid(scales);
        this.drawData(scales);
        this.drawAxes(scales);
        this.drawLegend(scales);
    }

    drawGrid(scales) {
        this.ctx.strokeStyle = this.colors.grid;
        this.ctx.lineWidth = 1;
        
        // خطوط أفقية
        for (let i = 0; i <= 5; i++) {
            const y = this.padding.top + (scales.height / 5) * i;
            this.ctx.beginPath();
            this.ctx.moveTo(this.padding.left, y);
            this.ctx.lineTo(this.padding.left + scales.width, y);
            this.ctx.stroke();
        }
        
        // خطوط عمودية
        for (let i = 0; i <= scales.labels.length; i++) {
            const x = this.padding.left + (scales.width / scales.labels.length) * i;
            this.ctx.beginPath();
            this.ctx.moveTo(x, this.padding.top);
            this.ctx.lineTo(x, this.padding.top + scales.height);
            this.ctx.stroke();
        }
    }

    drawData(scales) {
        // رسم بيانات الأولى (عدد الطلبات)
        this.drawLine(scales, scales.values1, scales.maxValue1, this.colors.primary, this.colors.background);
        
        // رسم بيانات الثانية (الإيرادات)
        this.drawLine(scales, scales.values2, scales.maxValue2, this.colors.secondary, this.colors.background2);
    }

    drawLine(scales, values, maxValue, color, bgColor) {
        if (values.length < 2) return;
        
        const points = values.map((value, i) => ({
            x: this.padding.left + (i / (values.length - 1)) * scales.width,
            y: this.padding.top + scales.height - (value / maxValue) * scales.height
        }));

        // رسم المنطقة الممتلئة
        this.ctx.fillStyle = bgColor;
        this.ctx.beginPath();
        this.ctx.moveTo(points[0].x, this.padding.top + scales.height);
        
        points.forEach(point => {
            this.ctx.lineTo(point.x, point.y);
        });
        
        this.ctx.lineTo(points[points.length - 1].x, this.padding.top + scales.height);
        this.ctx.closePath();
        this.ctx.fill();

        // رسم الخط
        this.ctx.strokeStyle = color;
        this.ctx.lineWidth = 2;
        this.ctx.beginPath();
        
        points.forEach((point, i) => {
            if (i === 0) {
                this.ctx.moveTo(point.x, point.y);
            } else {
                // منحنى سلس مع Bézier curves
                const prevPoint = points[i - 1];
                const cp1x = prevPoint.x + (point.x - prevPoint.x) * 0.33;
                const cp1y = prevPoint.y;
                const cp2x = prevPoint.x + (point.x - prevPoint.x) * 0.67;
                const cp2y = point.y;
                
                this.ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, point.x, point.y);
            }
        });
        
        this.ctx.stroke();

        // رسم النقاط
        this.ctx.fillStyle = color;
        points.forEach(point => {
            this.ctx.beginPath();
            this.ctx.arc(point.x, point.y, 4, 0, 2 * Math.PI);
            this.ctx.fill();
        });
    }

    drawAxes(scales) {
        this.ctx.fillStyle = this.colors.text;
        this.ctx.font = '12px Cairo';
        this.ctx.textAlign = 'center';
        
        // المحور السيني (التواريخ)
        scales.labels.forEach((label, i) => {
            const x = this.padding.left + (i / (scales.labels.length - 1)) * scales.width;
            this.ctx.fillText(label.split('-').slice(1).join('/'), x, this.padding.top + scales.height + 20);
        });

        // المحور الصادي الأيسر (عدد الطلبات)
        this.ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            const value = Math.round((scales.maxValue1 / 5) * i);
            const y = this.padding.top + scales.height - (i / 5) * scales.height;
            this.ctx.fillText(value.toString(), this.padding.left - 10, y + 4);
        }

        // المحور الصادي الأيمن (الإيرادات)
        this.ctx.textAlign = 'left';
        for (let i = 0; i <= 5; i++) {
            const value = Math.round((scales.maxValue2 / 5) * i);
            const y = this.padding.top + scales.height - (i / 5) * scales.height;
            this.ctx.fillText(`${value} LYD`, this.padding.left + scales.width + 10, y + 4);
        }
    }

    drawLegend(scales) {
        const legend = [
            { label: 'عدد الطلبات', color: this.colors.primary },
            { label: 'الإيرادات (LYD)', color: this.colors.secondary }
        ];

        this.ctx.font = '14px Cairo';
        this.ctx.textAlign = 'left';
        
        legend.forEach((item, i) => {
            const x = this.padding.left;
            const y = 20 + (i * 20);
            
            this.ctx.fillStyle = item.color;
            this.ctx.fillRect(x, y - 10, 15, 3);
            
            this.ctx.fillStyle = this.colors.text;
            this.ctx.fillText(item.label, x + 20, y - 2);
        });
    }

    update(newData) {
        this.data = newData;
        this.draw();
    }

    destroy() {
        window.removeEventListener("resize", this.resizeCanvas);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // تغيير الفترة الزمنية
    const periodSelector = document.getElementById('period-selector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            window.location.href = `?period=${this.value}`;
        });
    }

    // رسم بياني للطلبات بالجافاسكريبت النقي
    const ordersCanvas = document.getElementById('ordersChart');
    if (ordersCanvas && window.statsData) {
        const ordersData = window.statsData.ordersDaily;
        
        if (ordersData && ordersData.length > 0) {
            ordersChart = new VanillaChart(ordersCanvas, ordersData);
        } else {
            // عرض رسالة في حالة عدم وجود بيانات
            ordersCanvas.style.display = 'none';
            const container = ordersCanvas.parentElement;
            const noDataDiv = document.createElement('div');
            noDataDiv.style.cssText = `
                text-align: center;
                padding: 2rem;
                color: var(--text-secondary);
                font-family: Cairo;
            `;
            noDataDiv.innerHTML = `
                <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                <p>لا توجد بيانات متاحة للرسم البياني</p>
            `;
            container.appendChild(noDataDiv);
        }
    }

    // تحسين إتصال البيانات مع PHP
    if (typeof window.statsData === 'undefined') {
        window.statsData = {
            ordersDaily: <?php echo json_encode($stats['orders']['daily_stats']); ?>
        };
    }
});

let ordersChart = null;

function exportChart(type) {
    // تصدير الرسم البياني كصورة
    if (ordersChart && ordersChart.canvas) {
        const canvas = ordersChart.canvas;
        const link = document.createElement('a');
        link.download = `chart-${type}-${new Date().toISOString().split('T')[0]}.png`;
        link.href = canvas.toDataURL();
        link.click();
        
        // إظهار رسالة نجاح
        showToast('تم تصدير الرسم البياني بنجاح', 'success');
    }
}

// وظيفة إظهار الرسائل التوضيحية
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1000;
        font-family: Cairo;
        font-weight: 600;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// إضافة CSS للرسوم المتحركة
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);

// وظائف إدارة جوائز الشهر الماضي
function showRewardsModal() {
    const modal = document.createElement('div');
    modal.id = 'rewardsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        font-family: Cairo;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        ">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.5rem;">🏆 تنفيذ جوائز الشهر الماضي</h2>
                <p style="margin: 0; color: #666; font-size: 0.9rem;">سيتم منح المكافآت للمراكز الأولى حسب الجدول التالي:</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; color: #333; font-size: 1.1rem;">جدول المكافآت:</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥇 المركز الأول:</span>
                        <span style="font-weight: 600; color: #ffd700;">40 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥈 المركز الثاني:</span>
                        <span style="font-weight: 600; color: #c0c0c0;">25 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥉 المركز الثالث:</span>
                        <span style="font-weight: 600; color: #cd7f32;">10 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>المراكز 4-7:</span>
                        <span style="font-weight: 600; color: #28a745;">1 LYD لكل مركز</span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button onclick="executeRewards()" style="
                    background: #28a745;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">تنفيذ الجوائز</button>
                <button onclick="closeRewardsModal()" style="
                    background: #6c757d;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">إلغاء</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeRewardsModal() {
    const modal = document.getElementById('rewardsModal');
    if (modal) {
        modal.remove();
    }
}

function executeRewards() {
    const modal = document.getElementById('rewardsModal');
    if (modal) {
        modal.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
                <h3 style="margin: 0 0 1rem 0; color: #333;">جاري تنفيذ الجوائز...</h3>
                <p style="margin: 0; color: #666;">يرجى الانتظار</p>
            </div>
        `;
    }
    
    // إرسال طلب تنفيذ الجوائز
    fetch('/admin/execute_rewards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'execute_rewards' })
    })
    .then(response => response.json())
    .then(data => {
        closeRewardsModal();
        
        if (data.success) {
            showRewardsResult(data);
        } else {
            showToast('خطأ: ' + data.message, 'error');
        }
    })
    .catch(error => {
        closeRewardsModal();
        showToast('حدث خطأ في الاتصال', 'error');
    });
}

function showRewardsResult(data) {
    const modal = document.createElement('div');
    modal.id = 'rewardsResultModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        font-family: Cairo;
    `;
    
    let processedUsersHtml = '';
    if (data.processed_users && data.processed_users.length > 0) {
        processedUsersHtml = `
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; color: #333; font-size: 1.1rem;">المستخدمون الذين تم منحهم الجوائز:</h3>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px;">
                    ${data.processed_users.map(user => `
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <div>
                                <div style="font-weight: 600;">${user.user_name || 'مستخدم غير معروف'}</div>
                                <div style="font-size: 0.8rem; color: #666;">المركز ${user.rank}</div>
                            </div>
                            <div style="font-weight: 600; color: #28a745;">${user.amount} LYD</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        ">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h2 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.5rem;">تم تنفيذ الجوائز بنجاح!</h2>
                <p style="margin: 0; color: #666; font-size: 0.9rem;">${data.message}</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #28a745;">${data.processed}</div>
                    <div style="font-size: 0.9rem; color: #666;">مستخدم تم منحه الجائزة</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #007bff;">${data.total_amount}</div>
                    <div style="font-size: 0.9rem; color: #666;">LYD إجمالي المبلغ</div>
                </div>
            </div>
            
            ${processedUsersHtml}
            
            <div style="text-align: center;">
                <button onclick="closeRewardsResultModal()" style="
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 0.75rem 2rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">موافق</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeRewardsResultModal() {
    const modal = document.getElementById('rewardsResultModal');
    if (modal) {
        modal.remove();
    }
}
</script>

<?php if (!$isAdmin): ?>
<?php include __DIR__ . '/../templates/partials/footer.php'; ?>
<?php else: ?>
    </main>
</div>
</body>
</html>
<?php endif; ?>
