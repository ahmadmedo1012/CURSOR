<?php
require_once '../config/config.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'لوحة الإدارة';
$adminUser = $_SESSION['admin_user'] ?? 'admin';

// Compute admin KPIs with real data - robust error handling
try {
    // Orders today (00:00 - now)
    $ordersToday = Database::fetchColumn(
        "SELECT COALESCE(COUNT(*), 0) FROM orders WHERE created_at >= CURDATE()",
        []
    );
    
    // Revenue today (LYD) - only processing, completed, partial orders
    $revenueToday = Database::fetchColumn(
        "SELECT COALESCE(SUM(COALESCE(price_lyd, 0)), 0) FROM orders 
         WHERE created_at >= CURDATE() 
         AND status IN ('processing', 'completed', 'partial')
         AND COALESCE(is_deleted, 0) = 0",
        []
    );
    
    // Average fulfillment time (last 7 days) - only valid completions
    $avgFulfillmentTime = Database::fetchColumn(
        "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)), 0) 
         FROM orders 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND completed_at IS NOT NULL 
         AND completed_at > created_at
         AND TIMESTAMPDIFF(MINUTE, created_at, completed_at) < 10000 
         AND COALESCE(is_deleted, 0) = 0",
        []
    );
    
    // Tickets open - notifications marked as open
    $ticketsOpen = Database::fetchColumn(
        "SELECT COALESCE(COUNT(*), 0) FROM notifications 
         WHERE COALESCE(status, 'open') = 'open' 
         AND COALESCE(is_deleted, 0) = 0",
        []
    );
    
    // Provider health check (balance > 0 is considered "up")
    $providerHealth = Database::fetchAll(
        "SELECT 
            COALESCE(COUNT(CASE WHEN balance_usd > 10 THEN 1 END), 0) as healthy_providers,
            COALESCE(COUNT(*), 0) as total_providers
         FROM providers",
        []
    );
    
    // 14-day sparklines data (orders and revenue)
    $sparklinesData = Database::fetchAll(
        "SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders_count,
            COALESCE(SUM(CASE WHEN status IN ('processing', 'completed', 'partial') 
                          THEN price_lyd ELSE 0 END), 0) as revenue
         FROM orders 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC",
        []
    );
    
    // Format sparklines data for JavaScript
    $sparklinesJS = '';
    if (!empty($sparklinesData)) {
        $ordersData = json_encode(array_column($sparklinesData, 'orders_count'));
        $revenueData = json_encode(array_column($sparklinesData, 'revenue'));
        $dates = json_encode(array_column($sparklinesData, 'date'));
        
        $sparklinesJS = "
            window.sparklinesData = {
                orders: {$ordersData},
                revenue: {$revenueData},
                dates: {$dates}
            };
        ";
    } else {
        $sparklinesJS = "window.sparklinesData = { orders: [], revenue: [], dates: [] };";
    }
    
} catch (Exception $e) {
    // Fallback values if database query fails - ensure proper data types
    $ordersToday = 0;
    $revenueToday = 0.00;
    $avgFulfillmentTime = 0;
    $ticketsOpen = 0;
    $providerHealth = [['healthy_providers' => 0, 'total_providers' => 0]];
    $sparklinesJS = "window.sparklinesData = { orders: [], revenue: [], dates: [] };";
    
    // Log error for debugging without exposing to user
    error_log("Admin KPIs error: " . $e->getMessage());
}

// Check if admin session exists
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin) {
include '../templates/partials/header.php';
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
    
    <!-- Critical CSS inline for above-the-fold content -->
    <style>
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
  --ad-bg:#F7F9FD; 
  --ad-card:#FFFFFF; 
  --ad-elev:#EFF3FA;
  --ad-text:#1B2437; 
  --ad-muted:#5E6B86; 
  --ad-border:rgba(10,20,40,.1);
  --ad-shadow:0 4px 12px rgba(0,0,0,.08);
  
  /* Additional light theme variables */
  --primary-color: #1A3C8C;
  --color-accent: #C9A227;
  --color-text: #1B2437;
  --color-elev: #EFF3FA;
  --color-border: rgba(10,20,40,.1);
  --card-bg: #FFFFFF;
  --text-primary: #1B2437;
  --text-secondary: #5E6B86;
  --text-tertiary: rgba(26, 34, 52, 0.6);
  --color-gold: #D4B73A;
}

/* Shell */
.admin-body{ 
    background:var(--ad-bg); 
    color:var(--ad-text); 
    transition: background-color 0.3s ease, color 0.3s ease;
}

/* Smooth transitions for theme changes */
.admin-card,
.admin-action-btn,
.nav-item .nav-link,
.admin-search input,
.admin-sidebar,
.admin-topbar,
.kpi-card {
    transition: background-color 0.3s ease, 
                color 0.3s ease, 
                border-color 0.3s ease, 
                box-shadow 0.3s ease,
                transform 0.3s ease;
}

/* Prevent FOUC */
.is-preload * { 
    transition: none !important; 
    animation: none !important; 
}
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
  border: 1px solid #B38F1F;
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
.table tbody tr:nth-child(even){ background:rgba(255,255,255,.02); }
.table td,.table th{ padding:12px 14px; border-bottom:1px solid var(--ad-border); vertical-align:middle; }

/* Status Badges */
.status-completed{ background:rgba(40,167,69,.2); color:#28a745; }
.status-processing{ background:rgba(255,193,7,.2); color:#ffc107; }
.status-pending{ background:rgba(108,117,125,.2); color:#6c757d; }
.status-failed{ background:rgba(220,53,69,.2); color:#dc3545; }

/* KPIs */
.kpi-card{
  background:var(--ad-card); border:1px solid var(--ad-border); border-radius:var(--ad-radius);
  padding:20px; box-shadow:0 1px 2px rgba(0,0,0,.16); position:relative; overflow:hidden;
}
.kpi-card::before{
  content:""; position:absolute; top:0; inset-inline-start:0; inset-inline-end:0; height:3px;
  background:linear-gradient(90deg,var(--ad-gold),#D6B544);
}

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
        
        .admin-wrapper { 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }
        
        .admin-topbar { 
            background: var(--ad-card); 
            border-bottom: 1px solid var(--ad-border); 
            padding: 1rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            z-index: 9000;
            position: relative;
        }
        
        .admin-topbar::after{
            content: "";
            display: block;
            height: 2px;
            background: linear-gradient(90deg, var(--ad-gold), #D6B544, var(--ad-gold));
            position: absolute;
            bottom: 0;
            inset-inline-start: 0;
            right: 0;
        }
        
        .sidebar-toggle { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            padding: 0.5rem; 
            border-radius: 6px; 
            transition: background-color 0.3s ease; 
        }
        
        .sidebar-toggle:hover, 
        .sidebar-toggle:focus-visible { 
            background: var(--color-elev); 
            outline: 2px solid var(--primary-color); 
            outline-offset: 2px; 
        }
        
        /* High contrast for accessibility */
        @media (prefers-contrast: high) {
            :root {
                --primary-color: #005cee;
                --text-primary: #000000;
                --card-bg: #ffffff;
                --border-color: #000000;
                --text-secondary: #333333;
            }
            
            .sidebar-toggle {
                border: 2px solid var(--text-primary);
            }
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .admin-topbar { 
                padding: 0.5rem; 
            }
        }
    </style>
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Load CSS with high priority -->
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
    
    <!-- Defer non-critical JavaScript -->
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
        
        // Prevent FOUC for critical admin interface
        document.documentElement.classList.add('is-preload');
        document.head.lastElementChild.onload = function() {
            document.documentElement.classList.remove('is-preload');
        };
    </script>
</head>
        <body class="admin-body">
        <?php } ?>

<?php if ($isAdmin): ?>
<!-- Admin Shell Layout -->
<style>
/* Admin Shell Styles */
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

.admin-topbar-brand {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary-color), var(--color-accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
}

.admin-search {
    flex: 1;
    max-width: 400px;
    position: relative;
    margin: 0 1rem;
}

.admin-search input {
    width: 100%;
    height: 40px;
    padding: 0 40px 0 16px;
    border: 1px solid var(--color-border);
    border-radius: 12px;
    background: var(--color-elev);
    color: var(--color-text);
    font-size: 0.95rem;
    outline: none;
    transition: all 0.3s ease;
}

.admin-search button {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    color: var(--color-text);
    opacity: 0.6;
}

.admin-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.admin-action-btn {
    width: 40px;
    height: 40px;
    background: var(--color-elev);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.admin-action-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Theme Toggle Button Styles */
.theme-toggle {
    position: relative;
    overflow: hidden;
}

.theme-icon {
    transition: transform 0.3s ease, opacity 0.3s ease;
    display: inline-block;
}

.theme-toggle:hover .theme-icon {
    transform: rotate(180deg);
}

/* Theme-specific styles */
[data-theme="light"] .theme-icon {
    content: "🌙";
}

[data-theme="dark"] .theme-icon {
    content: "🌙";
}

/* Light theme specific improvements */
[data-theme="light"] {
    /* Better contrast for light theme */
    --ad-shadow: 0 2px 8px rgba(0,0,0,.06);
}

[data-theme="light"] .admin-card {
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
}

[data-theme="light"] .admin-action-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(26, 60, 140, 0.3);
}

[data-theme="light"] .nav-item .nav-link:hover {
    background: rgba(26, 60, 140, 0.05);
}

[data-theme="light"] .nav-item .nav-link.active {
    background: linear-gradient(90deg, rgba(26, 60, 140, 0.08), transparent);
    color: var(--primary-color);
}

/* Light theme KPI cards */
[data-theme="light"] .kpi-card {
    background: #FFFFFF;
    border: 1px solid rgba(10,20,40,.08);
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

[data-theme="light"] .kpi-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.1);
    transform: translateY(-2px);
}

/* Light theme search input */
[data-theme="light"] .admin-search input {
    background: #FFFFFF;
    border: 1px solid rgba(10,20,40,.12);
    color: #1B2437;
}

[data-theme="light"] .admin-search input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(26, 60, 140, 0.1);
}

/* Light theme sidebar */
[data-theme="light"] .admin-sidebar {
    background: #FFFFFF;
    border-left: 1px solid rgba(10,20,40,.08);
    box-shadow: -4px 0 20px rgba(0,0,0,.08);
}

/* Light theme topbar */
[data-theme="light"] .admin-topbar {
    background: #FFFFFF;
    border-bottom: 1px solid rgba(10,20,40,.08);
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

/* Accessibility improvements for light theme */
[data-theme="light"] .admin-action-btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

[data-theme="light"] .nav-item .nav-link:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    [data-theme="light"] {
        --ad-text: #000000;
        --ad-muted: #333333;
        --ad-border: #000000;
        --primary-color: #0000FF;
    }
}

.admin-sidebar-toggle {
    width: 44px;
    height: 44px;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 4px;
}

.admin-sidebar-toggle::before,
.admin-sidebar-toggle::after,
.admin-sidebar-toggle span {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: var(--color-text);
    border-radius: 2px;
    transition: all 0.3s ease;
}

.admin-sidebar-toggle:hover {
    background: var(--color-elev);
    border-color: var(--primary-color);
}

.admin-sidebar {
    position: fixed;
    top: 64px;
    right: 0;
    width: 280px;
    height: calc(100vh - 64px);
    background: var(--card-bg);
    border-left: 1px solid var(--color-border);
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 999;
    overflow-y: auto;
    box-shadow: -4px 0 25px rgba(0, 0, 0, 0.1);
}

.admin-sidebar.open {
    transform: translateX(0);
}

.admin-sidebar-header {
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--color-border);
}

.admin-nav-list {
    list-style: none;
    margin: 0;
    padding: 1rem 0;
}

.nav-item .nav-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    color: var(--color-text);
    text-decoration: none;
    transition: all 0.2s ease;
    font-weight: 500;
    min-height: 44px;
}

.nav-item .nav-link:hover {
    background: var(--color-elev);
    color: var(--primary-color);
}

.nav-item .nav-link.active {
    background: linear-gradient(90deg, rgba(26, 60, 140, 0.1), transparent);
    color: var(--primary-color);
    border-right: 3px solid var(--primary-color);
}

.nav-icon {
    font-size: 1.2rem;
    width: 20px;
    text-align: center;
}

.admin-sidebar-overlay {
    position: fixed;
    top: 64px;
    inset-inline-start: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.admin-sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
}

.admin-content {
    margin-top: 64px;
    margin-right: 0;
    min-height: calc(100vh - 64px);
    transition: margin-right 0.3s ease;
    padding: 20px;
}

.sidebar-open .admin-content {
    margin-right: 280px;
}

@media (max-width: 768px) {
    .admin-sidebar {
        width: 100%;
        max-width: 320px;
    }
    
    .sidebar-open .admin-content {
        margin-right: 0;
    }
    
    .admin-search {
        display: none;
    }
}
</style>

<div class="admin-wrapper">
    <!-- Admin Topbar -->
    <header class="admin-topbar">
        <button type="button" class="admin-sidebar-toggle" id="sidebar-toggle" aria-label="فتح/إغلاق القائمة الجانبية"></button>
        
        <div class="admin-topbar-brand"><?php echo APP_NAME; ?> - الإدارة</div>
        
        <div class="admin-search">
            <input type="search" placeholder="بحث في الإدارة..." id="admin-search-input" aria-label="بحث شامل">
            <button type="button" onclick="triggerSearch()">🔍</button>
        </div>
        
        <div class="admin-actions">
            <!-- Theme Toggle Button -->
            <button type="button" class="admin-action-btn theme-toggle" id="theme-toggle" title="تبديل النمط" aria-label="تبديل النمط">
                <span class="theme-icon">🌙</span>
            </button>
            
            <button type="button" class="admin-action-btn" onclick="quickSync()" title="مزامنة سريعة">🔄</button>
            <button type="button" class="admin-action-btn" onclick="clearCache()" title="مسح الذاكرة المؤقتة">🗑️</button>
        </div>
        
        <div class="admin-profile">
            <button type="button" class="admin-profile-toggle" onclick="location.href='/admin/logout.php'">
                <?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'admin'); ?> 🚪
            </button>
        </div>
    </header>

    <!-- Admin Sidebar -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar-header">
            <div class="admin-logo">
                <span class="logo-icon">⚙️</span>
                <span class="logo-text">مركز الإدارة</span>
            </div>
        </div>
        
        <nav role="navigation" aria-label="القائمة الجانبية للإدارة">
            <ul class="admin-nav-list">
                <li class="nav-item">
                    <a href="<?php echo asset_url('admin/index.php'); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'page' : 'false'; ?>">
                        <span class="nav-icon">📊</span>
                        <span class="nav-label">لوحة التحكم</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo asset_url('admin/services.php'); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'services.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'services.php' ? 'page' : 'false'; ?>">
                        <span class="nav-icon">🛠️</span>
                        <span class="nav-label">الخدمات</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="<?php echo asset_url('admin/providers.php'); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'providers.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'providers.php' ? 'page' : 'false'; ?>">
                        <span class="nav-icon">🏢</span>
                        <span class="nav-label">المزودين</span>
                    </a>
                </li>
            
                <li class="nav-item">
                    <a href="<?php echo asset_url('admin/orders.php'); ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'page' : 'false'; ?>">
                        <span class="nav-icon">📦</span>
                        <span class="nav-label">الطلبات</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/wallet_approvals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'wallet_approvals.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">💰</span>
                        <span class="nav-label">المحافظ</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/rewards_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'rewards_management.php' ? 'active' : ''; ?>">
                        <span class="nav-icon">🏆</span>
                        <span class="nav-label">جوائز المتصدرين</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' && isset($_GET['logs']) === false ? 'active' : ''; ?>">
                        <span class="nav-icon">📈</span>
                        <span class="nav-label">التقارير والإحصائيات</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/admin/reports.php?logs=activity" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' && isset($_GET['logs']) && $_GET['logs'] === 'activity' ? 'active' : ''; ?>">
                        <span class="nav-icon">💼</span>
                        <span class="nav-label">سجل الأنشطة</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="admin-sidebar-overlay" id="sidebar-overlay"></div>

    <main class="admin-content" role="main">

<?php else: ?>
<div class="container-fluid">
<?php endif; ?>

    <!-- العنوان الرئيسي -->
    <div class="<?php echo $isAdmin ? 'page-header' : 'admin-welcome-header'; ?>">
        <div class="admin-user-info">
            <div class="admin-avatar"><?php echo mb_substr(htmlspecialchars($adminUser), 0, 1); ?></div>
            <div class="admin-greeting">
                <h1>مرحباً، <?php echo htmlspecialchars($adminUser); ?></h1>
                <p>مرحباً بك في مركز إدارة GameBox</p>
                <span class="admin-last-login">آخر تسجيل دخول: <?php echo date('Y-m-d H:i'); ?></span>
            </div>
        </div>
    </div>

    <!-- Live KPI Overview -->
    <div class="kpi-overview-section">
        <h2 style="text-align: center; margin-bottom: 2rem; font-weight: 700; background: linear-gradient(135deg, var(--primary-color), var(--color-accent)); background-clip: text; -webkit-background-clip: text; color: transparent; -webkit-text-fill-color: transparent;">
            📊 نظرة عامة حية
        </h2>
        
        <div class="kpi-cards-grid">
            <!-- Orders Today KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">📦</span>
                    <h3 class="kpi-title">طلبات اليوم</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo number_format($ordersToday); ?></span>
                    <span class="kpi-unit">طلب</span>
                </div>
                <div class="kpi-sparkline" id="orders-sparkline">
                    <svg viewBox="0 0 100 20" width="100" height="20">
                        <path d="" fill="none" stroke="var(--primary-color)" stroke-width="2"/>
                    </svg>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">من بداية اليوم</span>
                </div>
            </div>

            <!-- Revenue Today KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">💰</span>
                    <h3 class="kpi-title">إيرادات اليوم</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo number_format($revenueToday, 2, '.', ','); ?></span>
                    <span class="kpi-unit">LYD</span>
                </div>
                <div class="kpi-sparkline" id="revenue-sparkline">
                    <svg viewBox="0 0 100 20" width="100" height="20">
                        <path d="" fill="none" stroke="var(--color-accent)" stroke-width="2"/>
                    </svg>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">طلبات مدفوعة</span>
                </div>
            </div>

            <!-- Fulfillment Time KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">⚡</span>
                    <h3 class="kpi-title">متوسط التنفيذ</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo $avgFulfillmentTime > 60 ? number_format($avgFulfillmentTime / 60, 1) : number_format($avgFulfillmentTime); ?></span>
                    <span class="kpi-unit"><?php echo $avgFulfillmentTime > 60 ? 'ساعة' : 'دقيقة'; ?></span>
                </div>
                <div class="kpi-trend">
                    <span class="trend-indicator <?php echo $avgFulfillmentTime < 60 ? 'positive' : 'warning'; ?>">
                        <?php echo $avgFulfillmentTime < 60 ? '⚡ سريع' : '⏰ بطيء'; ?>
                    </span>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">آخر 7 أيام</span>
                </div>
            </div>

            <!-- Tickets Open KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">🎫</span>
                    <h3 class="kpi-title">تذاكر مفتوحة</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo number_format($ticketsOpen); ?></span>
                    <span class="kpi-unit">تذكرة</span>
                </div>
                <div class="kpi-trend">
                    <span class="trend-indicator <?php echo $ticketsOpen <= 5 ? 'positive' : ($ticketsOpen <= 15 ? 'warning' : 'negative'); ?>">
                        <?php echo $ticketsOpen <= 5 ? '✅ منخفض' : ($ticketsOpen <= 15 ? '⚠️ متوسط' : '🔥 مرتفع'); ?>
                    </span>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">آخر 30 يوم</span>
                </div>
            </div>

            <!-- Provider Health KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">🏥</span>
                    <h3 class="kpi-title">صحة المزودين</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo isset($providerHealth[0]) ? $providerHealth[0]['healthy_providers'] : 0; ?></span>
                    <span class="kpi-unit">/<?php echo isset($providerHealth[0]) ? $providerHealth[0]['total_providers'] : 0; ?></span>
                </div>
                <div class="kpi-trend">
                    <span class="trend-indicator <?php echo isset($providerHealth[0]) && $providerHealth[0]['healthy_providers'] >= $providerHealth[0]['total_providers'] * 0.8 ? 'positive' : 'warning'; ?>">
                        <?php echo isset($providerHealth[0]) && $providerHealth[0]['healthy_providers'] >= $providerHealth[0]['total_providers'] * 0.8 ? '🟢 نشط' : '🟡 تحقق'; ?>
                    </span>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">رصيد > 10$</span>
                </div>
            </div>

            <!-- System Performance KPI -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon">⚙️</span>
                    <h3 class="kpi-title">أداء النظام</h3>
                </div>
                <div class="kpi-metric">
                    <span class="kpi-number"><?php echo $ordersToday > 0 ? round(($ordersToday / max(50, $ordersToday)) * 100) : 0; ?></span>
                    <span class="kpi-unit">%</span>
                </div>
                <div class="kpi-trend">
                    <span class="trend-indicator <?php echo $ordersToday >= 20 ? 'positive' : ($ordersToday >= 10 ? 'warning' : 'negative'); ?>">
                        <?php echo $ordersToday >= 20 ? '🚀 ممتاز' : ($ordersToday >= 10 ? '📈 جيد' : '📉 منخفض'); ?>
                    </span>
                </div>
                <div class="kpi-footer">
                    <span class="kpi-label">معدل الطلبات</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقات الإدارة الرئيسية -->
    <div class="admin-main-card">
        
        <div class="admin-sections-grid">
            <div class="admin-section-card">
                <div class="section-header">
                    <div class="section-icon">⚙️</div>
                    <div class="section-title">
                        <h3>إدارة الخدمات</h3>
                        <p>مكافحة، ترجمة، وإعداد الخدمات</p>
                    </div>
                </div>
                <div class="section-body">
                    <a href="/admin/sync.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        مزامنة الخدمات من API
                    </a>
                    <a href="/admin/translations.php" class="btn btn-accent btn-block" style="margin-bottom: 1rem;">
                        ترجمة الخدمات
                    </a>
                    <a href="/admin/balance_check.php" class="btn btn-warning btn-block" style="margin-bottom: 1rem;">
                        فحص رصيد المزوّد
                    </a>
                    <a href="/admin/api_diagnostics.php" class="btn btn-info btn-block" style="margin-bottom: 1rem;">
                        تشخيص API
                    </a>
                    <a href="/admin/fix_columns.php" class="btn btn-secondary btn-block" style="margin-bottom: 1rem;">
                        إصلاح أعمدة المزودين
                    </a>
                    <a href="/admin/setup_providers.php" class="btn btn-secondary btn-block" style="margin-bottom: 1rem;">
                        إعداد المزودين المتعددين
                    </a>
                    <a href="/admin/sync_multi.php" class="btn btn-accent btn-block" style="margin-bottom: 1rem;">
                        مزامنة متعددة المزودين
                    </a>
                    <a href="/admin/providers.php" class="btn btn-warning btn-block" style="margin-bottom: 1rem;">
                        إدارة المزودين
                    </a>
                    <a href="/admin/setup_notifications.php" class="btn btn-secondary btn-block" style="margin-bottom: 1rem;">
                        إعداد الإشعارات
                    </a>
                    <a href="/admin/fix_orders_columns.php" class="btn btn-error btn-block" style="margin-bottom: 1rem;">
                        إصلاح أعمدة الطلبات
                    </a>
                    <a href="/admin/fix_notification_duplicates.php" class="btn btn-warning btn-block" style="margin-bottom: 1rem;">
                        إصلاح تكرار الإشعارات
                    </a>
                    <a href="/admin/setup_advanced_services.php" class="btn btn-accent btn-block" style="margin-bottom: 1rem;">
                        إعداد الخدمات المتقدم
                    </a>
                    <a href="/admin/dashboard.php" class="btn btn-primary btn-block" style="margin-bottom: 1rem;">
                        📊 لوحة التحكم الرئيسية
                    </a>
                    <a href="/admin/reports.php" class="btn btn-info btn-block" style="margin-bottom: 1rem;">
                        📈 التقارير والإحصائيات
                    </a>
                    <a href="/admin/notifications.php" class="btn btn-warning btn-block" style="margin-bottom: 1rem;">
                        🔔 إدارة الإشعارات
                    </a>
                    <a href="/catalog.php" class="btn btn-success btn-block" style="margin-bottom: 1rem;">
                        🛍️ عرض الخدمات (المحسنة)
                    </a>
                    <a href="/catalog_new.php" class="btn btn-secondary btn-block">
                        📋 النسخة القديمة
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">إدارة المحافظ</h3>
                </div>
                <div class="card-body">
                    <a href="/admin/wallet_approvals.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        طلبات شحن المحافظ
                    </a>
                    <a href="/wallet/" class="btn btn-primary btn-block">
                        عرض المحافظ
                    </a>
                </div>
            </div>
        </div>
        
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">إدارة الطلبات</h3>
                </div>
                <div class="card-body">
                    <a href="/track.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        تتبع الطلبات
                    </a>
                    <a href="/order.php" class="btn btn-primary btn-block">
                        إنشاء طلب جديد
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">إدارة المستخدمين</h3>
                </div>
                <div class="card-body">
                    <a href="/auth/login.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        تسجيل دخول المستخدمين
                    </a>
                    <a href="/account/" class="btn btn-primary btn-block">
                        حسابات المستخدمين
                    </a>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <a href="/admin/logout.php" class="btn" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                تسجيل الخروج من الإدارة
            </a>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
</main>
</div>

<!-- Inject Sparklines Data -->
<?php echo $sparklinesJS; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const body = document.body;
    const adminContent = document.querySelector('.admin-content');

    function openSidebar() {
        sidebar.classList.add('open');
        sidebarOverlay.classList.add('open');
        body.classList.add('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', 'true');
        
        // Lock body scroll on mobile
        body.style.overflow = 'hidden';
        
        // Send focus to sidebar for accessibility
        const firstLink = sidebar.querySelector('a');
        if (firstLink) {
            firstLink.focus();
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
        body.classList.remove('sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', 'false');
        
        // Restore body scroll
        body.style.overflow = '';
        
        // Return focus to toggle button
        sidebarToggle.focus();
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // Event listeners
    sidebarToggle.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // Search functionality
    const searchInput = document.getElementById('admin-search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    triggerSearch(query);
                }
            }
        });
    }

    // Lazy load sparklines when visible (below fold optimization)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                renderSparklines();
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '50px' });
    
    // Create skeleton loading placeholders first
    createSkeletonPlaceholders();
    
    // Observe sparklines container
    const sparklinesContainer = document.querySelector('.kpi-overview-section');
    if (sparklinesContainer) {
        observer.observe(sparklinesContainer);
    } else {
        renderSparklines(); // Fallback if IntersectionObserver not supported
    }
});

// Create skeleton loading placeholders
function createSkeletonPlaceholders() {
    const placeholderElements = [
        { id: 'orders-sparkline', title: 'Orders' },
        { id: 'revenue-sparkline', title: 'Revenue' }
    ];
    
    placeholderElements.forEach(el => {
        const container = document.getElementById(el.id);
        if (container) {
            container.innerHTML = `
                <div class="skeleton-card" style="height: 20px; border-radius: 4px;">
                    <div class="sr-only">Loading ${el.title} chart data...</div>
                </div>
            `;
        }
    });
}

// Sparklines rendering function with vanilla JavaScript
function renderSparklines() {
    if (typeof window.sparklinesData === 'undefined' || !window.sparklinesData) {
        replaceSkeletonWithMessage('Sparkline data temporarily unavailable', 'info');
        return;
    }

    const { orders, revenue, dates } = window.sparklinesData;
    
    if (orders.length < 2 || revenue.length < 2) {
        replaceSkeletonWithMessage('Insufficient data for charts', 'info');
        return;
    }

    try {
        // Render Orders Sparkline
        drawSparkline('orders-sparkline', orders, {
            color: 'var(--primary-color)',
            width: 100,
            height: 20
        });

        // Render Revenue Sparkline  
        drawSparkline('revenue-sparkline', revenue, {
            color: 'var(--color-accent)',
            width: 100,
            height: 20
        });

        // Announce success to screen readers
        announceToScreenReader('Charts loaded successfully');

    } catch (error) {
        replaceSkeletonWithMessage('Chart rendering error', 'error');
    }
}

// Replace skeleton with message
function replaceSkeletonWithMessage(msg, type = 'info') {
    const skeletonElements = document.querySelectorAll('.skeleton-card');
    skeletonElements.forEach(el => {
        el.className = `message-placeholder message-${type}`;
        el.innerHTML = `<small>${msg}</small>`;
    });
}

// Screen reader announcement
function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);
    
    setTimeout(() => announcement.remove(), 1000);
}

// Vanilla JavaScript sparkline generator
function drawSparkline(containerId, data, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) {
        return; // Silent return for better UX
    }

    const svg = container.querySelector('svg');
    if (!svg) {
    }

    const path = svg.querySelector('path');
    if (!path) {
        replaceSkeletonWithMessage('Chart element unavailable', 'error');
        return;
    }

    const width = options.width || 100;
    const height = options.height || 20;
    const color = options.color || 'var(--primary-color)';
    
    // Normalize data to fit SVG dimensions
    const minValue = Math.min(...data);
    const maxValue = Math.max(...data);
    const range = maxValue - minValue || 1; // Avoid division by zero
    
    // Generate SVG path
    let pathData = '';
    const pointCount = data.length;
    const stepX = width / Math.max(pointCount - 1, 1);
    
    for (let i = 0; i < pointCount; i++) {
        const x = i * stepX;
        const normalizedValue = range > 0 ? (data[i] - minValue) / range : 0.5;
        const y = height - (normalizedValue * height * 0.8) - (height * 0.1); // Padding 10% top/bottom
        
        if (i === 0) {
            pathData += `M ${x} ${y}`;
        } else {
            // Smooth curves for better visualization
            const prevX = (i - 1) * stepX;
            const prevNormalizedValue = range > 0 ? (data[i - 1] - minValue) / range : 0.5;
            const prevY = height - (prevNormalizedValue * height * 0.8) - (height * 0.1);
            
            const cp1x = prevX + (stepX * 0.5);
            const cp1y = prevY;
            const cp2x = x - (stepX * 0.5);
            const cp2y = y;
            
            pathData += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x} ${y}`;
        }
    }
    
    // Set path attributes
    path.setAttribute('d', pathData);
    path.setAttribute('stroke', color);
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    
    // Add animation
    const pathLength = path.getTotalLength();
    path.style.strokeDasharray = pathLength;
    path.style.strokeDashoffset = pathLength;
    
    // Animate sparkline on load
    setTimeout(() => {
        path.style.transition = 'stroke-dashoffset 1s ease-in-out';
        path.style.strokeDashoffset = '0';
    }, 100);
}

function triggerSearch(query = null) {
    const searchInput = document.getElementById('admin-search-input');
    const searchQuery = query || searchInput?.value?.trim();
    
    if (searchQuery) {
        // Redirect to search results or orders page with search
        window.location.href = `/admin/orders.php?search=${encodeURIComponent(searchQuery)}`;
    }
}

function quickSync() {
    if (confirm('هل تريد مزامنة الخدمات؟')) {
        window.location.href = '/admin/sync.php';
    }
}

function clearCache() {
    if (confirm('هل تريد مسح الذاكرة المؤقتة؟')) {
        // Could implement cache clearing here
        // Silent handling in production
    }
}

// Theme Toggle Functionality
function initializeThemeToggle() {
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = themeToggle?.querySelector('.theme-icon');
    
    if (!themeToggle || !themeIcon) return;
    
    // Get saved theme or default to dark
    const savedTheme = localStorage.getItem('admin-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Update icon based on current theme
    updateThemeIcon(savedTheme, themeIcon);
    
    // Theme toggle handler
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        // Apply new theme
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('admin-theme', newTheme);
        
        // Update icon
        updateThemeIcon(newTheme, themeIcon);
        
        // Announce theme change
        announceToScreenReader(`تم تغيير النمط إلى ${newTheme === 'light' ? 'الفاتح' : 'الداكن'}`);
    });
}

function updateThemeIcon(theme, iconElement) {
    if (theme === 'light') {
        iconElement.textContent = '🌙';
        iconElement.setAttribute('aria-label', 'التبديل إلى النمط الداكن');
    } else {
        iconElement.textContent = '☀️';
        iconElement.setAttribute('aria-label', 'التبديل إلى النمط الفاتح');
    }
}

// Auto close sidebar on mobile when clicking links
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && e.target.closest('.nav-link')) {
        const sidebar = document.getElementById('admin-sidebar');
        if (sidebar?.classList.contains('open')) {
            sidebar.classList.remove('open');
            document.getElementById('sidebar-overlay').classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    }
});

// Defer non-critical scripts for performance
setTimeout(() => {
    // Load non-critical JavaScript after page is interactive
    loadDeferredScripts();
}, 100);

function loadDeferredScripts() {
    // Initialize sidebar functionality
    initializeSidebar();
    
    // Initialize admin search
    initializeAdminSearch();
    
    // Initialize quick actions
    initializeQuickActions();
    
    // Initialize theme toggle
    initializeThemeToggle();
}

function initializeSidebar() {
    // Enhanced sidebar with accessibility
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (!sidebar || !overlay) return;
    
    sidebar.setAttribute('aria-hidden', 'false');
    sidebar.setAttribute('aria-labelledby', 'sidebar-title');
    
    // Focus management
    const focusableElements = sidebar.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    
    sidebar.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSidebar();
        } else if (e.key === 'Tab') {
            if (e.shiftKey && e.target === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && e.target === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    });
    
    // Announce sidebar state to screen readers
    announceSidebarState();
}

function initializeAdminSearch() {
    const searchInput = document.querySelector('.admin-search');
    if (!searchInput) return;
    
    searchInput.setAttribute('role', 'searchbox');
    searchInput.setAttribute('aria-label', 'البحث في لوحة الإدارة');
    searchInput.setAttribute('aria-describedby', 'search-help');
    
    // Add search help text
    const helpText = document.createElement('div');
    helpText.id = 'search-help';
    helpText.className = 'sr-only';
    helpText.textContent = 'ابحث في الخدمات والطلبات والمستخدمين';
    searchInput.parentNode.appendChild(helpText);
}

function initializeQuickActions() {
    const quickActionButtons = document.querySelectorAll('.quick-action');
    
    quickActionButtons.forEach(btn => {
        btn.addEventListener('click', handleQuickAction);
        btn.setAttribute('aria-label', btn.textContent.trim());
    });
}

function announceSidebarState() {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = 'Sidebar navigation available';
    document.body.appendChild(announcement);
    
    setTimeout(() => announcement.remove(), 1000);
}

// Performance: Add skeleton loaders for heavy content
window.addEventListener('load', () => {
    // Remove any remaining skeleton loaders
    const skeletonElements = document.querySelectorAll('.skeleton');
    skeletonElements.forEach(el => {
        el.classList.remove('skeleton');
        el.classList.add('loaded');
    });
    });
    
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

<!-- Defer main JavaScript bundle for better performance -->
<script>
// Load main JS bundle after critical page load
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
    script.onload = () => {
        // JavaScript bundle loaded successfully
        announceToScreenReader('Admin interface fully loaded');
    };
    document.head.appendChild(script);
}
</script>

<!-- Screen Reader Styles -->
<style>
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

/* Focus styles for accessibility */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Enhanced contrast for accessibility */
@media (prefers-contrast: high) {
    .btn {
        border-width: 2px;
    }
    
    .admin-topbar {
        border-bottom-width: 2px;
    }
    
    .kpi-card {
        border: 2px solid var(--border-color);
    }
    
    .trend-indicator {
        border-width: 2px;
    }
}

/* Reduced motion for accessibility */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* Message placeholders */
.message-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    font-style: italic;
}

.message-info {
    color: var(--text-secondary);
}

.message-error {
    color: #dc3545;
}

/* Performance: Preload hover states */
.admin-topbar:hover,
.kpi-card:hover,
.btn:hover {
    will-change: transform;
}

.admin-topbar:not(:hover),
.kpi-card:not(:hover),
.btn:not(:hover) {
    will-change: auto;
}
</style>

<?php endif; ?>

<style>
/* تحسينات شاملة لواجهة الإدارة */
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.admin-welcome-header {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
    backdrop-filter: blur(20px);
    background: linear-gradient(135deg, var(--card-bg) 0%, rgba(201, 162, 39, 0.05) 100%);
}

.admin-user-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.admin-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--color-accent));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    box-shadow: 0 8px 25px rgba(26, 60, 140, 0.3);
}

.admin-greeting h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2.25rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary-color), var(--color-accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.admin-greeting p {
    margin: 0 0 0.5rem 0;
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.admin-last-login {
    font-size: 0.9rem;
    color: var(--text-tertiary);
    background: var(--color-elev);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
}

.admin-stats-summary {
    display: flex;
    gap: 2rem;
}

.quick-stat {
    text-align: center;
    padding: 1.5rem;
    background: rgba(201, 162, 39, 0.1);
    border-radius: 16px;
    border: 1px solid rgba(201, 162, 39, 0.2);
    min-width: 120px;
}

.quick-stat .stat-number {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.quick-stat .stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.admin-main-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
}

.admin-sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}

.admin-section-card {
    background: var(--card-bg);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-section-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border-color: var(--accent-color);
}

.section-header {
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-icon {
    font-size: 2.5rem;
    opacity: 0.9;
}

.section-title h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.section-title p {
    margin: 0;
    opacity: 0.8;
    font-size: 0.95rem;
}

.section-body {
    padding: 1.5rem;
}

.section-body .btn {
    margin-bottom: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.section-body .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.section-body .btn:last-child {
    margin-bottom: 0;
}

/* تحسينات للأزرار في الإدارة */
.btn.btn-block {
    justify-content: center;
}

.btn.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn.btn-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    border: none;
    color: #000;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

.btn.btn-info {
    background: linear-gradient(135deg, #17a2b8, #20c997);
    border: none;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
}

.btn.btn-error {
    background: linear-gradient(135deg, #dc3545, #e74c3c);
    border: none;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.btn.btn-accent {
    background: linear-gradient(135deg, var(--color-accent), var(--color-gold));
    border-radius: 12px;
    color: #000;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(201, 162, 39, 0.3);
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 15px;
    }

    .admin-welcome-header {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
        padding: 2rem;
    }

    .admin-user-info {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .admin-avatar {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }

    .admin-greeting h1 {
        font-size: 1.75rem;
    }

    .admin-stats-summary {
        gap: 1rem;
        justify-content: center;
    }

    .quick-stat {
        min-width: 100px;
        padding: 1rem;
    }

    .admin-main-card {
        padding: 1.5rem;
    }

    .admin-sections-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .section-header {
        padding: 1.25rem;
        gap: 0.75rem;
    }

    .section-icon {
        font-size: 2rem;
    }

    .section-title h3 {
        font-size: 1.2rem;
    }

    .section-body {
        padding: 1.25rem;
    }

    .section-body .btn {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}

/* شاشات صغيرة جداً (360px-430px) */
@media (max-width: 430px) {
    .container-fluid {
        padding: 0 10px;
    }

    .admin-welcome-header {
        padding: 1.5rem;
    }

    .admin-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }

    .admin-greeting h1 {
        font-size: 1.5rem;
    }

    .admin-stats-summary {
        flex-direction: column;
        gap: 0.75rem;
    }

    .quick-stat {
        min-width: unset;
        width: 100%;
        padding: 1rem;
    }

    .admin-main-card {
        padding: 1rem;
    }

    .admin-sections-grid {
        gap: 1rem;
    }

    .section-header {
        padding: 1rem;
        flex-direction: column;
        text-align: center;
    }

    .section-title h3 {
        font-size: 1.1rem;
    }

    .section-body {
        padding: 1rem;
    }

    .section-body .btn {
        padding: 0.75rem;
        font-size: 0.85rem;
    }
}

/* تحسينات إضافية للشاشات شديدة الصغيرة */
@media (max-width: 360px) {
    .admin-greeting h1 {
        font-size: 1.25rem;
    }

    .section-body .btn {
        padding: 0.625rem;
        font-size: 0.8rem;
    }
}

/* تحسينات للرسوم المتحركة */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.admin-section-card {
    animation: fadeInUp 0.6s ease-out;
}

.admin-section-card:nth-child(2) {
    animation-delay: 0.1s;
}

.admin-section-card:nth-child(3) {
    animation-delay: 0.2s;
}

.admin-section-card:nth-child(4) {
    animation-delay: 0.3s;
}

/* KPI Cards Styles */
.kpi-overview-section {
    margin-bottom: 3rem;
}

.kpi-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.kpi-card:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0.0, 0.15);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    inset-inline-start: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--color-accent));
}

.kpi-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.kpi-icon {
    font-size: 1.5rem;
    opacity: 0.8;
}

.kpi-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text);
    margin: 0;
}

.kpi-metric {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.kpi-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
    background: linear-gradient(135deg, var(--primary-color), var(--color-accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.kpi-unit {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.kpi-sparkline {
    height: 20px;
    margin-bottom: 0.75rem;
    opacity: 0.8;
}

.kpi-sparkline svg {
    width: 100%;
    height: 100%;
}

.kpi-trend {
    margin-bottom: 0.75rem;
}

.trend-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.trend-indicator.positive {
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.trend-indicator.warning {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.trend-indicator.negative {
    background: rgba(220, 53, 69, 0.15);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.kpi-footer {
    font-size: 0.85rem;
    color: var(--text-tertiary);
    font-weight: 500;
}

/* تحسينات إضافية للألوان */
:root {
    --color-accent: #C9A227;
    --text-tertiary: rgba(237, 239, 244, 0.6);
    --color-green: #28a745;
}

[data-theme="light"] {
    --color-gold: #D4B73A;
    --text-tertiary: rgba(26, 34, 52, 0.6);
}

/* Mobile Responsive for KPIs */
@media (max-width: 768px) {
    .kpi-cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .kpi-card {
        padding: 1.25rem;
    }
    
    .kpi-number {
        font-size: 2rem;
    }
}

@media (max-width: 430px) {
    .kpi-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .kpi-card {
        padding: 1rem;
    }
    
    .kpi-number {
        font-size: 1.75rem;
    }
    
    .kpi-title {
        font-size: 0.9rem;
    }
}
</style>

<?php if (!$isAdmin): ?>
<?php include '../templates/partials/footer.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>
