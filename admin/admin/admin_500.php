<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';

Auth::startSession();

// Check if admin session exists
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطأ في الخادم - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
</head>
<body class="admin-body">
<style>
/* Admin 500 Error Page Styles */
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

.page-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: linear-gradient(135deg, var(--ad-bg) 0%, #2d1b1b 100%);
}

.error-card {
    background: var(--ad-card);
    border: 1px solid var(--ad-border);
    border-radius: var(--ad-radius);
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--ad-shadow);
    max-width: 500px;
    width: 100%;
}

.error-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.error-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--ad-text);
    margin-bottom: 1rem;
}

.error-description {
    color: var(--ad-muted);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.btn {
    background: linear-gradient(180deg, #D6B544, var(--ad-gold));
    color: #0E0F12;
    border: 1px solid #B38F1F;
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn:hover {
    background: linear-gradient(180deg, #e6c455, #dbb650);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(216, 182, 68, 0.3);
}

.btn--outline {
    background: transparent;
    color: var(--ad-text);
    border: 1px solid var(--ad-border);
}

.btn--outline:hover {
    background: var(--ad-elev);
    color: var(--ad-text);
    transform: translateY(-2px);
}

.btn--danger {
    background: linear-gradient(180deg, #ef4444, #dc2626);
    color: #ffffff;
    border: 1px solid #b91c1c;
}

.btn--danger:hover {
    background: linear-gradient(180deg, #f87171, #ef4444);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.dev-info {
    background: rgba(220, 38, 38, 0.1);
    border: 1px solid rgba(220, 38, 38, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
    text-align: left;
}

.dev-info h4 {
    color: #fca5a5;
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
}

.dev-info p {
    color: var(--ad-muted);
    font-size: 0.8rem;
    margin: 0;
    font-family: monospace;
}

@media (max-width: 430px) {
    .page-container {
        padding: 1rem;
    }
    
    .error-card {
        padding: 2rem 1rem;
    }
    
    .error-title {
        font-size: 2rem;
    }
    
    .error-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>

<div class="page-container">
    <div class="error-card">
        <div class="error-icon">⚡</div>
        <h1 class="error-title">500</h1>
        
        <?php if ($isAdmin): ?>
            <p class="error-description">
                عذراً، حدث خطأ داخلي في الخادم.<br>
                تم تسجيل المشكلة ومتابعتها من قبل فريق التقنية.
            </p>
            <div class="error-actions">
                <a href="/admin/" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"/>
                    </svg>
                    لوحة التحكم
                </a>
                <button onclick="location.reload()" class="btn btn--danger">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10 0 0,1 22,12H20A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A10,10 0 0,1 12,2M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/>
                    </svg>
                    إعادة المحاولة
                </button>
                <a href="/admin/reports.php?logs=activity" class="btn btn--outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    سجل الأخطاء
                </a>
            </div>
            
            <div class="dev-info">
                <h4>🔧 معلومات التطوير</h4>
                <p>الوقت: <?php echo date('Y-m-d H:i:s'); ?><br>
                المسار: <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/unknown'); ?><br>
                العميل: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'غير محدد'); ?></p>
            </div>
        <?php else: ?>
            <p class="error-description">
                عذراً، حدث خطأ في الخادم.<br>
                يرجى المحاولة مرة أخرى أو العودة إلى الصفحة الرئيسية.
            </p>
            <div class="error-actions">
                <a href="/" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"/>
                    </svg>
                    الصفحة الرئيسية
                </a>
                <button onclick="location.reload()" class="btn btn--danger">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10 0 0,1 22,12H20A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A10,10 0 0,1 12,2M12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8Z"/>
                    </svg>
                    إعادة المحاولة
                </button>
            </div>
        <?php endif; ?>
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

// Log error details to console for debugging (only if it's an admin session)
<?php if ($isAdmin): ?>
// Silent error handling
<?php endif; ?>

// Auto-retry mechanism
let retryCount = 0;
const maxRetries = 3;

function autoRetry() {
    if (retryCount < maxRetries) {
        retryCount++;
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
}

// If no user interaction after 30 seconds, auto-retry
setTimeout(autoRetry, 30000);
</script>

</body>
</html>

