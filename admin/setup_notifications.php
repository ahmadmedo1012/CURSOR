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

$pageTitle = 'إعداد نظام الإشعارات';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_notifications'])) {
    try {
        // تشغيل migration الإشعارات
        $migrationFile = __DIR__ . '/../database/008_notifications_system.sql';
        
        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        Database::query($statement);
                    } catch (Exception $e) {
                        // تجاهل الأخطاء إذا كانت الجداول موجودة بالفعل
                        if (strpos($e->getMessage(), 'already exists') === false && 
                            strpos($e->getMessage(), 'Duplicate') === false &&
                            strpos($e->getMessage(), 'Duplicate entry') === false) {
                            throw $e;
                        }
                        // تسجيل الخطأ المتجاهل
                        error_log("تم تجاهل خطأ تكرار في setup_notifications: " . $e->getMessage());
                    }
                }
            }
            
            $results[] = "✅ تم إنشاء جداول الإشعارات بنجاح";
        } else {
            throw new Exception("ملف Migration غير موجود");
        }
        
        // إنشاء مجلد API إذا لم يكن موجوداً
        $apiDir = __DIR__ . '/../api';
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0755, true);
            $results[] = "✅ تم إنشاء مجلد API";
        }
        
        // إنشاء مجلد Components إذا لم يكن موجوداً
        $componentsDir = __DIR__ . '/../src/Components';
        if (!is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
            $results[] = "✅ تم إنشاء مجلد Components";
        }
        
        $results[] = "🎉 تم إعداد نظام الإشعارات بنجاح!";
        
    } catch (Exception $e) {
        $results[] = "❌ خطأ: " . $e->getMessage();
    } catch (Error $e) {
        $results[] = "❌ خطأ PHP: " . $e->getMessage();
    }
}

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="card-subtitle">إعداد قاعدة البيانات والملفات المطلوبة لنظام الإشعارات</p>
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
                    <p>سيقوم هذا الإعداد بإنشاء:</p>
                    <ul>
                        <li>جدول <code>notifications</code> - لتخزين الإشعارات</li>
                        <li>جدول <code>notification_views</code> - لتتبع المشاهدات</li>
                        <li>جدول <code>notification_stats</code> - للإحصائيات</li>
                        <li>إشعارات تجريبية للاختبار</li>
                        <li>المجلدات المطلوبة للـ API والمكونات</li>
                    </ul>
                </div>
                
                <button type="submit" name="setup_notifications" class="btn btn-primary">
                    بدء الإعداد
                </button>
            </form>
            
            <div class="mt-4">
                <h3>الخطوات التالية:</h3>
                <ol>
                    <li>قم بتشغيل الإعداد أعلاه</li>
                    <li>اذهب إلى <a href="/admin/notifications.php">إدارة الإشعارات</a></li>
                    <li>أنشئ إشعارات جديدة أو فعّل الإشعارات التجريبية</li>
                    <li>اختبر الإشعارات في الموقع</li>
                </ol>
            </div>
            
            <div class="mt-4">
                <h3>ميزات نظام الإشعارات:</h3>
                <div class="grid grid-2">
                    <div>
                        <h4>للإدارة:</h4>
                        <ul>
                            <li>إنشاء وتعديل الإشعارات</li>
                            <li>تحديد الجمهور المستهدف</li>
                            <li>جدولة الإشعارات</li>
                            <li>إحصائيات مفصلة</li>
                            <li>ألوان وأيقونات مخصصة</li>
                        </ul>
                    </div>
                    <div>
                        <h4>للعملاء:</h4>
                        <ul>
                            <li>عرض تلقائي للإشعارات</li>
                            <li>إمكانية الرفض</li>
                            <li>اختفاء تلقائي</li>
                            <li>تصميم متجاوب</li>
                            <li>تأثيرات بصرية جميلة</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>
