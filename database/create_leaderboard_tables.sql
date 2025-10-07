-- إنشاء جداول المتصدرين والإنفاق
-- هذا الملف ينشئ الجداول المطلوبة لصفحة المتصدرين
-- إنشاء جدول المحافظ إذا لم يكن موجوداً
CREATE TABLE IF NOT EXISTS `wallets` (
    `user_id` INT PRIMARY KEY,
    `balance` DECIMAL(12, 2) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- إنشاء جدول معاملات المحفظة إذا لم يكن موجوداً
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('topup', 'deduct') NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `operator` ENUM('libyana', 'madar') NULL,
    `reference` VARCHAR(160) NULL,
    `description` TEXT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_wallet_user` (`user_id`),
    INDEX `idx_wallet_type` (`type`),
    INDEX `idx_wallet_created` (`created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- إضافة عمود user_id إلى جدول الطلبات إذا لم يكن موجوداً
ALTER TABLE `orders`
ADD COLUMN IF NOT EXISTS `user_id` INT NULL,
    ADD INDEX IF NOT EXISTS `idx_orders_user` (`user_id`);
-- إضافة المفتاح الخارجي إذا لم يكن موجوداً
-- (سيتم تجاهل الخطأ إذا كان موجوداً بالفعل)
SET @sql = 'ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL';
SET @sql = CONCAT(
        @sql,
        ' ON DUPLICATE KEY UPDATE user_id = user_id'
    );
PREPARE stmt
FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- إدراج بيانات تجريبية للاختبار (اختياري)
-- يمكن حذف هذا القسم إذا كنت لا تريد بيانات تجريبية
-- إنشاء مستخدمين تجريبيين إذا لم يكونوا موجودين
INSERT IGNORE INTO `users` (`id`, `phone`, `name`, `password_hash`)
VALUES (
        1,
        '0912345678',
        'أحمد محمد',
        '$2y$10$example_hash_1'
    ),
    (
        2,
        '0923456789',
        'فاطمة علي',
        '$2y$10$example_hash_2'
    ),
    (
        3,
        '0934567890',
        'محمد حسن',
        '$2y$10$example_hash_3'
    ),
    (
        4,
        '0945678901',
        'سارة أحمد',
        '$2y$10$example_hash_4'
    ),
    (
        5,
        '0956789012',
        'علي محمود',
        '$2y$10$example_hash_5'
    );
-- إنشاء محافظ للمستخدمين التجريبيين
INSERT IGNORE INTO `wallets` (`user_id`, `balance`)
VALUES (1, 500.00),
    (2, 750.50),
    (3, 300.25),
    (4, 1200.75),
    (5, 850.00);
-- إنشاء معاملات محفظة تجريبية للشهر الحالي
INSERT IGNORE INTO `wallet_transactions` (
        `user_id`,
        `type`,
        `amount`,
        `reference`,
        `description`,
        `status`,
        `created_at`
    )
VALUES -- أحمد محمد (1250.50 LYD إجمالي)
    (
        1,
        'deduct',
        150.00,
        'ORDER-001',
        'طلب خدمات فيسبوك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 5 DAY)
    ),
    (
        1,
        'deduct',
        200.50,
        'ORDER-002',
        'طلب خدمات تيليجرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 4 DAY)
    ),
    (
        1,
        'deduct',
        300.00,
        'ORDER-003',
        'طلب خدمات إنستغرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 3 DAY)
    ),
    (
        1,
        'deduct',
        400.00,
        'ORDER-004',
        'طلب خدمات تيك توك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 2 DAY)
    ),
    (
        1,
        'deduct',
        200.00,
        'ORDER-005',
        'طلب خدمات يوتيوب',
        'approved',
        DATE_SUB(NOW(), INTERVAL 1 DAY)
    ),
    -- فاطمة علي (980.25 LYD إجمالي)
    (
        2,
        'deduct',
        120.25,
        'ORDER-006',
        'طلب خدمات فيسبوك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 6 DAY)
    ),
    (
        2,
        'deduct',
        180.00,
        'ORDER-007',
        'طلب خدمات تيليجرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 5 DAY)
    ),
    (
        2,
        'deduct',
        250.00,
        'ORDER-008',
        'طلب خدمات إنستغرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 4 DAY)
    ),
    (
        2,
        'deduct',
        200.00,
        'ORDER-009',
        'طلب خدمات تيك توك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 3 DAY)
    ),
    (
        2,
        'deduct',
        230.00,
        'ORDER-010',
        'طلب خدمات يوتيوب',
        'approved',
        DATE_SUB(NOW(), INTERVAL 2 DAY)
    ),
    -- محمد حسن (750.00 LYD إجمالي)
    (
        3,
        'deduct',
        100.00,
        'ORDER-011',
        'طلب خدمات فيسبوك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 7 DAY)
    ),
    (
        3,
        'deduct',
        150.00,
        'ORDER-012',
        'طلب خدمات تيليجرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 6 DAY)
    ),
    (
        3,
        'deduct',
        200.00,
        'ORDER-013',
        'طلب خدمات إنستغرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 5 DAY)
    ),
    (
        3,
        'deduct',
        300.00,
        'ORDER-014',
        'طلب خدمات تيك توك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 4 DAY)
    ),
    -- سارة أحمد (650.75 LYD إجمالي)
    (
        4,
        'deduct',
        80.75,
        'ORDER-015',
        'طلب خدمات فيسبوك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 8 DAY)
    ),
    (
        4,
        'deduct',
        120.00,
        'ORDER-016',
        'طلب خدمات تيليجرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 7 DAY)
    ),
    (
        4,
        'deduct',
        200.00,
        'ORDER-017',
        'طلب خدمات إنستغرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 6 DAY)
    ),
    (
        4,
        'deduct',
        250.00,
        'ORDER-018',
        'طلب خدمات تيك توك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 5 DAY)
    ),
    -- علي محمود (520.30 LYD إجمالي)
    (
        5,
        'deduct',
        70.30,
        'ORDER-019',
        'طلب خدمات فيسبوك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 9 DAY)
    ),
    (
        5,
        'deduct',
        100.00,
        'ORDER-020',
        'طلب خدمات تيليجرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 8 DAY)
    ),
    (
        5,
        'deduct',
        150.00,
        'ORDER-021',
        'طلب خدمات إنستغرام',
        'approved',
        DATE_SUB(NOW(), INTERVAL 7 DAY)
    ),
    (
        5,
        'deduct',
        200.00,
        'ORDER-022',
        'طلب خدمات تيك توك',
        'approved',
        DATE_SUB(NOW(), INTERVAL 6 DAY)
    );
-- إنشاء معاملات للشهر الماضي (للعرض في تبويب "فائزون الشهر الماضي")
INSERT IGNORE INTO `wallet_transactions` (
        `user_id`,
        `type`,
        `amount`,
        `reference`,
        `description`,
        `status`,
        `created_at`
    )
VALUES -- خالد إبراهيم (2100.00 LYD إجمالي للشهر الماضي)
    (
        1,
        'deduct',
        500.00,
        'ORDER-PREV-001',
        'طلب خدمات فيسبوك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 35 DAY)
    ),
    (
        1,
        'deduct',
        600.00,
        'ORDER-PREV-002',
        'طلب خدمات تيليجرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 34 DAY)
    ),
    (
        1,
        'deduct',
        500.00,
        'ORDER-PREV-003',
        'طلب خدمات إنستغرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 33 DAY)
    ),
    (
        1,
        'deduct',
        500.00,
        'ORDER-PREV-004',
        'طلب خدمات تيك توك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 32 DAY)
    ),
    -- فاطمة علي (1800.50 LYD إجمالي للشهر الماضي)
    (
        2,
        'deduct',
        400.50,
        'ORDER-PREV-005',
        'طلب خدمات فيسبوك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 36 DAY)
    ),
    (
        2,
        'deduct',
        500.00,
        'ORDER-PREV-006',
        'طلب خدمات تيليجرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 35 DAY)
    ),
    (
        2,
        'deduct',
        400.00,
        'ORDER-PREV-007',
        'طلب خدمات إنستغرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 34 DAY)
    ),
    (
        2,
        'deduct',
        500.00,
        'ORDER-PREV-008',
        'طلب خدمات تيك توك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 33 DAY)
    ),
    -- محمد حسن (1500.25 LYD إجمالي للشهر الماضي)
    (
        3,
        'deduct',
        300.25,
        'ORDER-PREV-009',
        'طلب خدمات فيسبوك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 37 DAY)
    ),
    (
        3,
        'deduct',
        400.00,
        'ORDER-PREV-010',
        'طلب خدمات تيليجرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 36 DAY)
    ),
    (
        3,
        'deduct',
        400.00,
        'ORDER-PREV-011',
        'طلب خدمات إنستغرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 35 DAY)
    ),
    (
        3,
        'deduct',
        400.00,
        'ORDER-PREV-012',
        'طلب خدمات تيك توك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 34 DAY)
    ),
    -- سارة أحمد (1200.75 LYD إجمالي للشهر الماضي)
    (
        4,
        'deduct',
        200.75,
        'ORDER-PREV-013',
        'طلب خدمات فيسبوك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 38 DAY)
    ),
    (
        4,
        'deduct',
        300.00,
        'ORDER-PREV-014',
        'طلب خدمات تيليجرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 37 DAY)
    ),
    (
        4,
        'deduct',
        300.00,
        'ORDER-PREV-015',
        'طلب خدمات إنستغرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 36 DAY)
    ),
    (
        4,
        'deduct',
        400.00,
        'ORDER-PREV-016',
        'طلب خدمات تيك توك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 35 DAY)
    ),
    -- علي محمود (950.00 LYD إجمالي للشهر الماضي)
    (
        5,
        'deduct',
        150.00,
        'ORDER-PREV-017',
        'طلب خدمات فيسبوك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 39 DAY)
    ),
    (
        5,
        'deduct',
        200.00,
        'ORDER-PREV-018',
        'طلب خدمات تيليجرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 38 DAY)
    ),
    (
        5,
        'deduct',
        300.00,
        'ORDER-PREV-019',
        'طلب خدمات إنستغرام - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 37 DAY)
    ),
    (
        5,
        'deduct',
        300.00,
        'ORDER-PREV-020',
        'طلب خدمات تيك توك - الشهر الماضي',
        'approved',
        DATE_SUB(NOW(), INTERVAL 36 DAY)
    );
-- رسالة نجاح
SELECT 'تم إنشاء جداول المتصدرين بنجاح!' as message;

