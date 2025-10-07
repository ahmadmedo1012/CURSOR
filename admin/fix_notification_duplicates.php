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

$pageTitle = 'إصلاح تكرار الإشعارات';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_duplicates'])) {
    try {
        // حذف الإحصائيات المكررة
        Database::query("
            DELETE ns1 FROM notification_stats ns1
            INNER JOIN notification_stats ns2 
            WHERE ns1.id > ns2.id 
            AND ns1.notification_id = ns2.notification_id
        ");
        $results[] = "✅ تم حذف الإحصائيات المكررة";
        
        // حذف الإشعارات المكررة
        Database::query("
            DELETE n1 FROM notifications n1
            INNER JOIN notifications n2 
            WHERE n1.id > n2.id 
            AND n1.title = n2.title 
            AND n1.message = n2.message
        ");
        $results[] = "✅ تم حذف الإشعارات المكررة";
        
        // إعادة إنشاء الإحصائيات المفقودة
        Database::query("
            INSERT IGNORE INTO notification_stats (notification_id, total_views, unique_views)
            SELECT n.id, 0, 0
            FROM notifications n
            LEFT JOIN notification_stats ns ON n.id = ns.notification_id
            WHERE ns.notification_id IS NULL
        ");
        $results[] = "✅ تم إنشاء الإحصائيات المفقودة";
        
        $results[] = "🎉 تم إصلاح جميع مشاكل التكرار بنجاح!";
        
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
            <p class="card-subtitle">إصلاح مشاكل التكرار في نظام الإشعارات</p>
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
                    <p>سيقوم هذا الإصلاح بـ:</p>
                    <ul>
                        <li>حذف الإحصائيات المكررة</li>
                        <li>حذف الإشعارات المكررة</li>
                        <li>إعادة إنشاء الإحصائيات المفقودة</li>
                    </ul>
                </div>
                
                <button type="submit" name="fix_duplicates" class="btn btn-primary">
                    إصلاح المشاكل
                </button>
            </form>
            
            <div class="mt-4">
                <h3>بعد الإصلاح:</h3>
                <ol>
                    <li>اذهب إلى <a href="/admin/notifications.php">إدارة الإشعارات</a></li>
                    <li>تأكد من عدم وجود إشعارات مكررة</li>
                    <li>أنشئ إشعارات جديدة إذا أردت</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

