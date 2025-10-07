<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/NotificationManager.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // فحص وجود جدول الإشعارات
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notifications'");
    if (!$tableExists) {
        throw new Exception('نظام الإشعارات غير مثبت');
    }
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'active':
                    // جلب الإشعارات النشطة
                    $targetAudience = $_GET['audience'] ?? 'all';
                    $currentPage = $_GET['page'] ?? null;
                    
                    $notifications = NotificationManager::getActiveNotifications($targetAudience, $currentPage);
                    
                    echo json_encode([
                        'success' => true,
                        'notifications' => $notifications,
                        'count' => count($notifications)
                    ]);
                    break;
                    
                case 'stats':
                    // جلب إحصائيات إشعار محدد
                    $notificationId = $_GET['id'] ?? null;
                    if (!$notificationId) {
                        throw new Exception('معرف الإشعار مطلوب');
                    }
                    
                    $stats = NotificationManager::getNotificationStats($notificationId);
                    
                    echo json_encode([
                        'success' => true,
                        'stats' => $stats
                    ]);
                    break;
                    
                default:
                    throw new Exception('إجراء غير صالح');
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'view':
                    // تسجيل عرض الإشعار
                    $input = json_decode(file_get_contents('php://input'), true);
                    $notificationId = $input['notification_id'] ?? null;
                    
                    if (!$notificationId) {
                        throw new Exception('معرف الإشعار مطلوب');
                    }
                    
                    $result = NotificationManager::markAsViewed($notificationId);
                    
                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'تم تسجيل العرض' : 'فشل في تسجيل العرض'
                    ]);
                    break;
                    
                case 'dismiss':
                    // رفض الإشعار
                    $input = json_decode(file_get_contents('php://input'), true);
                    $notificationId = $input['notification_id'] ?? null;
                    
                    if (!$notificationId) {
                        throw new Exception('معرف الإشعار مطلوب');
                    }
                    
                    $result = NotificationManager::dismissNotification($notificationId);
                    
                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'تم رفض الإشعار' : 'فشل في رفض الإشعار'
                    ]);
                    break;
                    
                default:
                    throw new Exception('إجراء غير صالح');
            }
            break;
            
        default:
            throw new Exception('طريقة HTTP غير مدعومة');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم'
    ]);
}
?>
