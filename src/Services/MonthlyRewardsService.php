<?php
require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/logger.php';

class MonthlyRewardsService {
    
    /**
     * جدول المكافآت حسب الترتيب
     */
    private static $rewardTable = [
        1 => 40,  // المركز الأول: 40 LYD
        2 => 25,  // المركز الثاني: 25 LYD
        3 => 10,  // المركز الثالث: 10 LYD
        4 => 1,   // المركز الرابع: 1 LYD
        5 => 1,   // المركز الخامس: 1 LYD
        6 => 1,   // المركز السادس: 1 LYD
        7 => 1,   // المركز السابع: 1 LYD
        // المراكز 8-10 لا يحصلون على مكافآت
    ];
    
    /**
     * تنفيذ جوائز الشهر الماضي
     */
    public static function processPreviousMonthRewards($adminUserId = null) {
        try {
            // بدء المعاملة
            Database::query("START TRANSACTION");
            
            // حساب نطاق الشهر الماضي
            $prevMonth = date('Y-m', strtotime('first day of last month'));
            $monthStart = date('Y-m-01 00:00:00', strtotime($prevMonth));
            $monthEnd = date('Y-m-01 00:00:00', strtotime("$prevMonth +1 month"));
            
            // جلب أفضل 10 مستخدمين للشهر الماضي
            $leaderboard = self::getPreviousMonthLeaderboard($monthStart, $monthEnd);
            
            if (empty($leaderboard)) {
                Database::query("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'لا توجد بيانات للشهر الماضي',
                    'processed' => 0,
                    'total_amount' => 0
                ];
            }
            
            $processedUsers = [];
            $totalAmount = 0;
            $skippedUsers = [];
            
            // معالجة كل مستخدم
            foreach ($leaderboard as $index => $user) {
                $rank = $index + 1;
                $userId = $user['user_id'];
                $reward = self::$rewardTable[$rank] ?? 0;
                
                // تخطي المستخدمين بدون مكافأة
                if ($reward <= 0) {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'user_name' => $user['user_name'],
                        'rank' => $rank,
                        'reason' => 'لا توجد مكافأة لهذا الترتيب'
                    ];
                    continue;
                }
                
                // إنشاء المرجع الفريد
                $reference = sprintf('TOP-%s-R%02d-U%d', $prevMonth, $rank, $userId);
                
                // التحقق من عدم تكرار المكافأة
                if (self::isRewardAlreadyGiven($userId, $reference)) {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'user_name' => $user['user_name'],
                        'rank' => $rank,
                        'reason' => 'تم منح المكافأة مسبقاً'
                    ];
                    continue;
                }
                
                // إضافة المكافأة
                $description = sprintf('جائزة أفضل المستخدمين %s — المركز %d', $prevMonth, $rank);
                
                $result = self::addRewardTransaction($userId, $reward, $reference, $description);
                
                if ($result) {
                    $processedUsers[] = [
                        'user_id' => $userId,
                        'user_name' => $user['user_name'],
                        'rank' => $rank,
                        'amount' => $reward,
                        'reference' => $reference
                    ];
                    $totalAmount += $reward;
                } else {
                    $skippedUsers[] = [
                        'user_id' => $userId,
                        'user_name' => $user['user_name'],
                        'rank' => $rank,
                        'reason' => 'فشل في إضافة المكافأة'
                    ];
                }
            }
            
            // تأكيد المعاملة
            Database::query("COMMIT");
            
            // تسجيل العملية
            self::logRewardProcess($prevMonth, $processedUsers, $skippedUsers, $totalAmount, $adminUserId);
            
            return [
                'success' => true,
                'message' => sprintf('تم معالجة %d مستخدم بنجاح', count($processedUsers)),
                'processed' => count($processedUsers),
                'total_amount' => $totalAmount,
                'processed_users' => $processedUsers,
                'skipped_users' => $skippedUsers,
                'month' => $prevMonth
            ];
            
        } catch (Exception $e) {
            // إلغاء المعاملة في حالة الخطأ
            Database::query("ROLLBACK");
            
            Logger::error('خطأ في معالجة جوائز الشهر الماضي', [
                'error' => $e->getMessage(),
                'admin_user' => $adminUserId
            ]);
            
            return [
                'success' => false,
                'message' => 'حدث خطأ في معالجة الجوائز: ' . $e->getMessage(),
                'processed' => 0,
                'total_amount' => 0
            ];
        }
    }
    
    /**
     * جلب أفضل 10 مستخدمين للشهر الماضي
     */
    private static function getPreviousMonthLeaderboard($monthStart, $monthEnd) {
        try {
            // تحديد جدول المحفظة
            $walletTable = self::getWalletTable();
            $spentPredicate = self::buildSpentPredicate($walletTable);
            
            $sql = "SELECT 
                        wt.user_id, 
                        SUM(ABS(wt.amount)) AS spent,
                        COUNT(*) AS transaction_count,
                        u.name as user_name,
                        u.phone as user_phone
                    FROM `{$walletTable}` wt
                    LEFT JOIN users u ON wt.user_id = u.id
                    WHERE {$spentPredicate}
                        AND wt.created_at >= ? 
                        AND wt.created_at < ?
                    GROUP BY wt.user_id
                    ORDER BY spent DESC
                    LIMIT 10";
            
            $results = Database::fetchAll($sql, [$monthStart, $monthEnd]);
            
            return $results;
            
        } catch (Exception $e) {
            Logger::error('خطأ في جلب لوحة المتصدرين للشهر الماضي', [
                'error' => $e->getMessage(),
                'month_start' => $monthStart,
                'month_end' => $monthEnd
            ]);
            return [];
        }
    }
    
    /**
     * تحديد جدول المحفظة المناسب
     */
    private static function getWalletTable() {
        try {
            $result = Database::fetchOne("SHOW TABLES LIKE 'wallet_transactions'");
            if ($result) {
                return 'wallet_transactions';
            }
        } catch (Exception $e) {
            // تجاهل الخطأ
        }
        
        return 'wallet_transactions'; // افتراضي
    }
    
    /**
     * بناء شرط المبلغ المنفق
     */
    private static function buildSpentPredicate($table) {
        try {
            $result = Database::fetchOne("SHOW COLUMNS FROM `{$table}` LIKE 'type'");
            if ($result) {
                return "type IN ('deduct', 'purchase', 'order', 'debit')";
            } else {
                return "amount < 0";
            }
        } catch (Exception $e) {
            return "amount < 0"; // افتراضي
        }
    }
    
    /**
     * التحقق من عدم تكرار المكافأة
     */
    private static function isRewardAlreadyGiven($userId, $reference) {
        try {
            $result = Database::fetchOne(
                "SELECT 1 FROM wallet_transactions WHERE user_id = ? AND reference = ? LIMIT 1",
                [$userId, $reference]
            );
            return (bool)$result;
        } catch (Exception $e) {
            Logger::error('خطأ في التحقق من تكرار المكافأة', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'reference' => $reference
            ]);
            return true; // في حالة الشك، لا نمنح المكافأة
        }
    }
    
    /**
     * إضافة معاملة المكافأة
     */
    private static function addRewardTransaction($userId, $amount, $reference, $description) {
        try {
            $sql = "INSERT INTO wallet_transactions (user_id, amount, type, reference, description, created_at)
                    VALUES (?, ?, 'credit', ?, ?, NOW())";
            
            Database::query($sql, [$userId, $amount, $reference, $description]);
            
            // تحديث رصيد المحفظة
            self::updateWalletBalance($userId, $amount);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('خطأ في إضافة معاملة المكافأة', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount,
                'reference' => $reference
            ]);
            return false;
        }
    }
    
    /**
     * تحديث رصيد المحفظة
     */
    private static function updateWalletBalance($userId, $amount) {
        try {
            // التحقق من وجود المحفظة
            $wallet = Database::fetchOne("SELECT * FROM wallets WHERE user_id = ?", [$userId]);
            
            if ($wallet) {
                // تحديث الرصيد الموجود
                Database::query(
                    "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?",
                    [$amount, $userId]
                );
            } else {
                // إنشاء محفظة جديدة
                Database::query(
                    "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW())",
                    [$userId, $amount]
                );
            }
            
        } catch (Exception $e) {
            Logger::error('خطأ في تحديث رصيد المحفظة', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount
            ]);
        }
    }
    
    /**
     * تسجيل عملية المكافآت
     */
    private static function logRewardProcess($month, $processedUsers, $skippedUsers, $totalAmount, $adminUserId) {
        try {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'month' => $month,
                'admin_user' => $adminUserId,
                'processed_count' => count($processedUsers),
                'skipped_count' => count($skippedUsers),
                'total_amount' => $totalAmount,
                'processed_users' => $processedUsers,
                'skipped_users' => $skippedUsers
            ];
            
            $logMessage = sprintf(
                "[%s] جوائز الشهر الماضي %s - تم معالجة %d مستخدم، تخطي %d مستخدم، إجمالي المبلغ: %.2f LYD",
                $logEntry['timestamp'],
                $month,
                count($processedUsers),
                count($skippedUsers),
                $totalAmount
            );
            
            Logger::info($logMessage, $logEntry);
            
        } catch (Exception $e) {
            // لا نريد أن يفشل التسجيل في إيقاف العملية
            error_log("خطأ في تسجيل عملية المكافآت: " . $e->getMessage());
        }
    }
    
    /**
     * جلب تاريخ آخر تنفيذ للمكافآت
     */
    public static function getLastRewardExecution() {
        try {
            $result = Database::fetchOne(
                "SELECT MAX(created_at) as last_execution 
                 FROM wallet_transactions 
                 WHERE reference LIKE 'TOP-%' 
                 ORDER BY created_at DESC 
                 LIMIT 1"
            );
            
            return $result ? $result['last_execution'] : null;
            
        } catch (Exception $e) {
            Logger::error('خطأ في جلب تاريخ آخر تنفيذ للمكافآت', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * التحقق من إمكانية تنفيذ المكافآت
     */
    public static function canExecuteRewards() {
        try {
            $prevMonth = date('Y-m', strtotime('first day of last month'));
            
            // التحقق من وجود مكافآت للشهر الماضي
            $result = Database::fetchOne(
                "SELECT 1 FROM wallet_transactions 
                 WHERE reference LIKE ? 
                 LIMIT 1",
                ["TOP-{$prevMonth}-%"]
            );
            
            return !$result; // يمكن التنفيذ إذا لم توجد مكافآت مسبقاً
            
        } catch (Exception $e) {
            Logger::error('خطأ في التحقق من إمكانية تنفيذ المكافآت', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * جلب جدول المكافآت
     */
    public static function getRewardTable() {
        return self::$rewardTable;
    }
}
?>

