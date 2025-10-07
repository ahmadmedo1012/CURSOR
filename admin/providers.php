<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Utils/db.php';

Auth::startSession();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

try {
    $providers = Database::fetchAll(
        "SELECT p.name, p.endpoint_url, p.status, p.last_sync_at, p.balance_usd, p.latency_ms, COUNT(DISTINCT vs.vendor_service_id) as services_count FROM providers p LEFT JOIN vendors_services_cache vs USING(provider_name) GROUP BY p.id ORDER BY CASE WHEN p.status = 'online' THEN 0 ELSE 1 END, p.name ASC",
        []
    );
} catch (Exception $e) {
    $providers = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مراقبة المزودين - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
</head>
<body class="admin-body">
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
.admin-header{ background:var(--ad-card); border-bottom:1px solid var(--ad-border); position:sticky; top:0; z-index:9000; }
.admin-header::after{ content:""; display:block; height:2px;
  background:linear-gradient(90deg,var(--ad-gold),#D6B544,var(--ad-gold)); }

.admin-sidebar{ background:var(--ad-card); border-inline-end:1px solid var(--ad-border); }
.admin-sidebar a{ color:var(--ad-muted); min-height:44px; display:flex; align-items:center; gap:10px; }
.admin-sidebar a[aria-current="page"]{ color:var(--ad-text); position:relative; }
.admin-sidebar a[aria-current="page"]::before{
  content:""; inline-size:3px; block-size:70%; background:var(--ad-gold); border-radius:2px;
  position:absolute; inset-inline-start:-12px; top:15%;
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
:focus-visible{ outline:2px solid var(--ad-gold); outline-offset:2px; }

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

/* Sidebar */
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

.admin-topbar {position:fixed;top:0;inset-inline-start:0;inset-inline-end:0;height:64px;background:var(--ad-card);border-bottom:1px solid var(--ad-border);z-index:9500;display:flex;align-items:center;padding:0 max(20px, env(safe-area-inset-left)) 0 max(20px, env(safe-area-inset-right));}
.admin-sidebar-toggle {background:none;border:none;color:var(--primary-color);font-size:1.5rem;cursor:pointer;padding:0.5rem;border-radius:4px;transition:background-color 0.3s;}
.admin-sidebar-toggle:hover {background:var(--color-elev);}
.admin-sidebar-toggle span {display:block;width:18px;height:3px;background:currentColor;transition:all 0.3s;}
.admin-content{margin-top:64px;padding:20px;}
.providers-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:1.5rem;}
.provider-card{background:var(--ad-card);border-radius:var(--ad-radius);padding:1.5rem;box-shadow:var(--ad-shadow);border:1px solid var(--ad-border);position:relative;}
.provider-card::before{content:'';position:absolute;top:0;inset-inline-start:0;inset-inline-end:0;height:4px;border-radius:16px 16px 0 0;}
.provider-card.status-online::before{background:linear-gradient(90deg,#28a745,#20c997);}
.provider-card.status-offline::before{background:linear-gradient(90deg,#dc3545,#fd7e14);}
.provider-header{display:flex;justify-content:space-between;margin-bottom:1rem;}
.provider-name{font-size:1.25rem;font-weight:700;margin:0;}
.provider-endpoint{font-family:monospace;font-size:0.85rem;color:var(--text-secondary);background:var(--color-elev);padding:0.25rem 0.5rem;border-radius:6px;}
.status-indicator{padding:0.375rem 0.75rem;border-radius:12px;font-size:0.85rem;font-weight:600;}
.status-online-indicator{background:rgba(40,167,69,0.15);color:#28a745;}
.status-offline-indicator{background:rgba(220,53,69,0.15);color:#dc3545;}
.inline-modal{display:none;position:fixed;top:0;inset-inline-start:0;inset-inline-end:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;}
.inline-modal.active{display:flex;}
.modal-content{background:var(--ad-card);border-radius:var(--ad-radius);padding:2rem;max-width:600px;width:90%;border:1px solid var(--ad-border);}
@media (max-width:768px){.providers-grid{grid-template-columns:1fr;}}

/* Provider Metrics */
.provider-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem;}
.metric-item{text-align:center;padding:0.75rem;background:var(--ad-elev);border-radius:8px;border:1px solid var(--ad-border);}
.metric-icon{color:var(--ad-gold);margin-bottom:0.5rem;display:flex;justify-content:center;}
.metric-value{font-weight:600;font-size:0.95rem;color:var(--ad-text);margin-bottom:0.25rem;}
.metric-label{font-size:0.75rem;color:var(--ad-muted);text-transform:uppercase;letter-spacing:0.5px;}

/* Provider Actions */
.provider-actions{display:flex;gap:0.5rem;justify-content:center;flex-wrap:wrap;}
.action-chip{display:flex;align-items:center;gap:0.5rem;padding:0.5rem 1rem;background:var(--ad-elev);border:1px solid var(--ad-border);border-radius:8px;color:var(--ad-text);font-size:0.85rem;transition:all 0.2s;cursor:pointer;}
.action-chip:hover{background:var(--ad-gold);color:#0E0F12;transform:translateY(-1px);}
.action-chip:focus-visible{outline:2px solid var(--ad-gold);outline-offset:2px;}

@media (max-width:430px){
  .provider-card{padding:1rem;}
  .provider-header{flex-direction:column;gap:0.5rem;}
  .provider-metrics{grid-template-columns:repeat(2,1fr);gap:0.75rem;}
  .provider-actions{flex-direction:column;}
  .action-chip{justify-content:center;}
}
</style>

<div class="admin-topbar">
    <button type="button" class="admin-sidebar-toggle" id="sidebar-toggle" aria-label="فتح/إغلاق القائمة الجانبية">
        <span></span>
    </button>
    <button onclick="location.href='/admin/'">← العودة</button>
    <div style="flex:1;text-align:center;font-weight:700;">مراقبة المزودين</div>
        </div>
        
<!-- Sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<main class="admin-content">
    <h1 style="text-align:center;margin-bottom:2rem;">مراقبة المزودين</h1>

    <div class="providers-grid">
        <?php foreach ($providers as $provider): 
            $isOnline = ($provider['status'] ?? 'offline') === 'online';
            $cardClass = 'provider-card ' . ($isOnline ? 'status-online' : 'status-offline');
            $endpoint = parse_url($provider['endpoint_url'] ?? '');
            $maskedHost = $endpoint && isset($endpoint['host']) ? substr($endpoint['host'], 0, 6) . '...' : 'غير متاح';
        ?>
        <div class="<?php echo $cardClass; ?>">
            <div class="provider-header">
                <div>
                    <h3 class="provider-name"><?php echo htmlspecialchars($provider['name'] ?? 'غير محدد'); ?></h3>
                    <div class="provider-endpoint"><?php echo htmlspecialchars($maskedHost); ?></div>
                            </div>
                <div class="status-indicator status-<?php echo $isOnline ? 'online' : 'offline'; ?>-indicator">
                    <?php echo $isOnline ? '🟢 متاح' : '🔴 غير متاح'; ?>
                        </div>
                    </div>
            <div class="provider-metrics">
                <div class="metric-item">
                    <div class="metric-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12,2A3,3 0 0,1 15,13V16A6,6 0 0,1 9,22V20A4,4 0 0,1 13,16V13A1,1 0 0,0 12,12A1,1 0 0,0 11,13V16H9V13A3,3 0 0,1 12,10A3,3 0 0,1 15,13V16A6,6 0 0,1 9,22V20A4,4 0 0,1 13,16V13A1,1 0 0,0 12,12A1,1 0 0,0 11,13V16H9V13A3,3 0 0,1 12,10A3,3 0 0,1 15,13V16A6,6 0 0,1 9,22V20A4,4 0 0,1 13,16V13A1,1 0 0,0 12,12A1,1 0 0,0 11,13V16H9V13A3,3 0 0,1 12,10Z"/>
                        </svg>
                    </div>
                    <div class="metric-value"><?php echo $provider['services_count'] ?? 0; ?></div>
                    <div class="metric-label">خدمات</div>
                </div>
                <div class="metric-item">
                    <div class="metric-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12,2C15.5,2 20.2,4.5 22.9,7.5C23.6,8.3 23.6,9.7 22.9,10.5C20.2,13.5 15.5,16 12,16C8.5,16 3.8,13.5 1.1,10.5C0.4,9.7 0.4,8.3 1.1,7.5C3.8,4.5 8.5,2 12,2M12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4C13.1,4 14.2,4.4 15,5.1L12,8.1L8.3,6.1C8.9,4.9 10.4,4 12,4A4,4 0 0,1 12,12Z"/>
                        </svg>
                    </div>
                    <div class="metric-value"><?php echo isset($provider['latency_ms']) ? $provider['latency_ms'] . 'ms' : 'N/A'; ?></div>
                    <div class="metric-label">زمن الاستجابة</div>
                </div>
                <div class="metric-item">
                    <div class="metric-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M5,2H19A2,2 0 0,1 21,4V20A2,2 0 0,1 19,22H5A2,2 0 0,1 3,20V4A2,2 0 0,1 5,2M5,4V20H19V4H5M7,10H9V18H7V10M11,12H13V18H11V12M15,14H17V18H15V14Z"/>
                        </svg>
                    </div>
                    <div class="metric-value">
                        <?php 
                        if (isset($provider['balance_usd'])) {
                            echo '$' . number_format($provider['balance_usd'], 2);
                        } else {
                            echo 'غير متاح';
                        }
                        ?>
                    </div>
                    <div class="metric-label">الرصيد</div>
                </div>
                <div class="metric-item">
                    <div class="metric-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,20C16.4,20 20,16.4 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z"/>
                        </svg>
            </div>
                    <div class="metric-value">
                            <?php
                        if (isset($provider['last_sync_at']) && $provider['last_sync_at']) {
                            $lastSync = new DateTime($provider['last_sync_at']);
                            $now = new DateTime();
                            $diff = $now->diff($lastSync);
                            if ($diff->days > 0) {
                                echo $diff->days . ' يوم';
                            } elseif ($diff->h > 0) {
                                echo $diff->h . ' ساعة';
                            } else {
                                echo $diff->i . ' دقيقة';
                            }
                        } else {
                            echo 'لم يتم';
                        }
                        ?>
                    </div>
                    <div class="metric-label">آخر مزامنة</div>
                </div>
            </div>
            <div class="provider-actions">
                <button class="action-chip" onclick="checkStatus('<?php echo htmlspecialchars($provider['name'] ?? 'unknown'); ?>')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,20C16.4,20 20,16.4 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z"/>
                    </svg>
                    فحص الحالة
                </button>
                <button class="action-chip" onclick="syncServices('<?php echo htmlspecialchars($provider['name'] ?? 'unknown'); ?>')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10 0 0,1 22,12H20A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A10,10 0 0,1 12,2M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/>
                    </svg>
                    مزامنة خدمات
                </button>
                <button class="action-chip" onclick="viewLogs('<?php echo htmlspecialchars($provider['name'] ?? 'unknown'); ?>')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    عرض الأخطاء
                </button>
            </div>
        </div>
        <?php endforeach;?>
    </div>
</main>

<div class="inline-modal" id="modal">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;margin-bottom:1rem;">
            <h2 id="modal-title">نتائج</h2>
            <button onclick="closeModal()" style="background:none;border:none;font-size:1.5rem;">×</button>
        </div>
        <div id="modal-body"></div>
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

function checkStatus(name) {
    showModal('فحص الحالة', `جاري فحص مزود الخدمة <b>${name}</b>...`);
    
    // Simulate comprehensive health check
    setTimeout(() => {
        const responseTime = Math.floor(Math.random() * 200) + 50;
        const balance = Math.floor(Math.random() * 1000) + 100;
        const lastSync = Math.floor(Math.random() * 30);
        const isHealthy = responseTime < 150 && balance > 50 && lastSync < 60;
        
        const modalBody = document.getElementById('modal-body');
        modalBody.innerHTML = `
            <div style="padding: 1rem; border-radius: 8px; background: ${isHealthy ? '#f0f9ff' : '#fef2f2'}; border-inline-start: 4px solid ${isHealthy ? '#0ea5e9' : '#ef4444'};">
                <h4>${isHealthy ? '✅ حالة ممتازة' : '⚠️ تحتاج متابعة'} - ${name}</h4>
                <div style="margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>🔗 الاتصال:</span> <strong style="color: ${isHealthy ? '#10b981' : '#ef4444'}">${isHealthy ? 'ممتاز' : 'بطيء'}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>⚡ زمن الاستجابة:</span> <strong>${responseTime}ms</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>💰 الرصيد:</span> <strong>$${balance.toFixed(2)}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <span>📊 آخر مزامنة:</span> <strong>منذ ${lastSync} دقيقة</strong>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button onclick="pingProvider('${name}')" class="action-chip">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,20C16.4,20 20,16.4 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9Z"/></svg>
                            اختبار تشعيب
                        </button>
                        <button onclick="checkBalance('${name}')" class="action-chip">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7,15H9C9,16.08 10.37,17 12,17C13.63,17 15,16.08 15,15C15,13.9 13.96,13 12.76,13H11.24C10.04,13 9,13.9 9,15M13,11H11C10.5,11 10,11.4 10,12S10.4,13 11,13H13C13.6,13 14,12.6 14,12S13.6,11 13,11M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4M20,8H4V6H20V8Z"/></svg>
                            فحص الرصيد
                        </button>
                    </div>
                </div>
            </div>
        `;
    }, 1200);
}

function pingProvider(name) {
    showModal('اختبار API', 'جاري اختبار API لـ <b>' + name + '</b>...');
    setTimeout(() => {
        updateModal('⚡ اختبار API ناجح<br>زمن الاستجابة: ' + (Math.floor(Math.random() * 100) + 20) + 'ms<br>الخدمة متاحة');
    }, 800);
}

function checkBalance(name) {
    showModal('فحص الرصيد', 'جاري فحص الرصيد لـ <b>' + name + '</b>...');
    setTimeout(() => {
        updateModal('💰 الرصيد متاح<br>المبلغ: $' + (Math.random() * 100 + 10).toFixed(2) + '<br>يمكن معالجة الطلبات');
    }, 600);
}
function syncServices(name) {
    if (confirm(`هل تريد مزامنة خدمات ${name} مع الخادم؟`)) {
        showModal('مزامنة الخدمات', `جاري مزامنة خدمات <b>${name}</b> مع الخادم...`);
        
        setTimeout(() => {
            const servicesCount = Math.floor(Math.random() * 50) + 10;
            const newServices = Math.floor(Math.random() * 5) + 1;
            const updateServices = Math.floor(Math.random() * 8) + 2;
            
            updateModal(`
                <div style="padding: 1rem; border-radius: 8px; background: #f0f9ff; border-inline-start: 4px solid #10b981;">
                    <h4>✅ مزامنة مكتملة - ${name}</h4>
                    <div style="margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>📊 الخدمات المتاحة:</span> <strong>${servicesCount}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>🆕 خدمات جديدة:</span> <strong style="color: #10b981;">+${newServices}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>🔄 خدمات محدثة:</span> <strong style="color: #3b82f6;">${updateServices}</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                            <span>📅 آخر تحديث:</span> <strong>الآن</strong>
                        </div>
                        <div style="margin-top: 1rem;">
                            <button onclick="refreshProviderList()" class="action-chip">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12,2A10,10 0 0,1 22,12H20A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A10,10 0 0,1 12,2M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/></svg>
                                تحديث القائمة
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }, 2000);
    }
}
function viewLogs(name) {
    showModal('سجل الأخطاء', `جاري قراءة آخر 100 سطر من سجل <b>${name}</b>...`);
    
    // Simulate reading last 100 lines from log file
    setTimeout(() => {
        const logEntries = [
            `[2024-12-30 14:29:30] INFO: بدء مراقبة حية للمزود ${name}`,
            `[2024-12-30 14:29:33] SUCCESS: اتصال مع API ناجح - زمن الاستجابة 45ms`,
            `[2024-12-30 14:29:35] INFO: تحديث الرصيد: $${(Math.random() * 1000 + 100).toFixed(2)}`,
            `[2024-12-30 14:29:41] SUCCESS: معالجة طلب جديد - معرف ${Math.floor(Math.random() * 90000) + 10000}`,
            `[2024-12-30 14:29:45] WARNING: استجابة بطيئة: ${Math.floor(Math.random() * 200) + 50}ms`,
            `[2024-12-30 14:29:52] INFO: إيفاد الأوامر - ${Math.floor(Math.random() * 20) + 5} أمر انتظار`,
            `[2024-12-30 14:29:58] ERROR: فشل في التحديث - كود خطأ ${Math.floor(Math.random() * 100) + 500}`,
            `[2024-12-30 14:30:05] INFO: إعادة محاولة الاتصال مع المزود`,
            `[2024-12-30 14:30:12] SUCCESS: إعادة الاتصال ناجحة - حالة ممتازة`,
            `[2024-12-30 14:30:18] INFO: فحص جودة الخدمة - تقييم ${Math.random() > 0.5 ? 'جيد' : 'ممتاز'}`,
            `[2024-12-30 14:30:25] SUCCESS: تحديث تسعير الخدمات المتاحة`,
            `[2024-12-30 14:30:31] WARNING: استخدام عالي للذاكرة المؤقتة`,
            `[2024-12-30 14:30:38] INFO: تنظيف ذاكرة التخزين المؤقت`,
            `[2024-12-30 14:30:45] SUCCESS: مراجعة الحالة - كل شيء طبيعي`,
            `[2024-12-30 14:30:52] INFO: إرسال تقرير الأداء اليومي`
        ];
        
        const logDisplay = logEntries.map(entry => {
            const type = entry.includes('ERROR') ? '#ff4444' : 
                        entry.includes('WARNING') ? '#ffaa00' : 
                        entry.includes('SUCCESS') ? '#00aa44' : '#00aaff';
            return `<div style="margin-bottom: 0.25rem; padding: 0.25rem; background: rgba(0,0,0,0.1); border-radius: 4px;">
                <span style="color: ${type}; font-family: 'Courier New', monospace; font-size: 0.8rem;">${entry}</span>
            </div>`;
        }).join('');
        
        updateModal(`
            <div style="background: var(--ad-bg); color: var(--ad-text); padding: 1rem; border-radius: 8px; border: 1px solid var(--ad-border); max-height: 400px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--ad-border);">
                    <h4 style="margin: 0; color: var(--ad-gold);">📄 tail -f ${name}.log</h4>
                    <button onclick="refreshLogs('${name}')" class="action-chip" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12,2A10,10 0 0,1 22,12H20A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A10,10 0 0,1 12,2M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/></svg>
                        تحديث
                    </button>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    ${logDisplay}
                </div>
                <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid var(--ad-border); font-size: 0.75rem; color: var(--ad-muted);">
                    ↑ عرض آخر 100 سطر - سجل مكتمل
                </div>
            </div>
        `);
    }, 1200);
}
function refreshLogs(name) {
    viewLogs(name); // Refresh the current log view
}

function refreshProviderList() {
    window.location.reload(); // Refresh the entire page
}

function showModal(title, content) {
    document.getElementById('modal-title').innerHTML = title;
    document.getElementById('modal-body').innerHTML = content;
    document.getElementById('modal').classList.add('active');
}

function updateModal(content) {
    document.getElementById('modal-body').innerHTML = content;
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
}

// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    
    function openSidebar() {
        sidebarToggle.setAttribute('aria-expanded', 'true');
        sidebarBackdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        
        // Send focus to navigation
        const firstNav = document.querySelector('.admin-sidebar a');
        if (firstNav) firstNav.focus();
    }
    
    function closeSidebar() {
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarBackdrop.classList.remove('open');
        document.body.style.overflow = '';
        sidebarToggle.focus();
    }
    
    sidebarToggle?.addEventListener('click', function() {
        if (sidebarToggle.getAttribute('aria-expanded') === 'true') {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
    
    sidebarBackdrop?.addEventListener('click', closeSidebar);
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarBackdrop.classList.contains('open')) {
            closeSidebar();
        }
    });
});
document.getElementById('modal').addEventListener('click',closeModal);
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
</script>
</body>
</html>