<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class NotificationManager {
    
    /**
     * جلب الإشعارات النشطة للعرض
     */
    public static function getActiveNotifications($targetAudience = 'all', $currentPage = null) {
        try {
            // فحص وجود جدول الإشعارات
            $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notifications'");
            if (!$tableExists) {
                return [];
            }
            
            $userIP = self::getUserIP();
            $now = date('Y-m-d H:i:s');
            
            $sql = "SELECT n.*, 
                           (SELECT COUNT(*) FROM notification_views nv WHERE nv.notification_id = n.id AND nv.user_ip = ?) as viewed_count,
                           (SELECT dismissed FROM notification_views nv WHERE nv.notification_id = n.id AND nv.user_ip = ? LIMIT 1) as is_dismissed
                    FROM notifications n 
                    WHERE n.is_active = 1 
                    AND (n.start_date <= ? OR n.start_date IS NULL)
                    AND (n.end_date >= ? OR n.end_date IS NULL)
                    AND n.target_audience IN (?, 'all')
                    ORDER BY n.priority DESC, n.created_at DESC";
            
            $params = [$userIP, $userIP, $now, $now, $targetAudience];
            
            $notifications = Database::fetchAll($sql, $params);
            
            // فلترة الإشعارات حسب الصفحة المحددة
            if ($currentPage) {
                $notifications = array_filter($notifications, function($notification) use ($currentPage) {
                    if (!$notification['show_on_pages']) return true;
                    
                    $allowedPages = json_decode($notification['show_on_pages'], true);
                    return in_array($currentPage, $allowedPages);
                });
            }
            
            // إزالة الإشعارات المرفوضة
            $notifications = array_filter($notifications, function($notification) {
                return !$notification['is_dismissed'];
            });
            
            return $notifications;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب الإشعارات: " . $e->getMessage());
            return [];
        } catch (Error $e) {
            error_log("خطأ PHP في جلب الإشعارات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تسجيل عرض الإشعار
     */
    public static function markAsViewed($notificationId) {
        try {
            $userIP = self::getUserIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // إدراج أو تحديث سجل العرض
            Database::query(
                "INSERT INTO notification_views (notification_id, user_ip, user_agent, viewed_at) 
                 VALUES (?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE viewed_at = NOW()",
                [$notificationId, $userIP, $userAgent]
            );
            
            // تحديث الإحصائيات
            self::updateStats($notificationId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("خطأ في تسجيل عرض الإشعار: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * رفض الإشعار
     */
    public static function dismissNotification($notificationId) {
        try {
            $userIP = self::getUserIP();
            
            Database::query(
                "UPDATE notification_views 
                 SET dismissed = 1, dismissed_at = NOW() 
                 WHERE notification_id = ? AND user_ip = ?",
                [$notificationId, $userIP]
            );
            
            // تحديث الإحصائيات
            self::updateStats($notificationId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("خطأ في رفض الإشعار: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إنشاء إشعار جديد
     */
    public static function createNotification($data) {
        try {
            $sql = "INSERT INTO notifications (
                        title, message, type, priority, target_audience, 
                        start_date, end_date, is_active, show_on_pages, 
                        dismissible, auto_dismiss_after, click_action, 
                        background_color, text_color, icon, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['title'],
                $data['message'],
                $data['type'] ?? 'info',
                $data['priority'] ?? 'normal',
                $data['target_audience'] ?? 'all',
                $data['start_date'] ?? date('Y-m-d H:i:s'),
                $data['end_date'] ?? null,
                $data['is_active'] ?? true,
                isset($data['show_on_pages']) ? json_encode($data['show_on_pages']) : null,
                $data['dismissible'] ?? true,
                $data['auto_dismiss_after'] ?? 0,
                $data['click_action'] ?? null,
                $data['background_color'] ?? null,
                $data['text_color'] ?? null,
                $data['icon'] ?? null,
                $data['created_by'] ?? 'admin'
            ];
            
            $notificationId = Database::query($sql, $params);
            
            // إنشاء إحصائيات للإشعار الجديد
            Database::query(
                "INSERT INTO notification_stats (notification_id, total_views, unique_views) VALUES (?, 0, 0)",
                [$notificationId]
            );
            
            return $notificationId;
            
        } catch (Exception $e) {
            error_log("خطأ في إنشاء الإشعار: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحديث إشعار موجود
     */
    public static function updateNotification($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'title', 'message', 'type', 'priority', 'target_audience',
                'start_date', 'end_date', 'is_active', 'show_on_pages',
                'dismissible', 'auto_dismiss_after', 'click_action',
                'background_color', 'text_color', 'icon'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    if ($field === 'show_on_pages' && is_array($data[$field])) {
                        $params[] = json_encode($data[$field]);
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) return false;
            
            $params[] = $id;
            
            Database::query(
                "UPDATE notifications SET " . implode(', ', $fields) . " WHERE id = ?",
                $params
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("خطأ في تحديث الإشعار: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف إشعار
     */
    public static function deleteNotification($id) {
        try {
            Database::query("DELETE FROM notifications WHERE id = ?", [$id]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في حذف الإشعار: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب إحصائيات الإشعار
     */
    public static function getNotificationStats($notificationId) {
        try {
            return Database::fetchOne(
                "SELECT * FROM notification_stats WHERE notification_id = ?",
                [$notificationId]
            );
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الإشعار: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * جلب جميع الإشعارات للإدارة
     */
    public static function getAllNotifications($limit = 50, $offset = 0) {
        try {
            $sql = "SELECT n.*, 
                           ns.total_views, ns.total_dismissals, ns.unique_views,
                           ns.last_viewed_at
                    FROM notifications n
                    LEFT JOIN notification_stats ns ON n.id = ns.notification_id
                    ORDER BY n.created_at DESC
                    LIMIT ? OFFSET ?";
            
            return Database::fetchAll($sql, [$limit, $offset]);
            
        } catch (Exception $e) {
            error_log("خطأ في جلب جميع الإشعارات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تحديث الإحصائيات
     */
    private static function updateStats($notificationId) {
        try {
            Database::query(
                "UPDATE notification_stats SET 
                    total_views = (SELECT COUNT(*) FROM notification_views WHERE notification_id = ?),
                    total_dismissals = (SELECT COUNT(*) FROM notification_views WHERE notification_id = ? AND dismissed = 1),
                    unique_views = (SELECT COUNT(DISTINCT user_ip) FROM notification_views WHERE notification_id = ?),
                    last_viewed_at = (SELECT MAX(viewed_at) FROM notification_views WHERE notification_id = ?),
                    updated_at = NOW()
                WHERE notification_id = ?",
                [$notificationId, $notificationId, $notificationId, $notificationId, $notificationId]
            );
        } catch (Exception $e) {
            error_log("خطأ في تحديث الإحصائيات: " . $e->getMessage());
        }
    }
    
    /**
     * جلب عنوان IP للمستخدم
     */
    private static function getUserIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * تنظيف الإشعارات المنتهية الصلاحية
     */
    public static function cleanupExpiredNotifications() {
        try {
            $now = date('Y-m-d H:i:s');
            
            // إلغاء تفعيل الإشعارات المنتهية الصلاحية
            Database::query(
                "UPDATE notifications SET is_active = 0 WHERE end_date < ? AND is_active = 1",
                [$now]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("خطأ في تنظيف الإشعارات المنتهية: " . $e->getMessage());
            return false;
        }
    }
}
?>
