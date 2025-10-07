<?php
require_once __DIR__ . '/../Utils/db.php';

class MonthlyLeaderboardService {
    
    /**
     * جلب أكبر المستخدمين إنفاقاً للشهر المحدد
     */
    public static function getTopSpenders($targetMonth = null, $limit = 10) {
        try {
            if ($targetMonth === null) {
                $targetMonth = date('Y-m');
            }
            
            // بناء نطاق الشهر
            $monthStart = date('Y-m-01 00:00:00', strtotime($targetMonth));
            $monthEnd = date('Y-m-01 00:00:00', strtotime("$targetMonth +1 month"));
            
            // جلب البيانات
            $sql = "SELECT 
                        wt.user_id,
                        u.name as user_name,
                        u.phone as user_phone,
                        SUM(ABS(wt.amount)) as spent
                    FROM wallet_transactions wt
                    JOIN users u ON wt.user_id = u.id
                    WHERE wt.type = 'deduct'
                        AND wt.status = 'approved'
                        AND wt.created_at >= ? 
                        AND wt.created_at < ?
                    GROUP BY wt.user_id
                    ORDER BY spent DESC
                    LIMIT ?";
            
            $results = Database::fetchAll($sql, [$monthStart, $monthEnd, $limit]);
            
            // تنسيق البيانات
            $leaderboard = [];
            foreach ($results as $row) {
                $leaderboard[] = [
                    'user_id' => (int)$row['user_id'],
                    'name_masked' => self::maskName($row['user_name']),
                    'phone_masked' => self::maskPhone($row['user_phone']),
                    'spent' => (float)$row['spent']
                ];
            }
            
            return $leaderboard;
            
        } catch (Exception $e) {
            error_log("MonthlyLeaderboardService error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * إخفاء الاسم
     */
    private static function maskName($name) {
        if (strlen($name) > 2) {
            return mb_substr($name, 0, 2) . ' م.';
        }
        return 'مستخدم';
    }
    
    /**
     * إخفاء رقم الهاتف
     */
    private static function maskPhone($phone) {
        if (strlen($phone) > 4) {
            return '***' . substr($phone, -4);
        }
        return '***';
    }
    
    /**
     * جلب نظام الجوائز
     */
    public static function getPrizes() {
        return [
            ['rank' => 1, 'amount_lyd' => 40],
            ['rank' => 2, 'amount_lyd' => 25],
            ['rank' => 3, 'amount_lyd' => 10],
            ['rank' => 4, 'amount_lyd' => 1],
            ['rank' => 5, 'amount_lyd' => 1],
            ['rank' => 6, 'amount_lyd' => 1],
            ['rank' => 7, 'amount_lyd' => 1]
        ];
    }
}
?>