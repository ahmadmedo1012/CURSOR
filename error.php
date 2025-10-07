<?php
// Centralized error handler
require_once __DIR__ . '/config/config.php';

// Get error details from query params or defaults
$errorCode = isset($_GET['code']) ? intval($_GET['code']) : 500;
$errorMessage = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';

// Default messages for common error codes
$defaultMessages = [
    404 => 'الصفحة المطلوبة غير موجودة',
    500 => 'حدث خطأ في الخادم',
    403 => 'غير مسموح لك بالوصول لهذه الصفحة',
    400 => 'طلب غير صحيح'
];

$pageTitle = 'خطأ ' . $errorCode;

// Set appropriate HTTP status
http_response_code($errorCode);

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon">
                <?php if ($errorCode == 404): ?>
                    <span style="font-size: 4rem;">🔍</span>
                <?php elseif ($errorCode == 500): ?>
                    <span style="font-size: 4rem;">⚠️</span>
                <?php else: ?>
                    <span style="font-size: 4rem;">❌</span>
                <?php endif; ?>
            </div>
            
            <h1 class="error-title">خطأ <?php echo $errorCode; ?></h1>
            
            <p class="error-message">
                <?php 
                if ($errorMessage) {
                    echo $errorMessage;
                } else {
                    echo $defaultMessages[$errorCode] ?? 'حدث خطأ غير متوقع';
                }
                ?>
            </p>
            
            <div class="error-actions">
                <a href="/" class="btn btn-primary">العودة للرئيسية</a>
                <button onclick="history.back()" class="btn">الصفحة السابقة</button>
                <?php if ($errorCode == 404): ?>
                    <a href="/catalog.php" class="btn">تصفح الخدمات</a>
                <?php endif; ?>
            </div>
            
            <?php if ($errorCode == 404): ?>
                <div class="error-suggestions">
                    <h3>ربما تبحث عن:</h3>
                    <ul>
                        <li><a href="/catalog.php">جميع الخدمات</a></li>
                        <li><a href="/track.php">تتبع الطلبات</a></li>
                        <li><a href="/account/">حسابي</a></li>
                        <li><a href="/wallet/">المحفظة</a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.error-page {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem 0;
}

.error-content {
    max-width: 500px;
    margin: 0 auto;
}

.error-icon {
    margin-bottom: 1.5rem;
}

.error-title {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.error-message {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.error-suggestions {
    background: var(--background-secondary);
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 2rem;
}

.error-suggestions h3 {
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.error-suggestions ul {
    list-style: none;
    padding: 0;
}

.error-suggestions li {
    margin-bottom: 0.5rem;
}

.error-suggestions a {
    color: var(--accent-color);
    text-decoration: none;
}

.error-suggestions a:hover {
    text-decoration: underline;
}

@media (max-width: 480px) {
    .error-title {
        font-size: 2rem;
    }
    
    .error-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .error-actions .btn {
        width: 100%;
        max-width: 200px;
    }
}
</style>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
