-- إنشاء جدول خدمات المخزنة مؤقتاً
CREATE TABLE IF NOT EXISTS `services_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `external_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(160) DEFAULT NULL,
  `rate_per_1k` decimal(12,4) DEFAULT 0.0000,
  `min` int(11) DEFAULT 0,
  `max` int(11) DEFAULT 0,
  `type` varchar(60) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_services_name` (`name`),
  KEY `idx_services_category` (`category`),
  KEY `idx_services_external` (`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء جدول الطلبات
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `external_order_id` varchar(64) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `username` varchar(160) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `price_lyd` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_external` (`external_order_id`),
  KEY `idx_orders_service` (`service_id`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
