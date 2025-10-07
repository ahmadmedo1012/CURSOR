<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class StatsService {
    
    /**
     * جلب إحصائيات عامة للموقع
     */
    public static function getGeneralStats() {
        try {
            $stats = [];
            
            // إحصائيات المستخدمين
            $stats['users'] = [
                'total' => Database::fetchOne("SELECT COUNT(*) as count FROM users")['count'],
                'active_today' => Database::fetchOne("SELECT COUNT(*) as count FROM users WHERE last_login >= CURDATE()")['count'],
                'new_this_month' => Database::fetchOne("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")['count']
            ];
            
            // إحصائيات الطلبات
            $stats['orders'] = [
                'total' => Database::fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
                'pending' => Database::fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'],
                'completed' => Database::fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'completed'")['count'],
                'failed' => Database::fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'failed'")['count'],
                'today' => Database::fetchOne("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'],
                'this_month' => Database::fetchOne("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")['count']
            ];
            
            // إحصائيات الخدمات
            $stats['services'] = [
                'total' => Database::fetchOne("SELECT COUNT(*) as count FROM services_cache")['count'],
                'active' => Database::fetchOne("SELECT COUNT(*) as count FROM services_cache WHERE rate_per_1k_lyd > 0")['count'],
                'translated' => Database::fetchOne("SELECT COUNT(*) as count FROM services_cache WHERE name_ar IS NOT NULL AND name_ar != ''")['count']
            ];
            
            // إحصائيات المحافظ
            $stats['wallets'] = [
                'total_balance' => Database::fetchOne("SELECT COALESCE(SUM(balance), 0) as total FROM wallets")['total'],
                'total_users' => Database::fetchOne("SELECT COUNT(*) as count FROM wallets WHERE balance > 0")['count'],
                'pending_topups' => Database::fetchOne("SELECT COUNT(*) as count FROM wallet_transactions WHERE type = 'topup' AND status = 'pending'")['count']
            ];
            
            // إحصائيات الإشعارات
            $stats['notifications'] = [
                'total' => Database::fetchOne("SELECT COUNT(*) as count FROM notifications")['count'],
                'active' => Database::fetchOne("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1")['count'],
                'total_views' => Database::fetchOne("SELECT COALESCE(SUM(total_views), 0) as total FROM notification_stats")['total']
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب الإحصائيات العامة: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات الطلبات التفصيلية
     */
    public static function getOrdersStats($period = '30') {
        try {
            $periodDays = intval($period);
            $dateCondition = "created_at >= DATE_SUB(CURDATE(), INTERVAL {$periodDays} DAY)";
            
            // إحصائيات الطلبات حسب الحالة
            $statusStats = Database::fetchAll("
                SELECT 
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(price_lyd), 0) as total_amount
                FROM orders 
                WHERE {$dateCondition}
                GROUP BY status
                ORDER BY count DESC
            ");
            
            // إحصائيات الطلبات اليومية
            $dailyStats = Database::fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders_count,
                    COALESCE(SUM(price_lyd), 0) as daily_revenue
                FROM orders 
                WHERE {$dateCondition}
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            ");
            
            // إحصائيات الخدمات الأكثر طلباً
            $topServices = Database::fetchAll("
                SELECT 
                    COALESCE(sc.name_ar, 'خدمة غير محددة') as service_name,
                    COALESCE(sc.category_ar, 'عام') as category,
                    COUNT(o.id) as orders_count,
                    COALESCE(SUM(o.price_lyd), 0) as total_revenue
                FROM orders o
                LEFT JOIN services_cache sc ON o.service_id = sc.id
                WHERE COALESCE(o.service_id, 0) > 0 AND o.{$dateCondition}
                GROUP BY o.service_id, COALESCE(sc.name_ar, 'خدمة غير محددة'), COALESCE(sc.category_ar, 'عام')
                ORDER BY orders_count DESC
                LIMIT 10
            ");
            
            // إحصائيات المزودين
            $providerStats = Database::fetchAll("
                SELECT 
                    COALESCE(provider, 'peakerr') as provider_name,
                    COUNT(*) as orders_count,
                    COALESCE(SUM(price_lyd), 0) as total_revenue
                FROM orders 
                WHERE {$dateCondition}
                GROUP BY provider
                ORDER BY orders_count DESC
            ");
            
            return [
                'status_stats' => $statusStats,
                'daily_stats' => $dailyStats,
                'top_services' => $topServices,
                'provider_stats' => $providerStats,
                'period_days' => $periodDays
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الطلبات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات المستخدمين
     */
    public static function getUsersStats() {
        try {
            // إحصائيات تسجيل الدخول
            $loginStats = Database::fetchAll("
                SELECT 
                    DATE(last_login) as date,
                    COUNT(*) as login_count
                FROM users 
                WHERE last_login >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(last_login)
                ORDER BY date DESC
                LIMIT 30
            ");
            
            // المستخدمين الأكثر نشاطاً
            $activeUsers = Database::fetchAll("
                SELECT 
                    u.phone,
                    u.name,
                    COUNT(o.id) as orders_count,
                    COALESCE(SUM(o.price_lyd), 0) as total_spent,
                    u.last_login
                FROM users u
                LEFT JOIN orders o ON u.id = o.user_id AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY u.id, u.phone, u.name, u.last_login
                HAVING COUNT(o.id) > 0
                ORDER BY COUNT(o.id) DESC, COALESCE(SUM(o.price_lyd), 0) DESC
                LIMIT 10
            ");
            
            // إحصائيات المحافظ
            $walletStats = Database::fetchAll("
                SELECT 
                    COALESCE(w.user_id, 0) as user_id,
                    COALESCE(u.phone, 'غير محدد') as phone,
                    COALESCE(w.balance, 0) as balance,
                    COUNT(wt.id) as transactions_count
                FROM wallets w
                LEFT JOIN users u ON w.user_id = u.id
                LEFT JOIN wallet_transactions wt ON w.user_id = wt.user_id AND wt.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                WHERE COALESCE(w.balance, 0) > 0
                GROUP BY w.user_id, COALESCE(u.phone, 'غير محدد'), COALESCE(w.balance, 0)
                ORDER BY COALESCE(w.balance, 0) DESC
                LIMIT 10
            ");
            
            return [
                'login_stats' => $loginStats,
                'active_users' => $activeUsers,
                'wallet_stats' => $walletStats
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات المستخدمين: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات الخدمات
     */
    public static function getServicesStats() {
        try {
            // إحصائيات التصنيفات
            $categoryStats = Database::fetchAll("
                SELECT 
                    COALESCE(category_ar, category) as category_name,
                    COUNT(*) as services_count,
                    AVG(rate_per_1k_lyd) as avg_price,
                    MIN(rate_per_1k_lyd) as min_price,
                    MAX(rate_per_1k_lyd) as max_price
                FROM services_cache 
                WHERE category IS NOT NULL AND category != ''
                GROUP BY category, category_ar
                ORDER BY services_count DESC
            ");
            
            // إحصائيات المزودين
            $providerStats = Database::fetchAll("
                SELECT 
                    COALESCE(provider, 'peakerr') as provider_name,
                    COUNT(*) as services_count,
                    AVG(rate_per_1k_lyd) as avg_price
                FROM services_cache 
                GROUP BY provider
                ORDER BY services_count DESC
            ");
            
            // الخدمات الأكثر طلباً
            $popularServices = Database::fetchAll("
                SELECT 
                    COALESCE(sc.name_ar, 'خدمة غير محددة') as service_name,
                    COALESCE(sc.category_ar, 'عام') as category,
                    COALESCE(sc.rate_per_1k_lyd, 0) as price,
                    COUNT(o.id) as orders_count,
                    COALESCE(SUM(o.price_lyd), 0) as total_revenue
                FROM services_cache sc
                LEFT JOIN orders o ON sc.id = o.service_id AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                WHERE COALESCE(sc.id, 0) > 0
                GROUP BY sc.id, COALESCE(sc.name_ar, 'خدمة غير محددة'), COALESCE(sc.category_ar, 'عام'), COALESCE(sc.rate_per_1k_lyd, 0)
                ORDER BY orders_count DESC
                LIMIT 15
            ");
            
            return [
                'category_stats' => $categoryStats,
                'provider_stats' => $providerStats,
                'popular_services' => $popularServices
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الخدمات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات الإشعارات
     */
    public static function getNotificationsStats() {
        try {
            // إحصائيات الإشعارات حسب النوع
            $typeStats = Database::fetchAll("
                SELECT 
                    type,
                    COUNT(*) as count,
                    AVG(total_views) as avg_views,
                    AVG(total_dismissals) as avg_dismissals
                FROM notifications n
                LEFT JOIN notification_stats ns ON n.id = ns.notification_id
                GROUP BY type
                ORDER BY count DESC
            ");
            
            // الإشعارات الأكثر مشاهدة
            $topNotifications = Database::fetchAll("
                SELECT 
                    n.title,
                    n.type,
                    ns.total_views,
                    ns.unique_views,
                    ns.total_dismissals,
                    n.created_at
                FROM notifications n
                LEFT JOIN notification_stats ns ON n.id = ns.notification_id
                ORDER BY ns.total_views DESC
                LIMIT 10
            ");
            
            // إحصائيات المشاهدات اليومية
            $dailyViews = Database::fetchAll("
                SELECT 
                    DATE(nv.viewed_at) as date,
                    COUNT(*) as views_count,
                    COUNT(DISTINCT nv.user_ip) as unique_users
                FROM notification_views nv
                WHERE nv.viewed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(nv.viewed_at)
                ORDER BY date DESC
                LIMIT 30
            ");
            
            return [
                'type_stats' => $typeStats,
                'top_notifications' => $topNotifications,
                'daily_views' => $dailyViews
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الإشعارات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات الأداء
     */
    public static function getPerformanceStats() {
        try {
            // إحصائيات الأداء اليومية
            $dailyPerformance = Database::fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_orders,
                    COALESCE(SUM(price_lyd), 0) as daily_revenue,
                    ROUND((COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)), 2) as success_rate
                FROM orders 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            ");
            
            // معدل النجاح الإجمالي
            $overallSuccessRate = Database::fetchOne("
                SELECT 
                    ROUND((COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)), 2) as success_rate
                FROM orders
            ")['success_rate'] ?? 0;
            
            // متوسط وقت التنفيذ
            $avgExecutionTime = Database::fetchOne("
                SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes
                FROM orders 
                WHERE status IN ('completed', 'failed') 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ")['avg_minutes'] ?? 0;
            
            return [
                'daily_performance' => $dailyPerformance,
                'overall_success_rate' => $overallSuccessRate,
                'avg_execution_time' => $avgExecutionTime
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الأداء: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب تقرير شامل
     */
    public static function getComprehensiveReport($period = '30') {
        try {
            return [
                'general' => self::getGeneralStats(),
                'orders' => self::getOrdersStats($period),
                'users' => self::getUsersStats(),
                'services' => self::getServicesStats(),
                'notifications' => self::getNotificationsStats(),
                'performance' => self::getPerformanceStats(),
                'period' => $period,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في إنشاء التقرير الشامل: " . $e->getMessage());
            return [];
        }
    }
}
?>

