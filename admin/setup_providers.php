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

$pageTitle = 'إعداد دعم المزودين المتعددين';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_providers'])) {
    try {
        // فحص هيكل الجداول الحالي
        $servicesColumns = Database::fetchAll(
            "SELECT column_name FROM information_schema.columns 
             WHERE table_name = 'services_cache' AND table_schema = DATABASE() 
             ORDER BY ordinal_position"
        );
        $results[] = "📋 أعمدة services_cache: " . implode(', ', array_column($servicesColumns, 'column_name'));
        
        $ordersColumns = Database::fetchAll(
            "SELECT column_name FROM information_schema.columns 
             WHERE table_name = 'orders' AND table_schema = DATABASE() 
             ORDER BY ordinal_position"
        );
        $results[] = "📋 أعمدة orders: " . implode(', ', array_column($ordersColumns, 'column_name'));
        // فحص وإضافة عمود provider إلى services_cache
        $servicesProvider = Database::fetchOne(
            "SELECT COUNT(*) as count FROM information_schema.columns 
             WHERE table_name = 'services_cache' 
             AND column_name = 'provider' 
             AND table_schema = DATABASE()"
        );
        
        if ($servicesProvider['count'] == 0) {
            // التحقق من وجود عمود external_id أولاً
            $externalIdExists = Database::fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.columns 
                 WHERE table_name = 'services_cache' 
                 AND column_name = 'external_id' 
                 AND table_schema = DATABASE()"
            );
            
            if ($externalIdExists['count'] > 0) {
                Database::query(
                    "ALTER TABLE services_cache ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr' AFTER external_id"
                );
            } else {
                Database::query(
                    "ALTER TABLE services_cache ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr'"
                );
            }
            $results[] = "✅ تم إضافة عمود provider إلى services_cache";
        } else {
            $results[] = "✅ عمود provider موجود في services_cache";
        }
        
        // فحص وإضافة عمود provider إلى orders
        $ordersProvider = Database::fetchOne(
            "SELECT COUNT(*) as count FROM information_schema.columns 
             WHERE table_name = 'orders' 
             AND column_name = 'provider' 
             AND table_schema = DATABASE()"
        );
        
        if ($ordersProvider['count'] == 0) {
            // التحقق من وجود عمود external_id أولاً
            $externalIdExists = Database::fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.columns 
                 WHERE table_name = 'orders' 
                 AND column_name = 'external_id' 
                 AND table_schema = DATABASE()"
            );
            
            if ($externalIdExists['count'] > 0) {
                Database::query(
                    "ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr' AFTER external_id"
                );
            } else {
                Database::query(
                    "ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr'"
                );
            }
            $results[] = "✅ تم إضافة عمود provider إلى orders";
        } else {
            $results[] = "✅ عمود provider موجود في orders";
        }
        
        // إنشاء جدول المزودين
        Database::query("CREATE TABLE IF NOT EXISTS providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            api_url VARCHAR(255) NOT NULL,
            api_key VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            is_visible BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $results[] = "✅ تم إنشاء جدول providers";
        
        // إدراج المزودين الافتراضيين
        Database::query("INSERT INTO providers (name, display_name, api_url, api_key, priority) VALUES
            ('peakerr', 'Peakerr', 'https://peakerr.com/api/', 'YOUR_PEAKERR_KEY', 1),
            ('newprovider', 'New Provider', 'https://newprovider.com/api/', 'YOUR_NEW_KEY', 2)
            ON DUPLICATE KEY UPDATE 
            display_name = VALUES(display_name),
            api_url = VALUES(api_url),
            api_key = VALUES(api_key),
            priority = VALUES(priority)");
        
        $results[] = "✅ تم إدراج المزودين الافتراضيين";
        
        // إنشاء جدول إحصائيات المزودين
        Database::query("CREATE TABLE IF NOT EXISTS provider_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(50) NOT NULL,
            total_orders INT DEFAULT 0,
            successful_orders INT DEFAULT 0,
            failed_orders INT DEFAULT 0,
            total_revenue DECIMAL(10,2) DEFAULT 0.00,
            last_sync TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_provider (provider)
        )");
        
        $results[] = "✅ تم إنشاء جدول provider_stats";
        
        // إضافة الفهارس
        try {
            Database::query("CREATE INDEX idx_services_provider ON services_cache(provider)");
            $results[] = "✅ تم إضافة فهرس services_cache";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $results[] = "✅ فهرس services_cache موجود بالفعل";
            } else {
                throw $e;
            }
        }
        
        try {
            Database::query("CREATE INDEX idx_orders_provider ON orders(provider)");
            $results[] = "✅ تم إضافة فهرس orders";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $results[] = "✅ فهرس orders موجود بالفعل";
            } else {
                throw $e;
            }
        }
        
        $results[] = "🎉 تم إعداد دعم المزودين المتعددين بنجاح!";
        
    } catch (Exception $e) {
        $results[] = "❌ خطأ: " . $e->getMessage();
    }
}

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="card-subtitle">إعداد قاعدة البيانات لدعم المزودين المتعددين</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($results)): ?>
                <div class="alert alert-info">
                    <h3>نتائج الإعداد:</h3>
                    <ul>
                        <?php foreach ($results as $result): ?>
                            <li><?php echo htmlspecialchars($result); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <p>سيقوم هذا الإعداد بإضافة:</p>
                    <ul>
                        <li>عمود <code>provider</code> لجدولي <code>services_cache</code> و <code>orders</code></li>
                        <li>جدول <code>providers</code> لإدارة المزودين</li>
                        <li>جدول <code>provider_stats</code> للإحصائيات</li>
                        <li>فهارس لتحسين الأداء</li>
                    </ul>
                </div>
                
                <button type="submit" name="setup_providers" class="btn btn-primary">
                    بدء الإعداد
                </button>
            </form>
            
            <div class="mt-4">
                <h3>الخطوات التالية:</h3>
                <ol>
                    <li>قم بتحديث API keys في جدول <code>providers</code></li>
                    <li>استخدم <a href="/admin/sync_multi.php">صفحة المزامنة المتعددة</a></li>
                    <li>راقب المزودين من <a href="/admin/providers.php">صفحة إدارة المزودين</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>
