<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Services/NotificationManager.php';

class NotificationDisplay {
    
    /**
     * عرض الإشعارات في الصفحة
     */
    public static function render($targetAudience = 'all', $currentPage = null) {
        try {
            // فحص وجود جدول الإشعارات
            $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notifications'");
            if (!$tableExists) {
                return '';
            }
            
            $notifications = NotificationManager::getActiveNotifications($targetAudience, $currentPage);
            
            if (empty($notifications)) {
                return '';
            }
            
            $html = '<div id="notifications-container" class="notifications-container">';
            
            foreach ($notifications as $notification) {
                $html .= self::renderNotification($notification);
            }
            
            $html .= '</div>';
            
            return $html;
            
        } catch (Exception $e) {
            error_log("خطأ في عرض الإشعارات: " . $e->getMessage());
            return '';
        } catch (Error $e) {
            error_log("خطأ PHP في عرض الإشعارات: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * عرض إشعار واحد
     */
    private static function renderNotification($notification) {
        $style = self::getNotificationStyle($notification);
        $icon = self::getNotificationIcon($notification);
        $autoDismiss = $notification['auto_dismiss_after'] > 0 ? "data-auto-dismiss='{$notification['auto_dismiss_after']}'" : '';
        
        $html = "<div class='notification notification-{$notification['type']}' 
                        data-notification-id='{$notification['id']}' 
                        data-dismissible='{$notification['dismissible']}' 
                        {$autoDismiss}
                        style='{$style}'
                        dir='rtl'>";
        
        // الأيقونة
        if ($icon) {
            $html .= "<div class='notification-icon'>{$icon}</div>";
        }
        
        // المحتوى
        $html .= "<div class='notification-content'>";
        
        if ($notification['title']) {
            $html .= "<div class='notification-title'>" . htmlspecialchars($notification['title']) . "</div>";
        }
        
        if ($notification['message']) {
            $html .= "<div class='notification-message'>" . htmlspecialchars($notification['message']) . "</div>";
        }
        
        $html .= "</div>";
        
        // زر الإغلاق
        if ($notification['dismissible']) {
            $html .= "<button class='notification-close' onclick='dismissNotification({$notification['id']})' aria-label='إغلاق'>";
            $html .= "<span>&times;</span>";
            $html .= "</button>";
        }
        
        $html .= "</div>";
        
        return $html;
    }
    
    /**
     * الحصول على تنسيق الإشعار
     */
    private static function getNotificationStyle($notification) {
        $styles = [];
        
        // الألوان المخصصة
        if ($notification['background_color']) {
            $styles[] = "background-color: {$notification['background_color']}";
        }
        
        if ($notification['text_color']) {
            $styles[] = "color: {$notification['text_color']}";
        }
        
        return implode('; ', $styles);
    }
    
    /**
     * الحصول على أيقونة الإشعار
     */
    private static function getNotificationIcon($notification) {
        // أيقونة مخصصة
        if ($notification['icon']) {
            return $notification['icon'];
        }
        
        // أيقونات افتراضية حسب النوع
        $icons = [
            'info' => 'ℹ️',
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'promotion' => '🎉'
        ];
        
        return $icons[$notification['type']] ?? '🔔';
    }
    
    /**
     * JavaScript للتعامل مع الإشعارات
     */
    public static function getJavaScript() {
        return "
        <script>
        // تسجيل عرض الإشعار عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                const notificationId = notification.dataset.notificationId;
                if (notificationId) {
                    markNotificationAsViewed(notificationId);
                    
                    // الاختفاء التلقائي
                    const autoDismiss = notification.dataset.autoDismiss;
                    if (autoDismiss && parseInt(autoDismiss) > 0) {
                        setTimeout(function() {
                            dismissNotification(notificationId);
                        }, parseInt(autoDismiss) * 1000);
                    }
                }
            });
        });
        
        // تسجيل عرض الإشعار
        function markNotificationAsViewed(notificationId) {
            fetch('/api/notifications.php?action=view', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            }).catch(function(error) {
                // Silent error handling
            });
        }
        
        // رفض الإشعار
        function dismissNotification(notificationId) {
            const notification = document.querySelector('[data-notification-id=\"' + notificationId + '\"]');
            if (notification) {
                // تأثير الاختفاء
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                notification.style.transition = 'all 0.3s ease';
                
                setTimeout(function() {
                    notification.remove();
                }, 300);
                
                // تسجيل الرفض في قاعدة البيانات
                fetch('/api/notifications.php?action=dismiss', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notification_id: notificationId
                    })
                }).catch(function(error) {
                    // Silent error handling
                });
            }
        }
        
        // إضافة تأثير النقر للإشعارات
        document.addEventListener('click', function(e) {
            const notification = e.target.closest('.notification');
            if (notification && !e.target.closest('.notification-close')) {
                const notificationId = notification.dataset.notificationId;
                const clickAction = notification.dataset.clickAction;
                
                if (clickAction) {
                    window.open(clickAction, '_blank');
                }
            }
        });
        </script>";
    }
    
    /**
     * CSS للإشعارات
     */
    public static function getCSS() {
        return "
        <style>
        .notifications-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            width: 100%;
        }
        
        .notification {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .notification:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }
        
        .notification-info {
            background: linear-gradient(135deg, #1A3C8C 0%, #2c5aa0 100%);
            color: white;
        }
        
        .notification-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .notification-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #000;
        }
        
        .notification-error {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
        }
        
        .notification-promotion {
            background: linear-gradient(135deg, #C9A227 0%, #ffd700 100%);
            color: #000;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            margin-left: 0.75rem;
            flex-shrink: 0;
            margin-top: 0.25rem;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .notification-message {
            font-size: 0.9rem;
            line-height: 1.4;
            opacity: 0.9;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            margin-left: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .notification-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        /* تأثير shimmer */
        .notification::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .notification:hover::before {
            left: 100%;
        }
        
        /* استجابة للشاشات الصغيرة */
        @media (max-width: 768px) {
            .notifications-container {
                right: 10px;
                left: 10px;
                max-width: none;
                top: 70px;
            }
            
            .notification {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .notification-icon {
                font-size: 1.25rem;
                margin-left: 0.5rem;
            }
            
            .notification-title {
                font-size: 0.9rem;
            }
            
            .notification-message {
                font-size: 0.8rem;
            }
        }
        
        /* تأثيرات الحركة */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification {
            animation: slideInRight 0.3s ease-out;
        }
        </style>";
    }
}
?>
