<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Utils/db.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'إدارة الطلبات';

// Enhanced Orders Management with Advanced Filtering and Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50; // Optimized for 1000+ rows
$offset = ($page - 1) * $perPage;

// Advanced filters
$statusFilter = $_GET['status'] ?? '';
$providerFilter = $_GET['provider'] ?? '';
$dateRangeFilter = $_GET['date_range'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Validate sort parameters
$allowedSorts = ['created_at', 'completed_at', 'price_lyd', '(o.joined_at)'];
$allowedOrders = ['ASC', 'DESC'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
$sortOrder = in_array($sortOrder, $allowedOrders) ? $sortOrder : 'DESC';

// Build WHERE conditions with NULL-safe flags
$whereConditions = [
    'COALESCE(o.is_deleted, 0) = 0'
];
$params = [];

// Status filter
if (!empty($statusFilter)) {
    $whereConditions[] = 'o.status = ?';
    $params[] = $statusFilter;
}

// Provider filter  
if (!empty($providerFilter)) {
    $whereConditions[] = 's.provider = ?';
    $params[] = $providerFilter;
}

// Date range filter
if (!empty($dateRangeFilter)) {
    switch ($dateRangeFilter) {
        case 'today':
            $whereConditions[] = 'DATE(o.created_at) = CURDATE()';
            break;
        case '7days':
            $whereConditions[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case '30days':
            $whereConditions[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case 'this_month':
            $whereConditions[] = 'MONTH(o.created_at) = MONTH(NOW()) AND YEAR(o.created_at) = YEAR(NOW())';
            break;
    }
}

// Enhanced search (debounced - implementation in JS)
if (!empty($searchQuery)) {
    $whereConditions[] = '(s.name LIKE ? OR s.name_ar LIKE ? OR o.link LIKE ? OR o.username LIKE ? OR o.external_order_id LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)';
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// Helper function for status labels
function getStatusLabel($status) {
    $labels = [
        'pending' => '⏳ في الانتظار',
        'processing' => '🔄 قيد التنفيذ', 
        'completed' => '✅ مكتمل',
        'partial' => '⚠️ جزئي',
        'cancelled' => '❌ ملغي',
        'failed' => '💥 فشل',
        'refunded' => '💰 مسترد'
    ];
    return $labels[$status] ?? ucfirst($status);
}

try {
    // Get total count for pagination
    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM orders o 
         LEFT JOIN services_cache s ON o.service_id = s.id 
         LEFT JOIN users u ON o.user_id = u.id
         WHERE {$whereClause}",
        $params
    );
    
    // Get orders with pagination and detailed data
    $orders = Database::fetchAll(
        "SELECT o.id, o.user_id, o.service_id, o.status, o.price_lyd, o.price_usd, 
                o.username, o.link, o.external_order_id, o.provider_order_id, 
                o.created_at, o.completed_at, o.joined_at, o.quantity,
                s.name as service_name, s.name_ar as service_name_ar, s.category, s.provider,
                u.phone as user_phone, u.email as user_email, u.name as user_name
         FROM orders o 
         LEFT JOIN services_cache s ON o.service_id = s.id 
         LEFT JOIN users u ON o.user_id = u.id
         WHERE {$whereClause}
         ORDER BY {$sortBy} {$sortOrder}, o.id DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    
    // Get filter options
    $providers = Database::fetchAll(
        "SELECT DISTINCT s.provider 
         FROM services_cache s 
         WHERE s.provider IS NOT NULL AND s.provider != ''
         ORDER BY s.provider",
        []
    );
    
    // Status statistics
    $stats = Database::fetchAll(
        "SELECT status, COUNT(*) as count 
         FROM orders 
         GROUP BY status 
         ORDER BY count DESC",
        []
    );
    
    $statusCounts = [];
    foreach ($stats as $stat) {
        $statusCounts[$stat['status']] = $stat['count'];
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalCount / $perPage);
    $hasNextPage = $page < $totalPages;
    $hasPreviousPage = $page > 1;
    
} catch (Exception $e) {
    $orders = [];
    $statusCounts = [];
    $providers = [];
    $totalCount = 0;
    $errorMessage = "خطأ في جلب الطلبات: " . $e->getMessage();
}

// Check if admin session exists
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin) {
require_once BASE_PATH . '/templates/partials/header.php';
} else {
    // Admin-specific header  
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
    <script src="<?php echo asset_url('assets/js/app.min.js'); ?>" defer></script>
</head>
<body class="admin-body">
<?php } ?>

<!-- Include admin shell partial -->
<?php if ($isAdmin): ?>
<style>
/* Font faces with display swap for better performance */
@font-face {
    font-family: 'Tajawal';
    src: url('<?php echo asset_url('assets/fonts/tajawal-regular.woff2'); ?>') format('woff2'),
         url('<?php echo asset_url('assets/fonts/tajawal-regular.woff'); ?>') format('woff');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Inter Variable';
    src: url('<?php echo asset_url('assets/fonts/inter-var.woff2'); ?>') format('woff2');
    font-weight: 100 900;
    font-style: normal;
    font-display: swap;
}

:root{
  --ad-primary:#1A3C8C;      /* Royal blue */
  --ad-gold:#C9A227;         /* Brand gold */
  --ad-bg:#0C1017;           /* Dark canvas */
  --ad-card:#121826;         /* Surfaces */
  --ad-elev:#161E2E;         /* Elevated */
  --ad-text:#E9EDF6;
  --ad-muted:#9AA6BF;
  --ad-border:rgba(255,255,255,.08);
  --ad-radius:14px;
  --ad-shadow:0 8px 24px rgba(0,0,0,.28);
}

[data-theme="light"]{
  --ad-bg:#F7F9FD; --ad-card:#FFFFFF; --ad-elev:#EFF3FA;
  --ad-text:#1B2437; --ad-muted:#5E6B86; --ad-border:rgba(10,20,40,.1);
}

/* Shell */
.admin-body{ background:var(--ad-bg); color:var(--ad-text); }
.admin-card{
  background:var(--ad-card); border:1px solid var(--ad-border);
  border-radius:var(--ad-radius); box-shadow:0 1px 2px rgba(0,0,0,.16);
}

/* Header/Sidebar accents */
.admin-header{ background:var(--ad-card); border-bottom:1px solid var(--ad-border); position:sticky; top:0; z-index:9500; }
.admin-header::after{ content:""; display:block; height:2px;
  background:linear-gradient(90deg,var(--ad-gold),#D6B544,var(--ad-gold)); }

.admin-sidebar{ 
  background:var(--ad-card); 
  border-inline-end:1px solid var(--ad-border);
  position: fixed;
  top: 0;
  left: 0;
  width: 280px;
  height: 100dvh;
  z-index: 10000;
  overflow-y: auto;
  transform: translateX(-100%);
  transition: transform 0.3s ease;
  padding-inline-start: max(16px, env(safe-area-inset-left));
  padding-inline-end: max(16px, env(safe-area-inset-right));
}
.admin-sidebar.open{ transform: translateX(0); }
.admin-sidebar a{ color:var(--ad-muted); min-height:44px; display:flex; align-items:center; gap:10px; padding:12px 16px; transition:background-color 0.2s; }
.admin-sidebar a:hover{ background:rgba(255,255,255,0.05); }
.admin-sidebar a[aria-current="page"]{ 
  color:var(--ad-text); 
  position:relative; 
  background:rgba(201,162,39,0.1);
}
.admin-sidebar a[aria-current="page"]::before{
  content:""; 
  inline-size:3px; 
  block-size:70%; 
  background:var(--ad-gold); 
  border-radius:2px;
  position:absolute; 
  inset-inline-start:0; 
  top:15%;
}
@media (min-width: 769px) {
  .admin-sidebar{ 
    position: static; 
    transform: none; 
    transition: none;
    width: auto;
    height: auto;
    overflow: visible;
  }
}

/* Sidebar backdrop */
.sidebar-backdrop{
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}
.sidebar-backdrop.open{
  opacity: 1;
  visibility: visible;
}
@media (min-width: 769px) {
  .sidebar-backdrop{ display: none; }
}

/* Buttons */
.btn{ border-radius:12px; height:40px; padding:0 14px; font-weight:600; }
.btn--primary{ background:linear-gradient(180deg,#D6B544,var(--ad-gold)); color:#0E0F12; border:1px solid #B38F1F; }
.btn--outline{ background:transparent;	color:var(--ad-text); border:1px solid var(--ad-border); }
.btn:focus-visible{ outline:2px solid #D6B544; outline-offset:2px; }

/* Inputs */
.input,.select,.textarea{
  background:var(--ad-elev); color:var(--ad-text);
  border:1px solid var(--ad-border); border-radius:12px; min-height:42px;
}
.input::placeholder{ color:var(--ad-muted); }
.input:focus,.select:focus,.textarea:focus{
  border-color:#B38F1F; box-shadow:0 0 0 3px rgba(201,162,39,.25); outline:none;
}

/* Enhanced focus-visible for better accessibility */
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
button:focus-visible,
a:focus-visible {
  outline: 2px solid var(--ad-gold);
  outline-offset: 2px;
  border-radius: 4px;
}

/* Form error messages */
.form-error {
  display: none;
  color: #dc3545;
  font-size: 0.875rem;
  margin-top: 0.25rem;
  padding: 0.375rem 0.5rem;
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.3);
  border-radius: 6px;
}

.form-error.show {
  display: block;
}

.input.error {
  border-color: #dc3545;
  box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25);
}

/* Unified Toast Notifications */
.admin-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: var(--ad-card);
  border: 1px solid var(--ad-border);
  border-radius: var(--ad-radius);
  padding: 1rem 2rem 1rem 1rem;
  box-shadow: var(--ad-shadow);
  z-index: 10001;
  min-width: 280px;
  max-width: 400px;
  transform: translateX(100%);
  opacity: 0;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.admin-toast.show {
  transform: translateX(0);
  opacity: 1;
}

.admin-toast-success {
  border-inline-start: 4px solid #28a745;
}

.admin-toast-error {
  border-inline-start: 4px solid #dc3545;
}

.admin-toast-warning {
  border-inline-start: 4px solid #ffc107;
}

.admin-toast-info {
  border-inline-start: 4px solid #17a2b8;
}

.toast-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
}

.toast-message {
  flex: 1;
  color: var(--ad-text);
  font-weight: 500;
}

.toast-close {
  background: none;
  border: none;
  color: var(--ad-muted);
  font-size: 1.25rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.toast-close:hover {
  color: var(--ad-text);
}

@media (max-width: 430px) {
  .admin-toast {
    right: 10px;
    inset-inline-start: 10px;
    min-width: auto;
    max-width: none;
  }
}

/* Tables */
.table{ width:100%; border-collapse:separate; border-spacing:0; }
.table thead th{
  position:sticky; top:0; background:var(--ad-card); z-index:1;
  color:var(--ad-text); border-bottom:1px solid var(--ad-border);
}
.table tbody tr:hover{ background:rgba(255,255,255,.03); }
.table td,.table th{ padding:12px 14px; border-bottom:1px solid var(--ad-border); }

/* Icons (inline SVG we use everywhere) */
.icon{ inline-size:1.2rem; block-size:1.2rem; display:inline-grid; place-items:center; vertical-align:middle; }
.icon svg{ width:100%; height:100%; display:block; }

/* Accessibility */
:focus-visible{ outline:2px solid #D6B544; outline-offset:2px; }

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
    min-height: calc(100vh - 64px);
    padding: 20px;
}

/* Mobile responsiveness fixes */
@media (max-width: 430px) {
    .admin-topbar {
        padding: 0.5rem 1rem;
        flex-wrap: wrap;
    }
    
    .admin-topbar button {
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
    }
    
    .admin-content {
        padding: 10px;
        margin-top: auto;
    }
}
</style>

<div class="admin-wrapper">
    <header class="admin-topbar">
        <button type="button" onclick="location.href='/admin/'" class="btn btn-sm">← العودة للصفحة الرئيسية</button>
        <div style="flex: 1; text-align: center; font-weight: 700; color: var(--primary-color);">
            <?php echo APP_NAME; ?> - إدارة الطلبات
        </div>
    </header>
    
    <main class="admin-content">
<?php endif; ?>

<div class="<?php echo !$isAdmin ? 'container-fluid' : ''; ?>">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" style="margin-bottom: 1rem;">
        <ol class="breadcrumb" style="background: none; padding: 0; margin: 0;">
            <li class="breadcrumb-item"><a href="/admin/" style="color: var(--primary-color); text-decoration: none;">🏠 لوحة الإدارة</a></li>
            <li class="breadcrumb-item active" aria-current="page">📦 إدارة الطلبات</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="page-header" style="background: var(--card-bg); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12); text-align: center;">
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, var(--primary-color), var(--color-accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $pageTitle; ?></h1>
        <p style="margin: 0; color: var(--text-secondary); font-size: 1rem;">عرض وإدارة جميع الطلبات في النظام</p>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-number"><?php echo array_sum($statusCounts); ?></div>
            <div class="stat-label">إجمالي الطلبات</div>
        </div>
        <div class="stat-card status-pending">
            <div class="stat-number"><?php echo $statusCounts['pending'] ?? 0; ?></div>
            <div class="stat-label">في الانتظار</div>
        </div>
        <div class="stat-card status-processing">
            <div class="stat-number"><?php echo $statusCounts['processing'] ?? 0; ?></div>
            <div class="stat-label">قيد التنفيذ</div>
        </div>
        <div class="stat-card status-completed">
            <div class="stat-number"><?php echo $statusCounts['completed'] ?? 0; ?></div>
            <div class="stat-label">مكتملة</div>
        </div>
    </div>

    <!-- Enhanced Orders Filters -->
    <div class="orders-filters-section">
        <form method="GET" id="orders-filter-form" class="filters-card">
            <!-- Filter Row 1: Search & Status -->
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search-filter" class="filter-label">🔍 بحث سريع</label>
                <input type="text" 
                           id="search-filter" 
                       name="search" 
                           class="filter-input search-input" 
                           placeholder="اسماء الخدمات، الروابط، الهاتف، الايميل..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           autocomplete="off"
                           aria-describedby="search-error">
                <div id="search-error" class="form-error"></div>
            </div>
            
                <div class="filter-group">
                    <label for="status-filter" class="filter-label">📊 الحالة</label>
                    <select id="status-filter" name="status" class="filter-select">
                    <option value="">جميع الحالات</option>
                        <?php foreach ($stats as $stat): ?>
                            <option value="<?php echo htmlspecialchars($stat['status']); ?>" 
                                    <?php echo $statusFilter === $stat['status'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(getStatusLabel($stat['status'])); ?> (<?php echo $stat['count']; ?>)
                            </option>
                        <?php endforeach; ?>
                </select>
            </div>
            
                <div class="filter-group">
                    <label for="provider-filter" class="filter-label">🏢 المزود</label>
                    <select id="provider-filter" name="provider" class="filter-select">
                        <option value="">جميع المزودين</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?php echo htmlspecialchars($provider['provider']); ?>" 
                                    <?php echo $providerFilter === $provider['provider'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($provider['provider']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date-range-filter" class="filter-label">📅 الفترة الزمنية</label>
                    <select id="date-range-filter" name="date_range" class="filter-select">
                        <option value="">جميع الفترات</option>
                        <option value="today" <?php echo $dateRangeFilter === 'today' ? 'selected' : ''; ?>>🗓️ اليوم</option>
                        <option value="7days" <?php echo $dateRangeFilter === '7days' ? 'selected' : ''; ?>>📅 آخر 7 أيام</option>
                        <option value="30days" <?php echo $dateRangeFilter === '30days' ? 'selected' : ''; ?>>📅 آخر 30 يوم</option>
                        <option value="this_month" <?php echo $dateRangeFilter === 'this_month' ? 'selected' : ''; ?>>📄 هذا الشهر</option>
                    </select>
            </div>
        </div>
        
            <!-- Filter Row 2: Sort & Actions -->
            <div class="filter-row">
                <div class="filter-group">
                    <label for="sort-select" class="filter-label">🔄 الترتيب</label>
                    <select id="sort-select" name="sort" class="filter-select">
                        <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>تاريخ الإنشاء (الأحدث)</option>
                        <option value="completed_at" <?php echo $sortBy === 'completed_at' ? 'selected' : ''; ?>>تاريخ الإكمال</option>
                        <option value="price_lyd" <?php echo $sortBy === 'price_lyd' ? 'selected' : ''; ?>>السعر</option>
                        <option value="o.joined_at" <?php echo $sortBy === 'o.joined_at' ? 'selected' : ''; ?>>تاريخ الانضمام</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="order-select" class="filter-label">اتجاه الترتيب</label>
                    <select id="order-select" name="order" class="filter-select">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>تنازلي ▼</option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>تصاعدي ▲</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">🔍</span>
                        تطبيق الفلاتر
                    </button>
                    <button type="button" class="btn" onclick="clearOrdersFilters()">
                        <span class="btn-icon">🔄</span>
                        إعادة تعيين
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportOrders()">
                        <span class="btn-icon">📥</span>
                        تصدير البيانات
                    </button>
                </div>
        </div>
    </form>
    </div>
    
    <!-- جدول الطلبات -->
    <?php if (!empty($orders)): ?>
        <div class="orders-table-container">
            <table class="table orders-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المستخدم</th>
                        <th>الخدمة</th>
                        <th>الكمية</th>
                        <th>السعر</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr data-order-id="<?php echo $order['id']; ?>" class="order-row clickable-row" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                            <td style="font-weight: bold; color: var(--primary-color);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span>#<?php echo $order['id']; ?></span>
                                    <button onclick="event.stopPropagation(); copyToClipboard('<?php echo $order['id']; ?>')" 
                                            class="copy-btn" title="نسخ رقم الطلب">
                                        📋
                                    </button>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($order['user_phone'] ?? 'غير محدد'); ?>
                                <br><small style="color: var(--text-secondary);">ID: <?php echo $order['user_id']; ?></small>
                            </td>
                            <td>
                                <div class="service-info">
                                    <div class="service-name" style="font-weight: 600;">
                                        <?php echo htmlspecialchars($order['service_name'] ?? 'خدمة غير محددة'); ?>
                                    </div>
                                    <?php if (!empty($order['link'])): ?>
                                    <div class="service-link" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                        <a href="<?php echo htmlspecialchars($order['link']); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars(mb_strlen($order['link']) > 30 ? mb_substr($order['link'], 0, 30) . '...' : $order['link']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['username'])): ?>
                                    <div class="service-username" style="font-size: 0.8rem; color: var(--text-secondary);">
                                        👤 <?php echo htmlspecialchars($order['username']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-weight: bold;">
                                <?php echo Formatters::formatQuantity($order['quantity']); ?>
                            </td>
                            <td style="color: var(--accent-color); font-weight: bold;">
                                <?php echo Formatters::formatMoney($order['price_lyd']); ?>
                            </td>
                            <td data-status="<?php echo htmlspecialchars($order['status']); ?>">
                                <?php
                                $statusClass = 'status-warning';
                                $statusText = $order['status'];
                                $statusIcon = '⏳';
                                
                                switch ($order['status']) {
                                    case 'completed':
                                        $statusClass = 'status-success';
                                        $statusText = 'مكتمل';
                                        $statusIcon = '✅';
                                        break;
                                    case 'processing':
                                        $statusClass = 'status-primary';
                                        $statusText = 'قيد التنفيذ';
                                        $statusIcon = '🔄';
                                        break;
                                    case 'partial':
                                        $statusClass = 'status-info';
                                        $statusText = 'جزئي';
                                        $statusIcon = '⚠️';
                                        break;
                                    case 'cancelled':
                                    case 'failed':
                                        $statusClass = 'status-error';
                                        $statusText = 'ملغي';
                                        $statusIcon = '❌';
                                        break;
                                    default:
                                        $statusText = 'في الانتظار';
                                        $statusIcon = '⏳';
                                }
                                ?>
                                <span class="order-status-badge <?php echo $statusClass; ?>">
                                    <span class="status-icon"><?php echo $statusIcon; ?></span>
                                    <span class="status-text"><?php echo $statusText; ?></span>
                                </span>
                            </td>
                            <td>
                                <?php echo date('Y-m-d', strtotime($order['created_at'])); ?>
                                <br><small style="color: var(--text-secondary);">
                                    <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <div class="order-actions">
                                    <?php if ($order['external_order_id']): ?>
                                        <a href="/track.php?order=<?php echo urlencode($order['external_order_id']); ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="تتبع الطلب">
                                            <span class="action-icon">📍</span>
                                            <span class="action-text">تتبع</span>
                                        </a>
                                        
                                        <!-- أزرار النسخ -->
                                        <div class="copy-buttons">
                                            <button type="button" class="copy-btn" onclick="copyOrderId(<?php echo $order['id']; ?>)" title="نسخ رقم الطلب">
                                                📋 رقم الطلب
                                            </button>
                                            <button type="button" class="copy-btn" onclick="copyExternalId('<?php echo htmlspecialchars($order['external_order_id']); ?>')" title="نسخ معرّف المزود">
                                                🏷️ المزود
                                            </button>
                                            <button type="button" class="copy-btn" onclick="copyUserId(<?php echo $order['user_id']; ?>)" title="نسخ رقم المستخدم">
                                                👤 المستخدم
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <!-- أزرار النسخ للطلبات الداخلية -->
                                        <div class="copy-buttons">
                                            <button type="button" class="copy-btn" onclick="copyOrderId(<?php echo $order['id']; ?>)" title="نسخ رقم الطلب">
                                                📋 رقم الطلب
                                            </button>
                                            <button type="button" class="copy-btn" onclick="copyUserId(<?php echo $order['user_id']; ?>)" title="نسخ رقم المستخدم">
                                                👤 المستخدم
                                            </button>
                                            <span class="no-external-id">-</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="orders-pagination" style="margin-top: 2rem; text-align: center;">
                <div class="pagination-info" style="margin-bottom: 1rem; color: var(--text-secondary);">
                    عرض <?php echo $offset + 1; ?> إلى <?php echo min($offset + $perPage, $totalCount); ?> من <?php echo number_format($totalCount); ?> طلب
                </div>
                
                <nav class="pagination-nav" aria-label="تنقل بين صفحات الطلبات">
                    <?php if ($hasPreviousPage): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn pagination-first">
                            ⏮️ أولى
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn pagination-prev">
                            ◀️ سابق
                        </a>
                    <?php endif; ?>
                    
                    <!-- Page numbers -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    $visiblePages = range($startPage, $endPage);
                    ?>
                    
                    <?php foreach ($visiblePages as $pageNum): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pageNum])); ?>" 
                           class="pagination-btn <?php echo $pageNum === $page ? 'pagination-current' : ''; ?>">
                            <?php echo $pageNum; ?>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if ($hasNextPage): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn pagination-next">
                            التالية ▶️
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-btn pagination-last">
                            أخيرة ⏭️
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
        
        <?php if (count($orders) >= $perPage): ?>
            <div class="pagination-info-mobile" style="text-align: center; margin-top: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                صفحة <?php echo $page; ?> من <?php echo $totalPages; ?> | <?php echo number_format($totalCount); ?> طلب إجمالي
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>لا توجد طلبات</h3>
            <p>لم يتم العثور على طلبات تطابق معايير البحث.</p>
            <button type="button" class="btn btn-primary" onclick="clearOrdersFilters()">عرض جميع الطلبات</button>
        </div>
    <?php endif; ?>
</div>

<!-- Order Details Drawer -->
<div class="order-details-drawer" id="orderDetailsDrawer">
    <div class="drawer-content" id="drawerContent">
        <div class="drawer-header">
            <h3 id="drawerTitle">تفاصيل الطلب</h3>
            <button class="drawer-close" onclick="closeOrderDrawer()">×</button>
        </div>
        <div class="drawer-body" id="drawerBody">
            <!-- Content loaded dynamically -->
        </div>
        <div class="drawer-footer" id="drawerFooter">
            <!-- Action buttons loaded dynamically -->
        </div>
    </div>
</div>

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

// Initialize enhanced orders functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeDebouncedSearch();
    initializeOrderDrawer();
    loadFiltersFromStorage();
});

function initializeDebouncedSearch() {
    const searchInput = document.getElementById('search-filter');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            saveFiltersToStorage();
            document.getElementById('orders-filter-form').submit();
        }, 350); // 350ms debounce
    });
}

function initializeOrderDrawer() {
    // Close drawer when clicking outside
    document.getElementById('orderDetailsDrawer').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderDrawer();
        }
    });
    
    // Close drawer with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeOrderDrawer();
        }
    });
}

function showOrderDetails(orderDataOrId) {
    const drawer = document.getElementById('orderDetailsDrawer');
    const drawerBody = document.getElementById('drawerBody');
    const drawerFooter = document.getElementById('drawerFooter');
    const drawerTitle = document.getElementById('drawerTitle');
    
    drawer.classList.add('active');
    
    // If we have order data directly, use it
    if (typeof orderDataOrId === 'object') {
        drawerTitle.textContent = `طلب #${orderDataOrId.id}`;
        drawerBody.innerHTML = renderOrderDetailsContent(orderDataOrId);
        drawerFooter.innerHTML = renderOrderActions(orderDataOrId);
    } else {
        // Legacy support for orderId
        drawerTitle.textContent = 'جاري التحميل...';
        drawerBody.innerHTML = '<div class="loading-state">🔄 جاري جلب تفاصيل الطلب...</div>';
        drawerFooter.innerHTML = '';
        
        setTimeout(() => {
            loadOrderDetails(orderDataOrId);
        }, 500);
    }
}

function loadOrderDetails(orderId) {
    const drawerTitle = document.getElementById('drawerTitle');
    const drawerBody = document.getElementById('drawerBody');
    const drawerFooter = document.getElementById('drawerFooter');
    
    // Find order data from current page
    const orderRow = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!orderRow) {
        drawerBody.innerHTML = '<div class="error-state">❌ لم يتم العثور على تفاصيل الطلب</div>';
        drawerFooter.innerHTML = '<button class="btn" onclick="closeOrderDrawer()">إغلاق</button>';
        return;
    }
    
    // Extract order data from table row
    const orderData = extractOrderDataFromRow(orderRow);
    
    // Render order details
    drawerTitle.textContent = `طلب #${orderId}`;
    drawerBody.innerHTML = renderOrderDetailsContent(orderData);
    drawerFooter.innerHTML = renderOrderActions(orderData);
}

function extractOrderDataFromRow(row) {
    const cells = row.querySelectorAll('td');
    return {
        id: row.dataset.orderId,
        user_phone: cells[1].textContent.trim(),
        service_name: cells[2].querySelector('.service-name').textContent.trim(),
        link: cells[2].querySelector('.service-link a')?.getAttribute('href') || '',
        username: cells[2].querySelector('.service-username')?.textContent.replace('👤 ', '') || '',
        quantity: cells[3].textContent.trim(),
        price: cells[4].textContent.trim(),
        status: cells[5].dataset.status,
        status_text: cells[5].textContent.trim(),
        created_date: cells[6].textContent.trim()
    };
}

function renderOrderDetailsContent(orderData) {
    return `
        <div class="order-detail-section">
            <h4>📋 معلومات الطلب</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>رقم الطلب:</strong>
                    <div class="detail-value with-copy">
                        <span>#${orderData.id}</span>
                        <button onclick="copyToClipboard('${orderData.id}')" class="copy-btn-mini">📋</button>
                    </div>
                </div>
                <div class="detail-item">
                    <strong>الخدمة:</strong>
                    <span class="detail-value">${orderData.service_name}</span>
                </div>
                <div class="detail-item">
                    <strong>الكمية:</strong>
                    <span class="detail-value">${orderData.quantity}</span>
                </div>
                <div class="detail-item">
                    <strong>السعر:</strong>
                    <span class="detail-value">${orderData.price}</span>
                </div>
                <div class="detail-item">
                    <strong>الحالة:</strong>
                    <span class="detail-value">${orderData.status_text}</span>
                </div>
                <div class="detail-item">
                    <strong>تاريخ الإنشاء:</strong>
                    <div class="detail-value with-copy">
                        <span>${orderData.created_date}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="order-detail-section">
            <h4>👤 معلومات المستخدم</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <strong>رقم الهاتف:</strong>
                    <div class="detail-value with-copy">
                        <span>${orderData.user_phone}</span>
                        <button onclick="copyUserId('${orderData.id}')" class="copy-btn-mini">📋</button>
                    </div>
                </div>
                <div class="detail-item">
                    <strong>المستخدم:</strong>
                    <span class="detail-value">${orderData.username}</span>
                </div>
            </div>
        </div>
        
        ${orderData.link ? `
        <div class="order-detail-section">
            <h4>🔗 رابط الهدف</h4>
            <div class="link-preview">
                <a href="${orderData.link}" target="_blank" rel="noopener">
                    ${orderData.link}
                </a>
            </div>
        </div>
        ` : ''}
        
        <div class="order-detail-section">
            <h4>🏢 معلومات المزود</h4>
            <div class="detail-row">
                ${orderData.provider_order_id ? `
                <div class="detail-item">
                    <strong>رقم طلب المزود:</strong>
                    <div class="detail-value with-copy">
                        <span>${orderData.provider_order_id}</span>` +
                        `<button onclick="copyToClipboard('${orderData.provider_order_id}')" class="copy-btn-mini">📋</button>
                    </div>
                </div>
                ` : ''}
                ${orderData.external_order_id ? `
                <div class="detail-item">
                    <strong>رقم طلب خارجي:</strong>
                    <div class="detail-value with-copy">
                        <span>${orderData.external_order_id}</span>
                        <button onclick="copyToClipboard('${orderData.external_order_id}')" class="copy-btn-mini">📋</button>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="order-detail-section">
            <h4>📈 تاريخ الطلب</h4>
            <div class="status-history">
                <div class="history-item">
                    <span class="history-status">⏳ تم الإنشاء</span>
                    <span class="history-date">${orderData.created_date}</span>
                </div>
                <div class="history-item">
                    <span class="history-status status-${orderData.status}">${orderData.status_text}</span>
                    <span class="history-date">الحالة الحالية</span>
                </div>
            </div>
        </div>
    `;
}

function renderOrderActions(orderData) {
    const actions = [];
    
    // Base actions
    actions.push('<button class="btn btn-sm" onclick="copyOrderId(' + orderData.id + ')">📋 نسخ رقم الطلب</button>');
    
    // Status-specific actions
    if (orderData.status === 'pending') {
        actions.push('<button class="btn btn-warning btn-sm" onclick="retryOrder(' + orderData.id + ')">🔄 إعادة المحاولة</button>');
        actions.push('<button class="btn btn-danger btn-sm" onclick="cancelOrder(' + orderData.id + ')">❌ إلغاء طلب</button>');
    }
    
    if (orderData.status === 'processing') {
        actions.push('<button class="btn btn-info btn-sm" onclick="checkOrderStatus(' + orderData.id + ')">🔍 فحص الحالة</button>');
    }
    
    if (orderData.status === 'completed') {
        actions.push('<button class="btn btn-success btn-sm" onclick="duplicateOrder(' + orderData.id + ')">📋 مضاعف طلب</button>');
    }
    
    actions.push('<button class="btn btn-secondary btn-sm" onclick="closeOrderDrawer()">إغلاق</button>');
    
    return actions.join(' ');
}

function copyToClipboard(text, message = 'تم النسخ') {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message, 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text, message);
        });
    } else {
        fallbackCopyToClipboard(text, message);
    }
}

function showToast(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 10000;
        background: var(--card-bg); color: var(--text-primary);
        padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function closeOrderDrawer() {
    document.getElementById('orderDetailsDrawer').classList.remove('active');
}

function clearOrdersFilters() {
    document.getElementById('orders-filter-form').reset();
    localStorage.removeItem('orders_filters');
    window.location.href = '/admin/orders.php';
}

// Copy functions
function copyOrderId(orderId) {
    copyToClipboard(orderId.toString(), 'تم نسخ رقم الطلب: ' + orderId);
}

function copyExternalId(externalId) {
    copyToClipboard(externalId, 'تم نسخ معرّف المزود: ' + externalId);
}

function copyUserId(userId) {
    copyToClipboard(userId.toString(), 'تم نسخ رقم المستخدم: ' + userId);
}

function copyToClipboard(text, message) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message, 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text, message);
        });
    } else {
        fallbackCopyToClipboard(text, message);
    }
}

function fallbackCopyToClipboard(text, message) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast(message, 'success');
    } catch (err) {
        showToast('فشل في النسخ', 'error');
    }
    
    document.body.removeChild(textArea);
}

function exportOrders() {
    // تصدير الطلبات كملف CSV
    const table = document.querySelector('.orders-table-container table');
    if (!table) {
        showToast('لا توجد بيانات للتصدير', 'error');
        return;
    }

    let csvContent = '';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach((row, index) => {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        
        cols.forEach((col, colIndex) => {
            // تنظيف النص من علامات HTML
            let cellText = col.textContent.trim();
            
            // إزالة الأيقونات والحفاظ على النص فقط
            cellText = cellText.replace(/[🔍📊👤🔍📥🔄⏳✅❌⚠️]/g, '');
            
            // تنسيق منفصل للحقول الحساسة
            if (colIndex === 0 || colIndex === 7) { // العمود الأول والأخير
                cellText = '"' + cellText.replace(/"/g, '""') + '"';
            } else {
                cellText = cellText.replace(/"/g, '""');
            }
            
            rowData.push(cellText);
        });
        
        csvContent += rowData.join(',') + '\n';
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `orders-${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('تم تصدير الطلبات بنجاح', 'success');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1000;
        font-weight: 600;
        animation: slideInOut 3s ease-in-out;
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-poll for order updates
    let orderPollingInterval;
    
    function startOrderPolling() {
        orderPollingInterval = setInterval(() => {
            // Implementation for admin order polling
            // Polling for order updates...
        }, 15000);
    }
    
    function stopOrderPolling() {
        if (orderPollingInterval) {
            clearInterval(orderPollingInterval);
        }
    }
    
    // Start polling when page loads
    startOrderPolling();
    
    // Stop polling when page becomes hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopOrderPolling();
        } else {
            startOrderPolling();
        }
    });
});
</script>

<style>
/* Same styles as account/orders.php */
.order-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
}

.status-icon {
    font-size: 1rem;
    line-height: 1;
}

.status-text {
    font-size: 0.75rem;
}

.status-success {
    background: var(--success-color);
    color: white;
    border: 1px solid #1e7e34;
}

.status-primary {
    background: var(--primary-color);
    color: white;
    border: 1px solid var(--color-primary-600);
}

.status-warning {
    background: var(--warning-color);
    color: var(--dark-bg);
    border: 1px solid #ffb300;
}

.status-error {
    background: var(--error-color);
    color: white;
    border: 1px solid #bd2130;
}

.status-info {
    background: #17a2b8;
    color: white;
    border: 1px solid #117a8b;
}

.order-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
}

.copy-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    align-items: center;
}

.copy-btn {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.copy-btn:hover {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
    transform: translateY(-1px);
}

/* Sticky Table Header */
@media (max-width: 768px) {
    .orders-table-container {
        max-height: 70vh;
        overflow-y: auto;
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
    }
    
    .orders-table-container thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--color-card);
        border-bottom: 2px solid var(--color-border);
    }
}

@keyframes slideInOut {
    0% { transform: translateX(100%); opacity: 0; }
    10%, 90% { transform: translateX(0); opacity: 1; }
    100% { transform: translateX(100%); opacity: 0; }
}
</style>

/* Enhanced Orders CSS Styles */
<style>
/* Filters Section */
.orders-filters-section {
    margin-bottom: 2rem;
}

.filters-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--border-color);
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-row:last-child {
    margin-bottom: 0;
    grid-template-columns: auto auto auto 1fr;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-select,
.filter-input {
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(26, 60, 140, 0.1);
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    justify-content: flex-end;
}

/* Enhanced Table Styles */
.clickable-row {
    cursor: pointer;
    transition: all 0.2s ease;
}

.clickable-row:hover {
    background: rgba(26, 60, 140, 0.05) !important;
    transform: translateX(-2px);
    box-shadow: inset 4px 0 0 var(--primary-color);
}

.copy-btn {
    background: none;
    border: none;
    padding: 0.25rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    opacity: 0.7;
    transition: all 0.3s ease;
}

.copy-btn:hover {
    opacity: 1;
    background: var(--color-elev);
    transform: scale(1.1);
}

/* Pagination Styles */
.orders-pagination {
    margin-top: 2rem;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pagination-btn {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    text-decoration: none;
    background: var(--card-bg);
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.9rem;
}

.pagination-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.pagination-current {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Order Details Drawer */
.order-details-drawer {
    position: fixed;
    top: 0;
    right: -100%;
    width: 400px;
    height: 100vh;
    background: rgba(0, 0, 0, 0.3);
    z-index: 10000;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.order-details-drawer.active {
    right: 0;
}

.drawer-content {
    width: 100%;
    height: 100vh;
    background: var(--card-bg);
    border-inline-start: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.2);
}

.drawer-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
}

.drawer-header h3 {
    margin: 0;
    font-size: 1.25rem;
}

.drawer-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.drawer-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.drawer-body {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    color: var(--text-primary);
}

.drawer-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
    background: var(--color-elev);
}

.order-detail-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.order-detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.order-detail-section h4 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.detail-item strong {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text-primary);
    font-weight: 600;
}

.detail-value.with-copy {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.copy-btn-mini {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.125rem 0.25rem;
    font-size: 0.7rem;
    opacity: 0.7;
    transition: all 0.3s ease;
    border-radius: 4px;
}

.copy-btn-mini:hover {
    opacity: 1;
    background: var(--color-elev);
}

.status-history {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.history-status {
    color: var(--text-primary);
    font-weight: 600;
}

.history-date {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.link-preview {
    padding: 0.75rem;
    background: var(--color-elev);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.link-preview a {
    color: var(--primary-color);
    word-break: break-all;
}

.loading-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.error-state {
    text-align: center;
    padding: 2rem;
    color: #dc3545;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .filter-row:last-child {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .filter-actions {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .order-details-drawer {
        width: 100%;
        right: -100%;
    }
    
    .order-details-drawer.active {
        right: 0;
    }
    
    .drawer-header {
        padding: 1rem;
    }
    
    .drawer-footer {
        padding: 1rem;
    }
    
    .pagination-nav {
        gap: 0.25rem;
    }
    
    .pagination-btn {
        padding: 0.375rem 0.5rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 430px) {
    .filters-card {
        padding: 1rem;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .drawer-body {
        padding: 1rem;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    /* Mobile table scrolling */
    .orders-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .orders-table {
        min-width: 600px;
    }
    
    /* Sticky header on mobile */
    .orders-table thead th {
        position: sticky;
        top: 0;
        background: var(--ad-card);
        z-index: 10;
        border-bottom: 2px solid var(--ad-border);
    }
    
    .drawer-footer {
        flex-direction: column;
    }
}

/* Form validation */
.input.error, .filter-input.error {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25);
}
</style>

<script>
// Form validation with Arabic error messages for orders search
document.addEventListener('DOMContentLoaded', function() {
    // Lazy load heavy tables when visible
    const tableObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('loaded');
                // Trigger any table-specific rendering
                if (entry.target.classList.contains('orders-table')) {
                    entry.target.style.willChange = 'auto';
                }
            }
        });
    }, { rootMargin: '50px' });
    
    // Observe heavy tables
    const heavyTables = document.querySelectorAll('.orders-table, .proces-data-table');
    heavyTables.forEach(table => {
        table.style.willChange = 'scroll-position';
        tableObserver.observe(table);
    });
    const searchInput = document.getElementById('search-filter');
    const searchError = document.getElementById('search-error');
    
    function validateSearch() {
        const searchTerm = searchInput.value.trim();
        if (searchTerm.length > 0 && searchTerm.length < 3) {
            showFieldError(searchInput, searchError, 'البحث يجب أن يكون 3 أحرف أو أكثر');
            return false;
        }
        hideFieldError(searchInput, searchError);
        return true;
    }
    
    function showFieldError(input, errorElement, message) {
        input.classList.add('error');
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
    
    function hideFieldError(input, errorElement) {
        input.classList.remove('error');
        errorElement.classList.remove('show');
    }
    
    // Validation on blur
    if (searchInput && searchError) {
        searchInput.addEventListener('blur', validateSearch);
    }
    
    // Unified Toast Notification System
    window.showToast = function(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        
        const icons = {
            'success': '✅',
            'error': '❌', 
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="window.closeToast(this.parentElement)">×</button>
        `;
        
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remove
        setTimeout(() => {
            if (toast.parentElement) {
                window.closeToast(toast);
            }
        }, duration);
    };
    
    window.closeToast = function(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    };
});
</script>

<?php if (!$isAdmin): ?>
<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
<?php else: ?>
    </main>
</div>
</body>
</html>
<?php endif; ?>
