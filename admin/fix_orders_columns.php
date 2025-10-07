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

$pageTitle = 'إصلاح أعمدة جدول الطلبات';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_columns'])) {
    try {
        // فحص وإضافة عمود notes
        $notesExists = Database::fetchOne("SHOW COLUMNS FROM orders LIKE 'notes'");
        if (!$notesExists) {
            Database::query("ALTER TABLE orders ADD COLUMN notes TEXT NULL");
            $results[] = "✅ تم إضافة عمود notes";
        } else {
            $results[] = "✅ عمود notes موجود بالفعل";
        }
        
        // فحص وإضافة عمود provider
        $providerExists = Database::fetchOne("SHOW COLUMNS FROM orders LIKE 'provider'");
        if (!$providerExists) {
            Database::query("ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'peakerr'");
            $results[] = "✅ تم إضافة عمود provider";
        } else {
            $results[] = "✅ عمود provider موجود بالفعل";
        }
        
        // فحص وإضافة عمود external_id
        $externalIdExists = Database::fetchOne("SHOW COLUMNS FROM orders LIKE 'external_id'");
        if (!$externalIdExists) {
            Database::query("ALTER TABLE orders ADD COLUMN external_id VARCHAR(255) NULL");
            $results[] = "✅ تم إضافة عمود external_id";
        } else {
            $results[] = "✅ عمود external_id موجود بالفعل";
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
            <p class="card-subtitle">إضافة الأعمدة المفقودة في جدول الطلبات</p>
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
                    <p>سيقوم هذا الإصلاح بإضافة الأعمدة المفقودة:</p>
                    <ul>
                        <li><code>notes</code> - لحفظ ملاحظات الطلب</li>
                        <li><code>provider</code> - لتحديد مزود الخدمة</li>
                        <li><code>external_id</code> - لتخزين معرف الخدمة الخارجي</li>
                    </ul>
                </div>
                
                <button type="submit" name="fix_columns" class="btn btn-primary">
                    إصلاح الأعمدة
                </button>
            </form>
            
            <div class="mt-4">
                <h3>بعد الإصلاح:</h3>
                <ol>
                    <li>سيتم حل مشكلة "Unknown column 'notes'"</li>
                    <li>يمكن إنشاء الطلبات بدون أخطاء</li>
                    <li>سيتم دعم المزودين المتعددين</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

