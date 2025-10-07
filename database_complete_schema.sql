-- =====================================================
-- GameBox Database Schema - Ultimate & Permission-Safe
-- =====================================================
-- هذا الملف يحتوي على جميع الجداول والخانات المطلوبة للنظام
-- تاريخ الإنشاء: 2024
-- الإصدار: 4.0 - Permission-Safe (No Views/Procedures/Triggers)
-- =====================================================
-- تعطيل فحص المفاتيح الخارجية مؤقتاً
SET FOREIGN_KEY_CHECKS = 0;
-- تعطيل فحص SQL mode مؤقتاً لتجنب أخطاء التوافق
SET SESSION sql_mode = '';
-- =====================================================
-- 1. جدول المستخدمين (Users)
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `name` varchar(100) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `balance_lyd` decimal(10, 2) DEFAULT 0.00,
    `balance_usd` decimal(10, 2) DEFAULT 0.00,
    `is_active` tinyint(1) DEFAULT 1,
    `is_verified` tinyint(1) DEFAULT 0,
    `verification_token` varchar(100) DEFAULT NULL,
    `reset_token` varchar(100) DEFAULT NULL,
    `reset_token_expires` datetime DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_username` (`username`),
    UNIQUE KEY `unique_email` (`email`),
    KEY `idx_username` (`username`),
    KEY `idx_email` (`email`),
    KEY `idx_is_active` (`is_active`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 2. جدول الخدمات المؤقتة (Services Cache)
-- =====================================================
DROP TABLE IF EXISTS `services_cache`;
CREATE TABLE `services_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `external_id` varchar(50) NOT NULL,
    `name` varchar(255) NOT NULL,
    `name_ar` varchar(255) DEFAULT NULL,
    `category` varchar(100) NOT NULL,
    `category_ar` varchar(100) DEFAULT NULL,
    `group_slug` varchar(50) NOT NULL,
    `subcategory` varchar(100) DEFAULT NULL,
    `type` varchar(50) DEFAULT NULL,
    `rate_per_1k` decimal(10, 4) DEFAULT NULL,
    `rate_per_1k_lyd` decimal(10, 4) DEFAULT NULL,
    `rate_per_1k_usd` decimal(10, 4) DEFAULT NULL,
    `min` int(11) DEFAULT 0,
    `max` int(11) DEFAULT 0,
    `description` text DEFAULT NULL,
    `description_ar` text DEFAULT NULL,
    `orders_count` int(11) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `is_visible` tinyint(1) DEFAULT 1,
    `is_deleted` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_external_id` (`external_id`),
    KEY `idx_group_slug` (`group_slug`),
    KEY `idx_subcategory` (`subcategory`),
    KEY `idx_category` (`category`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_is_visible` (`is_visible`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_orders_count` (`orders_count`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 3. جدول الطلبات (Orders)
-- =====================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `service_id` int(11) NOT NULL,
    `external_order_id` varchar(50) DEFAULT NULL,
    `quantity` int(11) NOT NULL,
    `link` varchar(500) DEFAULT NULL,
    `username` varchar(100) DEFAULT NULL,
    `status` enum(
        'pending',
        'processing',
        'completed',
        'cancelled',
        'refunded'
    ) DEFAULT 'pending',
    `charge_lyd` decimal(10, 2) DEFAULT 0.00,
    `charge_usd` decimal(10, 2) DEFAULT 0.00,
    `start_count` int(11) DEFAULT NULL,
    `remains` int(11) DEFAULT NULL,
    `api_response` text DEFAULT NULL,
    `error_message` text DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_service_id` (`service_id`),
    KEY `idx_external_order_id` (`external_order_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 4. جدول المعاملات المالية (Transactions)
-- =====================================================
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `type` enum(
        'deposit',
        'withdrawal',
        'order_payment',
        'refund',
        'bonus',
        'penalty'
    ) NOT NULL,
    `amount_lyd` decimal(10, 2) DEFAULT 0.00,
    `amount_usd` decimal(10, 2) DEFAULT 0.00,
    `balance_before_lyd` decimal(10, 2) DEFAULT 0.00,
    `balance_after_lyd` decimal(10, 2) DEFAULT 0.00,
    `balance_before_usd` decimal(10, 2) DEFAULT 0.00,
    `balance_after_usd` decimal(10, 2) DEFAULT 0.00,
    `description` varchar(255) DEFAULT NULL,
    `reference_id` varchar(100) DEFAULT NULL,
    `status` enum('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 5. جدول الإشعارات (Notifications)
-- =====================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `type` enum('order_update', 'payment', 'system', 'promotion') NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `data` text DEFAULT NULL,
    `is_read` tinyint(1) DEFAULT 0,
    `is_important` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `read_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_is_read` (`is_read`),
    KEY `idx_created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 6. جدول لوحة المتصدرين الشهرية (Monthly Leaderboard)
-- =====================================================
DROP TABLE IF EXISTS `monthly_leaderboard`;
CREATE TABLE `monthly_leaderboard` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `month_year` varchar(7) NOT NULL,
    `total_spent_lyd` decimal(10, 2) DEFAULT 0.00,
    `total_spent_usd` decimal(10, 2) DEFAULT 0.00,
    `total_orders` int(11) DEFAULT 0,
    `rank` int(11) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_month` (`user_id`, `month_year`),
    KEY `idx_month_year` (`month_year`),
    KEY `idx_rank` (`rank`),
    KEY `idx_total_spent_lyd` (`total_spent_lyd`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 7. جدول إعدادات النظام (System Settings)
-- =====================================================
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `setting_type` enum('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `description` varchar(255) DEFAULT NULL,
    `is_public` tinyint(1) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_setting_key` (`setting_key`),
    KEY `idx_setting_key` (`setting_key`),
    KEY `idx_is_public` (`is_public`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 8. جدول سجل العمليات (Activity Log)
-- =====================================================
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `data` text DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 9. جدول المكافآت والعروض (Rewards & Promotions)
-- =====================================================
DROP TABLE IF EXISTS `rewards`;
CREATE TABLE `rewards` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `type` enum('bonus', 'discount', 'free_service', 'referral') NOT NULL,
    `amount_lyd` decimal(10, 2) DEFAULT 0.00,
    `amount_usd` decimal(10, 2) DEFAULT 0.00,
    `percentage` decimal(5, 2) DEFAULT NULL,
    `description` varchar(255) DEFAULT NULL,
    `is_used` tinyint(1) DEFAULT 0,
    `expires_at` datetime DEFAULT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_is_used` (`is_used`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- 10. جدول جلسات المستخدمين (User Sessions)
-- =====================================================
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_token` varchar(255) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_session_token` (`session_token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_session_token` (`session_token`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- =====================================================
-- إضافة المفاتيح الخارجية بعد إنشاء جميع الجداول
-- =====================================================
-- إضافة المفاتيح الخارجية للطلبات
ALTER TABLE `orders`
ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
ALTER TABLE `orders`
ADD CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services_cache`(`id`) ON DELETE CASCADE;
-- إضافة المفاتيح الخارجية للمعاملات
ALTER TABLE `transactions`
ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- إضافة المفاتيح الخارجية للإشعارات
ALTER TABLE `notifications`
ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- إضافة المفاتيح الخارجية للوحة المتصدرين
ALTER TABLE `monthly_leaderboard`
ADD CONSTRAINT `fk_leaderboard_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- إضافة المفاتيح الخارجية لسجل العمليات
ALTER TABLE `activity_log`
ADD CONSTRAINT `fk_activity_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE
SET NULL;
-- إضافة المفاتيح الخارجية للمكافآت
ALTER TABLE `rewards`
ADD CONSTRAINT `fk_rewards_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- إضافة المفاتيح الخارجية للجلسات
ALTER TABLE `user_sessions`
ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- إعادة تفعيل فحص المفاتيح الخارجية
SET FOREIGN_KEY_CHECKS = 1;
-- إعادة تفعيل SQL mode
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
-- =====================================================
-- إدراج البيانات الأساسية
-- =====================================================
-- إدراج إعدادات النظام الأساسية
INSERT IGNORE INTO `system_settings` (
        `setting_key`,
        `setting_value`,
        `setting_type`,
        `description`,
        `is_public`
    )
VALUES (
        'app_name',
        'GameBox — مركز أحمد للهاتف المحمول',
        'string',
        'اسم التطبيق',
        1
    ),
    (
        'app_version',
        '1.0.0',
        'string',
        'إصدار التطبيق',
        1
    ),
    (
        'maintenance_mode',
        '0',
        'boolean',
        'وضع الصيانة',
        0
    ),
    (
        'registration_enabled',
        '1',
        'boolean',
        'تفعيل التسجيل',
        1
    ),
    (
        'min_deposit_lyd',
        '10.00',
        'number',
        'الحد الأدنى للإيداع (دينار ليبي)',
        1
    ),
    (
        'min_deposit_usd',
        '1.00',
        'number',
        'الحد الأدنى للإيداع (دولار أمريكي)',
        1
    ),
    (
        'exchange_rate_usd_lyd',
        '12.00',
        'number',
        'سعر الصرف USD إلى LYD',
        1
    ),
    (
        'api_timeout',
        '30',
        'number',
        'مهلة API بالثواني',
        0
    ),
    (
        'max_orders_per_user',
        '100',
        'number',
        'الحد الأقصى للطلبات لكل مستخدم',
        0
    ),
    (
        'auto_sync_services',
        '1',
        'boolean',
        'مزامنة الخدمات تلقائياً',
        0
    ),
    (
        'sync_interval_hours',
        '6',
        'number',
        'فترة المزامنة بالساعات',
        0
    );
-- إدراج مستخدم إداري افتراضي (كلمة المرور: admin123)
INSERT IGNORE INTO `users` (
        `username`,
        `email`,
        `password_hash`,
        `name`,
        `balance_lyd`,
        `balance_usd`,
        `is_active`,
        `is_verified`
    )
VALUES (
        'admin',
        'admin@gamebox.ly',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'مدير النظام',
        1000.00,
        100.00,
        1,
        1
    );
-- =====================================================
-- إنشاء الفهارس الإضافية للأداء
-- =====================================================
-- فهارس مركبة للأداء
CREATE INDEX `idx_services_group_subcategory` ON `services_cache` (`group_slug`, `subcategory`);
CREATE INDEX `idx_services_active_visible` ON `services_cache` (`is_active`, `is_visible`);
CREATE INDEX `idx_orders_user_status` ON `orders` (`user_id`, `status`);
CREATE INDEX `idx_orders_created_status` ON `orders` (`created_at`, `status`);
CREATE INDEX `idx_transactions_user_type` ON `transactions` (`user_id`, `type`);
CREATE INDEX `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);
-- =====================================================
-- إنهاء الملف
-- =====================================================
-- تعليق نهائي
-- هذا الملف يحتوي على جميع الجداول والخانات المطلوبة لنظام GameBox
-- يمكن تشغيله مباشرة على MySQL/MariaDB
-- تم إزالة Views و Procedures و Triggers لتجنب مشاكل الصلاحيات
-- يعمل على جميع أنواع الاستضافة المشتركة

