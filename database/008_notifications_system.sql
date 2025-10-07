-- نظام الإشعارات العامة (إصدار محسن)
-- Migration: 008_notifications_system.sql

-- جدول الإشعارات
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'promotion') DEFAULT 'info',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    target_audience ENUM('all', 'logged_in', 'guests') DEFAULT 'all',
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    show_on_pages JSON NULL, -- صفحات محددة للعرض
    dismissible BOOLEAN DEFAULT TRUE,
    auto_dismiss_after INT DEFAULT 0, -- بالثواني، 0 = لا يختفي تلقائياً
    click_action VARCHAR(255) NULL, -- رابط أو إجراء عند النقر
    background_color VARCHAR(7) NULL, -- لون الخلفية المخصص
    text_color VARCHAR(7) NULL, -- لون النص المخصص
    icon VARCHAR(100) NULL, -- أيقونة مخصصة
    created_by VARCHAR(100) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_type (type),
    INDEX idx_priority (priority)
);

-- جدول تتبع الإشعارات للمستخدمين
CREATE TABLE IF NOT EXISTS notification_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dismissed BOOLEAN DEFAULT FALSE,
    dismissed_at TIMESTAMP NULL,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_notification (notification_id, user_ip),
    INDEX idx_notification (notification_id),
    INDEX idx_user_ip (user_ip),
    INDEX idx_viewed_at (viewed_at)
);

-- جدول إحصائيات الإشعارات
CREATE TABLE IF NOT EXISTS notification_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    total_views INT DEFAULT 0,
    total_dismissals INT DEFAULT 0,
    unique_views INT DEFAULT 0,
    last_viewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification_stats (notification_id)
);

-- إدراج إشعارات تجريبية فقط إذا لم تكن موجودة
INSERT IGNORE INTO notifications (title, message, type, priority, target_audience, is_active, dismissible, auto_dismiss_after) VALUES
('مرحباً بك في GameBox!', 'نحن سعداء لوجودك معنا. استمتع بخدماتنا المتميزة وأسعارنا التنافسية.', 'success', 'normal', 'all', TRUE, TRUE, 10),
('عرض خاص!', 'احصل على خصم 20% على جميع خدمات تيك توك لفترة محدودة.', 'promotion', 'high', 'all', FALSE, TRUE, 15);

-- إنشاء إحصائيات للإشعارات التجريبية فقط إذا لم تكن موجودة
INSERT IGNORE INTO notification_stats (notification_id, total_views, unique_views) 
SELECT n.id, 0, 0 
FROM notifications n 
WHERE n.id IN (1, 2) 
AND NOT EXISTS (
    SELECT 1 FROM notification_stats ns 
    WHERE ns.notification_id = n.id
);

