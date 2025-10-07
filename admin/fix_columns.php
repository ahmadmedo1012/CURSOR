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

$pageTitle = 'إصلاح أعمدة المزودين';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // فحص هيكل جدول services_cache
        $servicesColumns = Database::fetchAll(
            "SELECT column_name FROM information_schema.columns 
             WHERE table_name = 'services_cache' AND table_schema = DATABASE()"
        );
        $servicesColumnNames = array_column($servicesColumns, 'column_name');
        $results[] = "🔍 أعمدة services_cache: " . implode(', ', $servicesColumnNames);
        
        // إضافة عمود provider إلى services_cache إذا لم يكن موجوداً
        if (!in_array('provider', $servicesColumnNames)) {
            if (in_array('external_id', $servicesColumnNames)) {
                Database::query("ALTER TABLE services_cache ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr' AFTER external_id");
                $results[] = "✅ تم إضافة عمود provider إلى services_cache بعد external_id";
            } else {
                Database::query("ALTER TABLE services_cache ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr'");
                $results[] = "✅ تم إضافة عمود provider إلى services_cache";
            }
        } else {
            $results[] = "✅ عمود provider موجود في services_cache";
        }
        
        // فحص هيكل جدول orders
        $ordersColumns = Database::fetchAll(
            "SELECT column_name FROM information_schema.columns 
             WHERE table_name = 'orders' AND table_schema = DATABASE()"
        );
        $ordersColumnNames = array_column($ordersColumns, 'column_name');
        $results[] = "🔍 أعمدة orders: " . implode(', ', $ordersColumnNames);
        
        // إضافة عمود provider إلى orders إذا لم يكن موجوداً
        if (!in_array('provider', $ordersColumnNames)) {
            if (in_array('external_id', $ordersColumnNames)) {
                Database::query("ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr' AFTER external_id");
                $results[] = "✅ تم إضافة عمود provider إلى orders بعد external_id";
            } else {
                Database::query("ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr'");
                $results[] = "✅ تم إضافة عمود provider إلى orders";
            }
        } else {
            $results[] = "✅ عمود provider موجود في orders";
        }
        
        // إنشاء الفهارس
        try {
            Database::query("CREATE INDEX idx_services_provider ON services_cache(provider)");
            $results[] = "✅ تم إنشاء فهرس services_cache";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $results[] = "✅ فهرس services_cache موجود بالفعل";
            } else {
                throw $e;
            }
        }
        
        try {
            Database::query("CREATE INDEX idx_orders_provider ON orders(provider)");
            $results[] = "✅ تم إنشاء فهرس orders";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $results[] = "✅ فهرس orders موجود بالفعل";
            } else {
                throw $e;
            }
        }
        
        $results[] = "🎉 تم إصلاح جميع الأعمدة بنجاح!";
        
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
            <p class="card-subtitle">إصلاح أعمدة المزودين في قاعدة البيانات</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($results)): ?>
                <div class="alert alert-info">
                    <h3>نتائج الإصلاح:</h3>
                    <ul>
                        <?php foreach ($results as $result): ?>
                            <li><?php echo htmlspecialchars($result); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <p>سيقوم هذا الإصلاح بإضافة عمود <code>provider</code> للجداول المطلوبة:</p>
                    <ul>
                        <li>جدول <code>services_cache</code></li>
                        <li>جدول <code>orders</code></li>
                        <li>فهارس لتحسين الأداء</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    إصلاح الأعمدة
                </button>
            </form>
            
            <div class="mt-4">
                <h3>الخطوات التالية:</h3>
                <ol>
                    <li><a href="/admin/setup_providers.php">إعداد دعم المزودين المتعددين</a></li>
                    <li><a href="/admin/sync_multi.php">مزامنة متعددة المزودين</a></li>
                    <li><a href="/admin/providers.php">إدارة المزودين</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

