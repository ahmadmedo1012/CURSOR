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

$pageTitle = 'إدارة الخدمات';
$pageDescription = 'عرض وإدارة جميع الخدمات المتاحة في النظام';

// Check if admin session exists
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin) {
    require_once __DIR__ . '/../templates/partials/header.php';
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
    <!-- Fonts will be loaded from system stack -->
    
    <!-- Critical CSS inline -->
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
  inset-inline-start: 0;
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
  inset-inline-start: 0;
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
.btn--outline{ background:transparent; color:var(--ad-text); border:1px solid var(--ad-border); }
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

/* AA+ Contrast Enhancements */
.btn,
a.btn,
button {
  background-color: #D6B544;
  color: #000000;
  border: 1px solid #B38B20;
}

.btn:hover,
a.btn:hover,
button:hover {
  background-color: #E6C555;
  color: #000000;
}

.btn:focus-visible,
a.btn:focus-visible,
button:focus-visible {
  outline: 3px solid #D6B544;
  outline-offset: 2px;
  background-color: #D6B544;
  color: #000000;
}

.btn--outline {
  background-color: transparent;
  color: #E9EDF6;
  border: 1px solid rgba(255,255,255,0.2);
}

.btn--outline:hover {
  background-color: rgba(255,255,255,0.05);
  color: #E9EDF6;
}

.btn--outline:focus-visible {
  outline: 3px solid #D6B544;
  outline-offset: 2px;
  background-color: rgba(255,255,255,0.05);
  color: #E9EDF6;
}

/* Links AA+ Contrast */
a {
  color: #D6B544;
  text-decoration: none;
}

a:hover {
  color: #E6C555;
  text-decoration: underline;
}

a:focus-visible {
  outline: 3px solid #D6B544;
  outline-offset: 2px;
  color: #E6C555;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .btn, button {
    background-color: #000000;
    color: #ffffff;
    border: 2px solid #ffffff;
  }
  
  a {
    color: #ffffff;
    text-decoration: underline;
  }
  
  a:hover, a:focus-visible {
    color: #ffff00;
    background-color: #000000;
  }
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
        
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Tajawal', 'Helvetica Neue', Arial, sans-serif; 
            font-size: 14px; 
            line-height: 1.5; 
            color: var(--ad-text); 
            background: var(--ad-bg);
            font-display: swap;
        }
        
        .admin-body { 
            background: var(--ad-bg); 
            color: var(--ad-text);
            min-height: 100vh;
        }
        
        .container-fluid {
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Table skeleton loading */
        .table-skeleton {
            background: linear-gradient(90deg, var(--color-elev) 25%, var(--border-color) 50%, var(--color-elev) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px;
            height: 2rem;
            margin: 0.25rem 0;
        }
        
        @keyframes skeleton-loading { 
            0% { background-position: 200% 0; } 
            100% { background-position: -200% 0; } 
        }
        
        /* High contrast for accessibility */
        @media (prefers-contrast: high) {
            :root {
                --primary-color: #005cee;
                --text-primary: #000000;
                --card-bg: #ffffff;
                --border-color: #000000;
            }
        }
        
        /* Focus styles */
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
</head>
<body class="admin-body">
<?php } ?>

<!-- Services Data Analytics -->
<?php
try {
    // Get filter parameters from request
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 24; // More reasonable default per page
    // Offset calculation moved after filtering
    
    $platformFilter = trim($_GET['platform'] ?? '');
    $typeFilter = trim($_GET['type'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $searchQuery = trim($_GET['search'] ?? '');
    $sortBy = $_GET['sort'] ?? 'sort_order';
    $sortOrder = $_GET['order'] ?? 'DESC';
    
    // Validate sort parameters
    $allowedSorts = ['sort_order', 'orders_count', 'rate_per_1k_lyd', 'name', 'updated_at'];
    $allowedOrders = ['ASC', 'DESC'];
    
    $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'sort_order';
    $sortOrder = in_array($sortOrder, $allowedOrders) ? $sortOrder : 'DESC';
    
    // Build WHERE conditions with NULL-safe flags
    $whereConditions = [
        'COALESCE(s.is_deleted, 0) = 0',
        'COALESCE(s.is_active, 1) = 1'
    ];
    $params = [];
    
    // Platform filter (group_slug)
    if (!empty($platformFilter)) {
        $whereConditions[] = 's.group_slug = ?';
        $params[] = $platformFilter;
    }
    
    // Type filter (subcategory)
    if (!empty($typeFilter)) {
        $whereConditions[] = 's.subcategory = ?';
        $params[] = $typeFilter;
    }
    
    // Status filter (visible/active) - respect additional visibility
    if (!empty($statusFilter)) {
        if ($statusFilter === 'active') {
            $whereConditions[] = 'COALESCE(s.is_visible, 1) = 1';
        } elseif ($statusFilter === 'hidden') {
            $whereConditions[] = 'COALESCE(s.is_visible, 1) = 0';
        }
    }
    
    // Text search (case-insensitive) - search multiple fields
    if (!empty($searchQuery)) {
        $whereConditions[] = '(s.name LIKE ? OR s.name_ar LIKE ? OR s.description LIKE ? OR s.vendor_service_id LIKE ?)';
        $searchTerm = "%{$searchQuery}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get platform options for filter
    $platforms = Database::fetchAll(
        "SELECT DISTINCT group_slug, 
                CASE 
                    WHEN group_slug = 'tiktok' THEN 'تيك توك'
                    WHEN group_slug = 'instagram' THEN 'إنستغرام' 
                    WHEN group_slug = 'facebook' THEN 'فيسبوك'
                    WHEN group_slug = 'youtube' THEN 'يوتيوب'
                    WHEN group_slug = 'twitter' THEN 'تويتر'
                    WHEN group_slug = 'telegram' THEN 'تيليجرام'
                    ELSE group_slug 
                END as platform_name
         FROM vendors_services_cache s 
         WHERE group_slug IS NOT NULL
         ORDER BY platform_name",
        []
    );
    
    // Get type options for filter
    $types = Database::fetchAll(
        "SELECT DISTINCT subcategory as type_value, subcategory as type_name
         FROM vendors_services_cache s 
         WHERE subcategory IS NOT NULL AND subcategory != ''
         ORDER BY subcategory",
        []
    );
    
    // Count results for pagination - same WHERE as data query
    $totalCount = Database::fetchColumn(
        "SELECT COUNT(*) FROM vendors_services_cache s WHERE {$whereClause}",
        $params
    );
    
    // Clamp page to valid range
    $totalPages = max(1, ceil($totalCount / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    // Get services with efficient query (avoid SELECT *)
    $services = Database::fetchAll(
        "SELECT s.vendor_service_id,
                COALESCE(s.name_ar, s.name) as service_name,
                s.group_slug as platform,
                s.subcategory as service_type,
                s.rate_per_1k_lyd as price_per_1k,
                s.min_quantity as min_qty,
                s.max_quantity as max_qty,
                CONCAT(s.speed, ' دقيقة') as speed,
                CASE WHEN COALESCE(s.visible, 1) = 1 THEN 'نشط' ELSE 'مخفي' END as status_text,
                COALESCE(s.visible, 1) as status_flag,
                s.provider,
                s.updated_at,
                COALESCE(s.orders_count, 0) as orders_count,
                s.sort_order
         FROM vendors_services_cache s 
         WHERE {$whereClause}
         ORDER BY {$sortBy} {$sortOrder}, s.name ASC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    
    // Calculate pagination info
    $totalPages = ceil($totalCount / $perPage);
    $hasNextPage = $page < $totalPages;
    $hasPreviousPage = $page > 1;
    
} catch (Exception $e) {
    $services = [];
    $platforms = [];
    $types = [];
    $totalCount = 0;
    $errorMessage = "خطأ في جلب الخدمات: " . $e->getMessage();
}
?>

<!-- Admin Shell Layout -->
<?php if ($isAdmin): ?>
<style>
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
    gap: 1rem;
    padding: 0 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.admin-content {
    margin-top: 64px;
    min-height: calc(100vh - 64px);
    padding: 20px;
}
</style>

<div class="admin-wrapper">
    <header class="admin-topbar">
        <button type="button" onclick="location.href='/admin/'" class="btn btn-sm">← العودة للصفحة الرئيسية</button>
        <div style="flex: 1; text-align: center; font-weight: 700; color: var(--primary-color);">
            <?php echo APP_NAME; ?> - إدارة الخدمات
        </div>
    </header>
    
    <main class="admin-content">
<?php endif; ?>

<div class="<?php echo !$isAdmin ? 'container-fluid' : ''; ?>">
    <!-- Page Header -->
    <div class="page-header" style="background: var(--card-bg); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12); text-align: center;">
        <h1 style="margin: 0 0 0.5rem 0; font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, var(--primary-color), var(--color-accent)); background-clip: text; -webkit-background-clip: text; color: transparent; -webkit-text-fill-color: transparent;">
            <?php echo $pageTitle; ?>
        </h1>
        <p style="margin: 0; color: var(--text-secondary); font-size: 1rem;">
            إدارة وتحسين أداء الخدمات المتاحة
        </p>
    </div>

    <!-- Services Filters -->
    <div class="services-filters-section">
        <form method="GET" id="services-filter-form" class="filters-card">
            <!-- Filter Row 1: Platform & Type -->
            <div class="filter-row">
                <div class="filter-group">
                    <label for="platform-filter" class="filter-label">📱 المنصة</label>
                    <select id="platform-filter" name="platform" class="filter-select">
                        <option value="">جميع المنصات</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo htmlspecialchars($platform['group_slug']); ?>" 
                                    <?php echo $platformFilter === $platform['group_slug'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($platform['platform_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="type-filter" class="filter-label">🏷️ النوع</label>
                    <select id="type-filter" name="type" class="filter-select">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['type_value']); ?>"
                                    <?php echo $typeFilter === $type['type_value'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status-filter" class="filter-label">📊 الحالة</label>
                    <select id="status-filter" name="status" class="filter-select">
                        <option value="">جميع الحالات</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>✅ نشط فقط</option>
                        <option value="hidden" <?php echo $statusFilter === 'hidden' ? 'selected' : ''; ?>>❌ مخفي فقط</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="search-filter" class="filter-label">🔍 البحث</label>
                    <input type="text" 
                           id="search-filter" 
                           name="search" 
                           class="filter-input" 
                           placeholder="ابحث في أسماء الخدمات..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
            </div>

            <!-- Filter Row 2: Sort & Actions -->
            <div class="filter-row">
                <div class="filter-group">
                    <label for="sort-select" class="filter-label">🔄 الترتيب</label>
                    <select id="sort-select" name="sort" class="filter-select">
                        <option value="sort_order" <?php echo $sortBy === 'sort_order' ? 'selected' : ''; ?>>ترتيب افتراضي</option>
                        <option value="orders_count" <?php echo $sortBy === 'orders_count' ? 'selected' : ''; ?>>الشعبية (أكثر طلبات)</option>
                        <option value="rate_per_1k_lyd" <?php echo $sortBy === 'rate_per_1k_lyd' ? 'selected' : ''; ?>>السعر (أقل أولاً)</option>
                        <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>الأبجدية (أ ب ت)</option>
                        <option value="updated_at" <?php echo $sortBy === 'updated_at' ? 'selected' : ''; ?>>الحديث (أولاً)</option>
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
                        <span class="btn-icon">🔍</span> تطبيق الفلاتر
                    </button>
                    <button type="button" class="btn" onclick="clearFilters()">
                        <span class="btn-icon">🔄</span> إعادة تعيين
                    </button>
                    <button type="button" class="btn btn-success" onclick="bulkSyncServices()">
                        <span class="btn-icon">🔄</span> مزامنة جديدة
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-error" style="margin: 1rem 0; padding: 1rem; background: #fee; border: 1px solid #fcc; border-radius: 6px; color: #c33;">
            <strong>⚠️ خطأ:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Services Table -->
    <?php if (!empty($services)): ?>
        <div class="services-table-section">
            <div class="table-header">
                <div class="results-info">
                    <span class="results-count"><?php echo number_format($totalCount); ?> خدمة</span>
                    <span class="results-separator">|</span>
                    <span class="page-info">صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></span>
                </div>
                
                <div class="bulk-actions">
                    <button type="button" class="btn btn-sm" onclick="selectAllServices()">
                        <span class="btn-icon">☑️</span> تحديد الكل
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="bulkToggleVisibility()">
                        <span class="btn-icon">👁️</span> تبديل الرؤية
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="bulkReorderServices()">
                        <span class="btn-icon">📊</span> إعادة ترتيب
                    </button>
                </div>
            </div>

            <div class="services-table-container">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th class="checkbox-col">
                                <input type="checkbox" id="select-all-checkbox" onchange="toggleAllSelection(this)" aria-label="تحديد جميع الخدمات">
                            </th>
                            <th class="name-col">الخدمة</th>
                            <th class="platform-col">المنصة</th>
                            <th class="type-col">النوع</th>
                            <th class="price-col">السعر/1ك</th>
                            <th class="limits-col">الحدود</th>
                            <th class="speed-col">السرعة</th>
                            <th class="status-col">الحالة</th>
                            <th class="provider-col">المزود</th>
                            <th class="updated-col">آخر تحديث</th>
                        </tr>
                    </thead>
                    <tbody id="services-table-body">
                        <!-- Temporary skeleton loading while JavaScript initializes -->
                        <?php foreach ($services as $service): ?>
                            <tr class="service-row" data-service-id="<?php echo $service['vendor_service_id']; ?>">
                                <td class="checkbox-col">
                                    <input type="checkbox" class="service-checkbox" value="<?php echo $service['vendor_service_id']; ?>">
                                </td>
                                
                                <td class="name-col">
                                    <div class="service-name">
                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                        <div class="service-meta">
                                            <small><?php echo Formatters::formatQuantity($service['orders_count']); ?> طلب</small>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="platform-col">
                                    <span class="platform-badge platform-<?php echo $service['platform']; ?>">
                                        <?php
                                        $platformIcons = [
                                            'instagram' => '📷 Instagram',
                                            'tiktok' => '🎵 TikTok', 
                                            'facebook' => '👥 Facebook',
                                            'youtube' => '📺 YouTube',
                                            'twitter' => '🐦 Twitter',
                                            'telegram' => '✈️ Telegram'
                                        ];
                                        echo $platformIcons[$service['platform']] ?? ucfirst($service['platform']);
                                        ?>
                                    </span>
                                </td>
                                
                                <td class="type-col">
                                    <span class="type-label"><?php echo htmlspecialchars($service['service_type']); ?></span>
                                </td>
                                
                                <td class="price-col">
                                    <span class="price-amount"><?php echo Formatters::formatMoney($service['price_per_1k']); ?></span>
                                    <small class="price-currency">LYD</small>
                                </td>
                                
                                <td class="limits-col">
                                    <div class="limits-range">
                                        <?php echo number_format($service['min_qty']); ?> - 
                                        <?php echo number_format($service['max_qty']); ?>
                                    </div>
                                </td>
                                
                                <td class="speed-col">
                                    <span class="speed-time"><?php echo htmlspecialchars($service['speed']); ?></span>
                                </td>
                                
                                <td class="status-col">
                                    <span class="status-badge status-<?php echo $service['status_flag'] ? 'active' : 'hidden'; ?>">
                                        <?php echo $service['status_text']; ?>
                                    </span>
                                </td>
                                
                                <td class="provider-col">
                                    <span class="provider-name"><?php echo htmlspecialchars($service['provider']); ?></span>
                                </td>
                                
                                <td class="updated-col">
                                    <span class="update-time" title="<?php echo date('Y-m-d H:i:s', strtotime($service['updated_at'])); ?>">
                                        <?php echo date('m/d', strtotime($service['updated_at'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="services-pagination">
                    <nav class="pagination-nav" aria-label="تنقل بين صفحات الخدمات">
                        <?php if ($hasPreviousPage): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                               class="pagination-btn pagination-first" title="الصفحة الأولي">
                                ⏮️ أولى
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="pagination-btn pagination-prev" title="الصفحة السابقة">
                                ◀️ سابق
                            </a>
                        <?php endif; ?>
                        
                        <span class="pagination-info">
                            صفحة <?php echo $page; ?> من <?php echo $totalPages; ?>
                        </span>
                        
                        <?php if ($hasNextPage): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="pagination-btn pagination-next" title="الصفحة التالية">
                                التالية ▶️
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                               class="pagination-btn pagination-last" title="الصفحة الأخيرة">
                                أخيرة ⏭️
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>لا توجد خدمات</h3>
            <p>لم يتم العثور على خدمات تطابق معايير البحث المحددة.</p>
            <button type="button" class="btn btn-primary" onclick="clearFilters()">عرض جميع الخدمات</button>
        </div>
    <?php endif; ?>
</div>

<?php if (!$isAdmin): ?>
<?php require_once __DIR__ . '/../templates/partials/footer.php'; ?>
<?php else: ?>
    </main>
</div>
</body>
</html>
<?php endif; ?>

<?php
// Include enhanced JavaScript functionality
if ($isAdmin): ?>
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

// Services Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Lazy load heavy tables when visible
    const tableObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('loaded');
                // Trigger any table-specific rendering
                if (entry.target.classList.contains('services-table')) {
                    entry.target.style.willChange = 'auto';
                }
            }
        });
    }, { rootMargin: '50px' });
    
    // Observe heavy tables
    const heavyTables = document.querySelectorAll('.services-table, .bulk-actions-table');
    heavyTables.forEach(table => {
        table.style.willChange = 'scroll-position';
        tableObserver.observe(table);
    });
    
    // Load filters from localStorage
    loadFiltersFromStorage();
    
    // Setup filter form auto-save
    const filterForm = document.getElementById('services-filter-form');
    const filterInputs = filterForm.querySelectorAll('input, select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', saveFiltersToStorage);
    });
    
    // Setup search debouncing
    const searchInput = document.getElementById('search-filter');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            saveFiltersToStorage();
            submitFilters();
        }, 350);
    });
});

function loadFiltersFromStorage() {
    const filters = localStorage.getItem('services_filters');
    if (filters) {
        try {
            const filterData = JSON.parse(filters);
            
            // Restore filter values
            if (filterData.platform) {
                document.getElementById('platform-filter').value = filterData.platform;
            }
            if (filterData.type) {
                document.getElementById('type-filter').value = filterData.type;
            }
            if (filterData.status) {
                document.getElementById('status-filter').value = filterData.status;
            }
            if (filterData.search) {
                document.getElementById('search-filter').value = filterData.search;
            }
            if (filterData.sort) {
                document.getElementById('sort-select').value = filterData.sort;
            }
            if (filterData.order) {
                document.getElementById('order-select').value = filterData.order;
            }
        } catch (e) {
            // Could not load stored filters
        }
    }
}

function saveFiltersToStorage() {
    const filterData = {
        platform: document.getElementById('platform-filter').value,
        type: document.getElementById('type-filter').value,
        status: document.getElementById('status-filter').value,
        search: document.getElementById('search-filter').value,
        sort: document.getElementById('sort-select').value,
        order: document.getElementById('order-select').value,
        timestamp: Date.now()
    };
    
    localStorage.setItem('services_filters', JSON.stringify(filterData));
}

function clearFilters() {
    // Reset form
    document.getElementById('services-filter-form').reset();
    
    // Clear localStorage
    localStorage.removeItem('services_filters');
    
    // Redirect without query parameters
    window.location.href = '/admin/services.php';
}

// Bulk actions
function selectAllServices() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    
    serviceCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function toggleAllSelection(selectAllCheckbox) {
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    serviceCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function bulkToggleVisibility() {
    const selectedServices = document.querySelectorAll('.service-checkbox:checked');
    
    if (selectedServices.length === 0) {
        // Silent handling in production
        return;
    }
    
    if (confirm(`هل تريد تبديل حالة الرؤية لـ ${selectedServices.length} خدمة؟`)) {
        // Implementation would go here - AJAX call to update visibility
        // Silent handling in production
        
        // Refresh the page to reflect changes
        window.location.reload();
    }
}

function bulkReorderServices() {
    const selectedServices = document.querySelectorAll('.service-checkbox:checked');
    
    if (selectedServices.length === 0) {
        // Silent handling in production
        return;
    }
    
    if (confirm(`هل تريد إعادة ترتيب ${selectedServices.length} خدمة حسب الشعبية؟`)) {
        // Implementation would go here - AJAX call to reorder services
        // Silent handling in production
        
        // Refresh the page to reflect changes
        window.location.reload();
    }
}

function bulkSyncServices() {
    if (confirm('هل تريد مزامنة جميع الخدمات من المزودين؟')) {
        // Redirect to sync page or AJAX call
        window.location.href = '/admin/sync.php';
    }
}
</script>

<style>
/* Services Table Styles */
.services-filters-section {
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

.services-table-section {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.results-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.results-separator {
    color: var(--border-color);
}

.bulk-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.services-table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.services-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.services-table th {
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
    padding: 1rem 0.75rem;
    font-weight: 600;
    text-align: center;
    font-size: 0.9rem;
}

.services-table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 0.9rem;
}

.services-table tr:hover {
    background: rgba(26, 60, 140, 0.05);
}

.service-name {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.service-name strong {
    color: var(--text-primary);
    font-weight: 600;
}

.service-meta {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.platform-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--color-elev);
    color: var(--text-primary);
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: capitalize;
}

.status-active {
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.status-hidden {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.price-amount {
    font-weight: 700;
    color: var(--color-accent);
    font-size: 1rem;
}

.price-currency {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-right: 0.25rem;
}

.limits-range {
    font-weight: 600;
    color: var(--text-primary);
}

.speed-time {
    padding: 0.25rem 0.5rem;
    background: var(--color-elev);
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.provider-name {
    font-weight: 600;
    color: var(--text-primary);
}

.update-time {
    font-size: 0.8rem;
    color: var(--text-secondary);
    padding: 0.25rem 0.5rem;
    background: var(--color-elev);
    border-radius: 8px;
}

.services-pagination {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}

.pagination-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pagination-btn {
    padding: 0.75rem 1rem;
    background: var(--color-elev);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.pagination-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.pagination-info {
    padding: 0.75rem 1rem;
    background: var(--color-elev);
    border-radius: 12px;
    font-weight: 600;
    color: var(--text-primary);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.6;
}

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
    font-size: 1.5rem;
}

.empty-state p {
    color: var(--text-secondary);
    margin: 0 0 2rem 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .filter-row:last-child {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .table-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .bulk-actions {
        justify-content: center;
    }
    
    .services-table th,
    .services-table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    .checkbox-col,
    .platform-col,
    .type-col,
    .limits-col,
    .speed-col,
    .provider-col,
    .updated-col {
        display: none;
    }
}

@media (max-width: 430px) {
    .services-table th,
    .services-table td {
        padding: 0.375rem 0.125rem;
        font-size: 0.75rem;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .services-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .services-table {
        min-width: 600px;
    }
    
    /* Sticky header on mobile */
    .services-table thead th {
        position: sticky;
        top: 0;
        background: var(--ad-card);
        z-index: 10;
        border-bottom: 2px solid var(--ad-border);
    }
}

/* Screen reader and accessibility styles */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Enhanced focus styles for table controls */
.services-table input[type="checkbox"]:focus-visible,
.filter-select:focus-visible,
.filter-input:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Table loading skeleton */
.table-loading .skeleton-row {
    display: table-row !important;
}

.table-loaded .skeleton-row {
    display: none !important;
}

/* Performance optimizations */
.services-table-container {
    contain: layout style paint;
}

.service-row {
    contain: layout style;
}

/* Reduced motion for accessibility */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<!-- Optimized JavaScript with lazy loading and accessibility -->
<script>
// Services page optimizations for performance
document.addEventListener('DOMContentLoaded', function() {
    // Initialize services table lazily
    initializeServicesTable();
    
    // Initialize filters with accessibility
    initializeFilters();
    
    // Initialize debounced search
    initializeDebouncedSearch();
});

function initializeServicesTable() {
    const tableBody = document.getElementById('services-table-body');
    if (!tableBody) return;
    
    // Add accessibility attributes
    const table = tableBody.closest('table');
    table.setAttribute('role', 'table');
    table.setAttribute('aria-label', 'جدول الخدمات');
    
    // Remove loading state and announce to screen readers
    table.classList.add('table-loaded');
    announceToScreenReader('Services table loaded with ' + tableBody.children.length + ' services');
}

function initializeFilters() {
    const filterInputs = document.querySelectorAll('.filter-input, .filter-select');
    
    filterInputs.forEach(input => {
        // Add accessibility labels
        const label = input.previousElementSibling;
        if (label) {
            input.setAttribute('aria-labelledby', label.id || '');
            if (!label.id) {
                label.id = 'label-' + input.name;
                input.setAttribute('aria-labelledby', label.id);
            }
        }
        
        // Add keyboard navigation
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitFilters();
            } else if (e.key === 'Escape') {
                clearFilters();
            }
        });
    });
}

function initializeDebouncedSearch() {
    const searchInput = document.getElementById('search-filter');
    if (!searchInput) return;
    
    searchInput.setAttribute('role', 'searchbox');
    searchInput.setAttribute('aria-label', 'البحث في الخدمات');
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        showLoadingIndicator();
            
        searchTimeout = setTimeout(() => {
            saveFiltersToStorage();
            submitFilters();
        }, 350);
    });
    
    // Add search clear button
    const clearSearch = document.createElement('button');
    clearSearch.type = 'button';
    clearSearch.className = 'btn btn-sm btn-outline';
    clearSearch.textContent = 'مسح';
    clearSearch.setAttribute('aria-label', 'مسح البحث');
    clearSearch.addEventListener('click', () => {
        searchInput.value = '';
        clearFilters();
    });
    
    searchInput.parentNode.appendChild(clearSearch);
}

function showLoadingIndicator() {
    const tableContainer = document.querySelector('.services-table-container');
    if (tableContainer) {
        tableContainer.classList.add('table-loading');
        
        // Remove loading after a reasonable delay
        setTimeout(() => {
            tableContainer.classList.remove('table-loading');
            tableContainer.classList.add('table-loaded');
        }, 500);
    }
}

function submitFilters() {
    // Silent form submission without console warnings
    try {
        document.querySelector('.filters-form').submit();
    } catch (error) {
        // Handle form submission quietly
        window.location.reload();
    }
}

function clearFilters() {
    const filterInputs = document.querySelectorAll('.filter-input, .filter-select');
    filterInputs.forEach(input => {
        if (input.type === 'checkbox') {
            input.checked = false;
        } else {
            input.value = '';
        }
    });
    
    localStorage.removeItem('services_filters');
    submitFilters();
}

function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);
    
    setTimeout(() => announcement.remove(), 1000);
}

// Performance: Lazy load table heavy features
setTimeout(() => {
    initializeTableAdvancedFeatures();
}, 100);

function initializeTableAdvancedFeatures() {
    // Initialize select all checkbox functionality
    initializeSelectAll();
    
    // Initialize row selection highlighting
    initializeRowSelection();
    
    // Initialize pagination accessibility
    initializePagination();
}

function initializeSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    
    if (!selectAllCheckbox) return;
    
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
            checkbox.closest('tr').classList.toggle('selected', isChecked);
        });
        
        announceToScreenReader(isChecked ? 'جميع الخدمات محددة' : 'تم إلغاء تحديد جميع الخدمات');
    });
}

function initializeRowSelection() {
    const serviceRows = document.querySelectorAll('.service-row');
    
    serviceRows.forEach(row => {
        const checkbox = row.querySelector('.service-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                row.classList.toggle('selected', this.checked);
            });
            
            row.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        }
    });
}

function initializePagination() {
    const paginationLinks = document.querySelectorAll('.services-pagination a, .pagination-btn');
    
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Show loading state during navigation
            showLoadingIndicator();
        });
    });
}

// Existing functions (optimized):
function saveFiltersToStorage() {
    try {
        const filterData = {
            platform: document.getElementById('platform-filter')?.value || '',
            type: document.getElementById('type-filter')?.value || '',
            status: document.getElementById('status-filter')?.value || '',
            search: document.getElementById('search-filter')?.value || '',
            sort: document.getElementById('sort-select')?.value || '',
            order: document.getElementById('order-select')?.value || '',
            timestamp: Date.now()
        };
        localStorage.setItem('services_filters', JSON.stringify(filterData));
    } catch (error) {
        // Handle storage errors gracefully
    }
}
</script>

<!-- Defer main JavaScript bundle for better performance -->
<script>
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadMainBundle);
} else {
    loadMainBundle();
}

function loadMainBundle() {
    const script = document.createElement('script');
    script.src = '<?php echo asset_url('assets/js/app.min.js'); ?>';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
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
</script>

<?php endif; ?>
