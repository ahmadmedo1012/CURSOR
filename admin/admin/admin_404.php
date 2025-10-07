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
    <title>صفحة غير موجودة - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
</head>
<body class="admin-body">
<style>
/* Admin 404 Error Page Styles */
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
    background: linear-gradient(135deg, var(--ad-bg) 0%, #1a2332 100%);
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
    background: linear-gradient(135deg, var(--ad-gold), #d6b544);
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

@media (max-width: 430px) {
    .page-container {
        padding: 1rem;
    }
    
    .error-card {
        padding: 2rem 1rem;
    }
    
现在我    .error-title {
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
        <div class="error-icon">🔍</div>
        <h1 class="error-title">404</h1>
        
        <?php if ($isAdmin): ?>
            <p class="error-description">
                عذراً، الصفحة المطلوبة غير موجودة في لوحة الإدارة.<br>
                يرجى التحقق من الرابط أو العودة إلى الصفحة الرئيسية.
            </p>
            <div class="error-actions">
                <a href="/admin/" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"/>
                    </svg>
                    لوحة التحكم
                </a>
                <a href="/admin/services.php" class="btn btn--outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                    </svg>
                    الخدمات
                </a>
                <button onclick="history.back()" class="btn btn--outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z"/>
                    </svg>
                    الرجوع
                </button>
            </div>
        <?php else: ?>
            <p class="error-description">
                عذراً، الصفحة المطلوبة غير موجودة.<br>
                يرجى التحقق من الرابط أو العودة إلى الصفحة الرئيسية.
            </p>
            <div class="error-actions">
                <a href="/" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"/>
                    </svg>
                    الصفحة الرئيسية
                </a>
                <button onclick="history.back()" class="btn btn--outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z"/>
                    </svg>
                    الرجوع
                </button>
            </div>
        <?php endif; ?>
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

// Auto-refresh error message every 30 seconds in case it's a temporary issue
setTimeout(() => {
    if (confirm('تبدو هذه صفحة خطأ. هل تريد تحديث الصفحة للتحقق مرة أخرى؟')) {
        window.location.reload();
    }
}, 30000);
</script>

</body>
</html>

