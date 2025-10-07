-- Database Schema Export
-- Generated on: 2024-12-19 15:30:00
-- Target: MariaDB 10.6+ (Shared Hosting Compatible)
-- Safe/Idempotent: Uses IF NOT EXISTS and IF EXISTS clauses
-- ==============================================
-- TABLES
-- ==============================================
-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `phone` varchar(30) NOT NULL,
    `name` varchar(120) DEFAULT NULL,
    `password_hash` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `phone` (`phone`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: sessions
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_token` varchar(64) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_token` (`session_token`),
    KEY `idx_sessions_user` (`user_id`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: service_groups
CREATE TABLE IF NOT EXISTS `service_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `slug` varchar(60) DEFAULT NULL,
    `title` varchar(120) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: services_cache
CREATE TABLE IF NOT EXISTS `services_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `external_id` varchar(50) NOT NULL,
    `name` varchar(255) NOT NULL,
    `name_ar` varchar(255) DEFAULT NULL,
    `category` varchar(160) DEFAULT NULL,
    `category_ar` varchar(160) DEFAULT NULL,
    `rate_per_1k` decimal(12, 4) DEFAULT 0.0000,
    `rate_per_1k_usd` decimal(12, 4) DEFAULT NULL,
    `rate_per_1k_lyd` decimal(12, 4) DEFAULT NULL,
    `min` int(11) DEFAULT 0,
    `max` int(11) DEFAULT 0,
    `type` varchar(60) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `description_ar` text DEFAULT NULL,
    `subcategory` varchar(100) DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `orders_count` int(11) DEFAULT 0,
    `group_slug` varchar(60) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_services_name` (`name`),
    KEY `idx_services_category` (`category`),
    KEY `idx_services_external` (`external_id`),
    KEY `idx_group_slug` (`group_slug`),
    KEY `idx_services_subcategory` (`subcategory`),
    KEY `idx_services_sort_order` (`sort_order`),
    KEY `idx_services_orders_count` (`orders_count`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: service_translations
CREATE TABLE IF NOT EXISTS `service_translations` (
    `service_id` int(11) NOT NULL,
    `name_ar` varchar(255) DEFAULT NULL,
    `category_ar` varchar(160) DEFAULT NULL,
    `description_ar` text DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`service_id`),
    CONSTRAINT `fk_service_translations_service` FOREIGN KEY (`service_id`) REFERENCES `services_cache` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `external_order_id` varchar(64) DEFAULT NULL,
    `service_id` int(11) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `quantity` int(11) NOT NULL,
    `link` varchar(500) DEFAULT NULL,
    `username` varchar(160) DEFAULT NULL,
    `status` varchar(50) DEFAULT 'pending',
    `price_lyd` decimal(12, 2) DEFAULT 0.00,
    `notes` text DEFAULT NULL,
    `provider` varchar(50) DEFAULT 'peakerr',
    `external_id` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orders_external` (`external_order_id`),
    KEY `idx_orders_service` (`service_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_user` (`user_id`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: wallets
CREATE TABLE IF NOT EXISTS `wallets` (
    `user_id` int(11) NOT NULL,
    `balance` decimal(12, 2) NOT NULL DEFAULT 0.00,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: wallet_transactions
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `type` enum('topup', 'deduct', 'credit') NOT NULL,
    `amount` decimal(12, 2) NOT NULL,
    `operator` enum('libyana', 'madar') DEFAULT NULL,
    `reference` varchar(160) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wallet_user` (`user_id`),
    KEY `idx_wallet_reference` (`reference`),
    KEY `idx_wallet_type` (`type`),
    KEY `idx_wallet_status` (`status`),
    CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: providers
CREATE TABLE IF NOT EXISTS `providers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `api_url` varchar(255) NOT NULL,
    `api_key` varchar(255) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `priority` int(11) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    KEY `idx_providers_active` (`is_active`),
    KEY `idx_providers_priority` (`priority`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: provider_stats
CREATE TABLE IF NOT EXISTS `provider_stats` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `provider` varchar(50) NOT NULL,
    `total_orders` int(11) DEFAULT 0,
    `successful_orders` int(11) DEFAULT 0,
    `failed_orders` int(11) DEFAULT 0,
    `total_revenue` decimal(10, 2) DEFAULT 0.00,
    `last_sync` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_provider` (`provider`),
    KEY `idx_provider_stats_provider` (`provider`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `type` enum('info', 'success', 'warning', 'error', 'promotion') DEFAULT 'info',
    `priority` enum('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `target_audience` enum('all', 'logged_in', 'guests') DEFAULT 'all',
    `start_date` datetime DEFAULT CURRENT_TIMESTAMP,
    `end_date` datetime DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `show_on_pages` json DEFAULT NULL,
    `dismissible` tinyint(1) DEFAULT 1,
    `auto_dismiss_after` int(11) DEFAULT 0,
    `click_action` varchar(255) DEFAULT NULL,
    `background_color` varchar(7) DEFAULT NULL,
    `text_color` varchar(7) DEFAULT NULL,
    `icon` varchar(100) DEFAULT NULL,
    `created_by` varchar(100) DEFAULT 'admin',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_dates` (`start_date`, `end_date`),
    KEY `idx_type` (`type`),
    KEY `idx_priority` (`priority`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: notification_views
CREATE TABLE IF NOT EXISTS `notification_views` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `notification_id` int(11) NOT NULL,
    `user_ip` varchar(45) NOT NULL,
    `user_agent` text DEFAULT NULL,
    `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dismissed` tinyint(1) DEFAULT 0,
    `dismissed_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_notification` (`notification_id`, `user_ip`),
    KEY `idx_notification` (`notification_id`),
    KEY `idx_user_ip` (`user_ip`),
    KEY `idx_viewed_at` (`viewed_at`),
    CONSTRAINT `fk_notification_views_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- Table: notification_stats
CREATE TABLE IF NOT EXISTS `notification_stats` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `notification_id` int(11) NOT NULL,
    `total_views` int(11) DEFAULT 0,
    `total_dismissals` int(11) DEFAULT 0,
    `unique_views` int(11) DEFAULT 0,
    `last_viewed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_notification_stats` (`notification_id`),
    CONSTRAINT `fk_notification_stats_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
-- ==============================================
-- INDEXES (Additional indexes not in CREATE TABLE)
-- ==============================================
-- Additional indexes for wallet_transactions
ALTER TABLE `wallet_transactions`
ADD INDEX IF NOT EXISTS `idx_wallet_reference` (`reference`);
ALTER TABLE `wallet_transactions`
ADD INDEX IF NOT EXISTS `idx_wallet_type` (`type`);
ALTER TABLE `wallet_transactions`
ADD INDEX IF NOT EXISTS `idx_wallet_status` (`status`);
-- Additional indexes for providers
ALTER TABLE `providers`
ADD INDEX IF NOT EXISTS `idx_providers_active` (`is_active`);
ALTER TABLE `providers`
ADD INDEX IF NOT EXISTS `idx_providers_priority` (`priority`);
-- Additional indexes for provider_stats
ALTER TABLE `provider_stats`
ADD INDEX IF NOT EXISTS `idx_provider_stats_provider` (`provider`);
-- ==============================================
-- VIEWS
-- ==============================================
-- No views found in the current schema
-- ==============================================
-- TRIGGERS
-- ==============================================
-- No triggers found in the current schema
-- ==============================================
-- PROCEDURES
-- ==============================================
-- No stored procedures found in the current schema
-- ==============================================
-- FUNCTIONS
-- ==============================================
-- No stored functions found in the current schema
-- ==============================================
-- SAMPLE DATA (Optional - Comment out if not needed)
-- ==============================================
-- Insert default providers
INSERT IGNORE INTO `providers` (
        `name`,
        `display_name`,
        `api_url`,
        `api_key`,
        `priority`
    )
VALUES (
        'peakerr',
        'Peakerr',
        'https://peakerr.com/api/',
        'YOUR_PEAKERR_KEY',
        1
    ),
    (
        'newprovider',
        'New Provider',
        'https://newprovider.com/api/',
        'YOUR_NEW_KEY',
        2
    );
-- Insert default provider stats
INSERT IGNORE INTO `provider_stats` (
        `provider`,
        `total_orders`,
        `successful_orders`,
        `failed_orders`,
        `total_revenue`
    )
VALUES ('peakerr', 0, 0, 0, 0.00),
    ('newprovider', 0, 0, 0, 0.00);
-- Insert sample notifications
INSERT IGNORE INTO `notifications` (
        `id`,
        `title`,
        `message`,
        `type`,
        `priority`,
        `target_audience`,
        `is_active`,
        `dismissible`,
        `auto_dismiss_after`
    )
VALUES (
        1,
        'مرحباً بك في GameBox!',
        'نحن سعداء لوجودك معنا. استمتع بخدماتنا المتميزة وأسعارنا التنافسية.',
        'success',
        'normal',
        'all',
        1,
        1,
        10
    ),
    (
        2,
        'عرض خاص!',
        'احصل على خصم 20% على جميع خدمات تيك توك لفترة محدودة.',
        'promotion',
        'high',
        'all',
        0,
        1,
        15
    );
-- Insert notification stats for sample notifications
INSERT IGNORE INTO `notification_stats` (`notification_id`, `total_views`, `unique_views`)
SELECT n.id,
    0,
    0
FROM notifications n
WHERE n.id IN (1, 2)
    AND NOT EXISTS (
        SELECT 1
        FROM notification_stats ns
        WHERE ns.notification_id = n.id
    );
-- ==============================================
-- END OF SCHEMA EXPORT
-- ==============================================

