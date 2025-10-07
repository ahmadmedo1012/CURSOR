<?php
require_once 'config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';

echo "<h2>إنشاء جداول المتصدرين</h2>";

try {
    // إنشاء جدول المحافظ
    echo "<h3>إنشاء جدول المحافظ...</h3>";
    $createWallets = "CREATE TABLE IF NOT EXISTS `wallets` (
        `user_id` INT PRIMARY KEY,
        `balance` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    Database::query($createWallets);
    echo "<p style='color: green;'>✅ تم إنشاء جدول المحافظ</p>";
    
    // إنشاء جدول معاملات المحفظة
    echo "<h3>إنشاء جدول معاملات المحفظة...</h3>";
    $createTransactions = "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `type` ENUM('topup','deduct') NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `operator` ENUM('libyana','madar') NULL,
        `reference` VARCHAR(160) NULL,
        `description` TEXT NULL,
        `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_wallet_user` (`user_id`),
        INDEX `idx_wallet_type` (`type`),
        INDEX `idx_wallet_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    Database::query($createTransactions);
    echo "<p style='color: green;'>✅ تم إنشاء جدول معاملات المحفظة</p>";
    
    // إضافة عمود user_id إلى جدول الطلبات
    echo "<h3>تحديث جدول الطلبات...</h3>";
    try {
        Database::query("ALTER TABLE `orders` ADD COLUMN `user_id` INT NULL");
        echo "<p style='color: green;'>✅ تم إضافة عمود user_id إلى جدول الطلبات</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ عمود user_id موجود بالفعل</p>";
    }
    
    try {
        Database::query("ALTER TABLE `orders` ADD INDEX `idx_orders_user` (`user_id`)");
        echo "<p style='color: green;'>✅ تم إضافة فهرس user_id</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ فهرس user_id موجود بالفعل</p>";
    }
    
    // إدراج بيانات تجريبية
    echo "<h3>إدراج البيانات التجريبية...</h3>";
    
    // إنشاء مستخدمين تجريبيين
    $users = [
        [1, '0912345678', 'أحمد محمد', '$2y$10$example_hash_1'],
        [2, '0923456789', 'فاطمة علي', '$2y$10$example_hash_2'],
        [3, '0934567890', 'محمد حسن', '$2y$10$example_hash_3'],
        [4, '0945678901', 'سارة أحمد', '$2y$10$example_hash_4'],
        [5, '0956789012', 'علي محمود', '$2y$10$example_hash_5']
    ];
    
    foreach ($users as $user) {
        try {
            Database::query("INSERT IGNORE INTO `users` (`id`, `phone`, `name`, `password_hash`) VALUES (?, ?, ?, ?)", $user);
        } catch (Exception $e) {
            // تجاهل الأخطاء إذا كان المستخدم موجود بالفعل
        }
    }
    echo "<p style='color: green;'>✅ تم إنشاء المستخدمين التجريبيين</p>";
    
    // إنشاء محافظ
    $wallets = [
        [1, 500.00],
        [2, 750.50],
        [3, 300.25],
        [4, 1200.75],
        [5, 850.00]
    ];
    
    foreach ($wallets as $wallet) {
        try {
            Database::query("INSERT IGNORE INTO `wallets` (`user_id`, `balance`) VALUES (?, ?)", $wallet);
        } catch (Exception $e) {
            // تجاهل الأخطاء إذا كانت المحفظة موجودة بالفعل
        }
    }
    echo "<p style='color: green;'>✅ تم إنشاء المحافظ</p>";
    
    // إنشاء معاملات للشهر الحالي
    $currentMonthTransactions = [
        // أحمد محمد (1250.50 LYD إجمالي)
        [1, 'deduct', 150.00, 'ORDER-001', 'طلب خدمات فيسبوك', 'approved'],
        [1, 'deduct', 200.50, 'ORDER-002', 'طلب خدمات تيليجرام', 'approved'],
        [1, 'deduct', 300.00, 'ORDER-003', 'طلب خدمات إنستغرام', 'approved'],
        [1, 'deduct', 400.00, 'ORDER-004', 'طلب خدمات تيك توك', 'approved'],
        [1, 'deduct', 200.00, 'ORDER-005', 'طلب خدمات يوتيوب', 'approved'],
        
        // فاطمة علي (980.25 LYD إجمالي)
        [2, 'deduct', 120.25, 'ORDER-006', 'طلب خدمات فيسبوك', 'approved'],
        [2, 'deduct', 180.00, 'ORDER-007', 'طلب خدمات تيليجرام', 'approved'],
        [2, 'deduct', 250.00, 'ORDER-008', 'طلب خدمات إنستغرام', 'approved'],
        [2, 'deduct', 200.00, 'ORDER-009', 'طلب خدمات تيك توك', 'approved'],
        [2, 'deduct', 230.00, 'ORDER-010', 'طلب خدمات يوتيوب', 'approved'],
        
        // محمد حسن (750.00 LYD إجمالي)
        [3, 'deduct', 100.00, 'ORDER-011', 'طلب خدمات فيسبوك', 'approved'],
        [3, 'deduct', 150.00, 'ORDER-012', 'طلب خدمات تيليجرام', 'approved'],
        [3, 'deduct', 200.00, 'ORDER-013', 'طلب خدمات إنستغرام', 'approved'],
        [3, 'deduct', 300.00, 'ORDER-014', 'طلب خدمات تيك توك', 'approved'],
        
        // سارة أحمد (650.75 LYD إجمالي)
        [4, 'deduct', 80.75, 'ORDER-015', 'طلب خدمات فيسبوك', 'approved'],
        [4, 'deduct', 120.00, 'ORDER-016', 'طلب خدمات تيليجرام', 'approved'],
        [4, 'deduct', 200.00, 'ORDER-017', 'طلب خدمات إنستغرام', 'approved'],
        [4, 'deduct', 250.00, 'ORDER-018', 'طلب خدمات تيك توك', 'approved'],
        
        // علي محمود (520.30 LYD إجمالي)
        [5, 'deduct', 70.30, 'ORDER-019', 'طلب خدمات فيسبوك', 'approved'],
        [5, 'deduct', 100.00, 'ORDER-020', 'طلب خدمات تيليجرام', 'approved'],
        [5, 'deduct', 150.00, 'ORDER-021', 'طلب خدمات إنستغرام', 'approved'],
        [5, 'deduct', 200.00, 'ORDER-022', 'طلب خدمات تيك توك', 'approved']
    ];
    
    foreach ($currentMonthTransactions as $transaction) {
        try {
            Database::query("INSERT IGNORE INTO `wallet_transactions` (`user_id`, `type`, `amount`, `reference`, `description`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))", 
                array_merge($transaction, [rand(1, 10)]));
        } catch (Exception $e) {
            // تجاهل الأخطاء إذا كانت المعاملة موجودة بالفعل
        }
    }
    echo "<p style='color: green;'>✅ تم إنشاء معاملات الشهر الحالي</p>";
    
    // إنشاء معاملات للشهر الماضي
    $prevMonthTransactions = [
        // خالد إبراهيم (2100.00 LYD إجمالي للشهر الماضي)
        [1, 'deduct', 500.00, 'ORDER-PREV-001', 'طلب خدمات فيسبوك - الشهر الماضي', 'approved'],
        [1, 'deduct', 600.00, 'ORDER-PREV-002', 'طلب خدمات تيليجرام - الشهر الماضي', 'approved'],
        [1, 'deduct', 500.00, 'ORDER-PREV-003', 'طلب خدمات إنستغرام - الشهر الماضي', 'approved'],
        [1, 'deduct', 500.00, 'ORDER-PREV-004', 'طلب خدمات تيك توك - الشهر الماضي', 'approved'],
        
        // فاطمة علي (1800.50 LYD إجمالي للشهر الماضي)
        [2, 'deduct', 400.50, 'ORDER-PREV-005', 'طلب خدمات فيسبوك - الشهر الماضي', 'approved'],
        [2, 'deduct', 500.00, 'ORDER-PREV-006', 'طلب خدمات تيليجرام - الشهر الماضي', 'approved'],
        [2, 'deduct', 400.00, 'ORDER-PREV-007', 'طلب خدمات إنستغرام - الشهر الماضي', 'approved'],
        [2, 'deduct', 500.00, 'ORDER-PREV-008', 'طلب خدمات تيك توك - الشهر الماضي', 'approved'],
        
        // محمد حسن (1500.25 LYD إجمالي للشهر الماضي)
        [3, 'deduct', 300.25, 'ORDER-PREV-009', 'طلب خدمات فيسبوك - الشهر الماضي', 'approved'],
        [3, 'deduct', 400.00, 'ORDER-PREV-010', 'طلب خدمات تيليجرام - الشهر الماضي', 'approved'],
        [3, 'deduct', 400.00, 'ORDER-PREV-011', 'طلب خدمات إنستغرام - الشهر الماضي', 'approved'],
        [3, 'deduct', 400.00, 'ORDER-PREV-012', 'طلب خدمات تيك توك - الشهر الماضي', 'approved'],
        
        // سارة أحمد (1200.75 LYD إجمالي للشهر الماضي)
        [4, 'deduct', 200.75, 'ORDER-PREV-013', 'طلب خدمات فيسبوك - الشهر الماضي', 'approved'],
        [4, 'deduct', 300.00, 'ORDER-PREV-014', 'طلب خدمات تيليجرام - الشهر الماضي', 'approved'],
        [4, 'deduct', 300.00, 'ORDER-PREV-015', 'طلب خدمات إنستغرام - الشهر الماضي', 'approved'],
        [4, 'deduct', 400.00, 'ORDER-PREV-016', 'طلب خدمات تيك توك - الشهر الماضي', 'approved'],
        
        // علي محمود (950.00 LYD إجمالي للشهر الماضي)
        [5, 'deduct', 150.00, 'ORDER-PREV-017', 'طلب خدمات فيسبوك - الشهر الماضي', 'approved'],
        [5, 'deduct', 200.00, 'ORDER-PREV-018', 'طلب خدمات تيليجرام - الشهر الماضي', 'approved'],
        [5, 'deduct', 300.00, 'ORDER-PREV-019', 'طلب خدمات إنستغرام - الشهر الماضي', 'approved'],
        [5, 'deduct', 300.00, 'ORDER-PREV-020', 'طلب خدمات تيك توك - الشهر الماضي', 'approved']
    ];
    
    foreach ($prevMonthTransactions as $transaction) {
        try {
            Database::query("INSERT IGNORE INTO `wallet_transactions` (`user_id`, `type`, `amount`, `reference`, `description`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))", 
                array_merge($transaction, [rand(30, 40)]));
        } catch (Exception $e) {
            // تجاهل الأخطاء إذا كانت المعاملة موجودة بالفعل
        }
    }
    echo "<p style='color: green;'>✅ تم إنشاء معاملات الشهر الماضي</p>";
    
    // فحص النتائج
    echo "<h3>فحص النتائج:</h3>";
    
    $usersCount = Database::fetchOne("SELECT COUNT(*) as count FROM users");
    $transactionsCount = Database::fetchOne("SELECT COUNT(*) as count FROM wallet_transactions");
    
    echo "<p>👥 عدد المستخدمين: " . $usersCount['count'] . "</p>";
    echo "<p>💰 عدد المعاملات: " . $transactionsCount['count'] . "</p>";
    
    echo "<h3 style='color: green;'>✅ تم إنشاء جداول المتصدرين بنجاح!</h3>";
    echo "<p><a href='leaderboard.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>انتقل إلى صفحة المتصدرين</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>خطأ: " . $e->getMessage() . "</p>";
}
?>
