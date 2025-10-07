<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class ServiceFilter {
    
    /**
     * جلب الخدمات مع فلترة وترتيب متقدم
     */
    public static function getFilteredServices($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // فلترة حسب التصنيف
            if (!empty($filters['category'])) {
                $whereConditions[] = "(category LIKE ? OR category_ar LIKE ? OR subcategory LIKE ?)";
                $categoryFilter = '%' . $filters['category'] . '%';
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
            }
            
            // فلترة حسب النوع الفرعي
            if (!empty($filters['subcategory'])) {
                $whereConditions[] = "(subcategory = ? OR subcategory LIKE ?)";
                $params[] = $filters['subcategory'];
                $params[] = '%' . $filters['subcategory'] . '%';
            }
            
            // فلترة حسب السعر
            if (!empty($filters['min_price'])) {
                $whereConditions[] = "rate_per_1k_lyd >= ?";
                $params[] = $filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $whereConditions[] = "rate_per_1k_lyd <= ?";
                $params[] = $filters['max_price'];
            }
            
            // فلترة حسب البحث
            if (!empty($filters['search'])) {
                $whereConditions[] = "(name LIKE ? OR name_ar LIKE ? OR description LIKE ? OR description_ar LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // فلترة حسب المزود
            if (!empty($filters['provider'])) {
                $whereConditions[] = "provider = ?";
                $params[] = $filters['provider'];
            }
            
            // بناء الاستعلام
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            // ترتيب الخدمات
            $orderBy = self::buildOrderBy($filters);
            
            // حد عدد النتائج
            $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
            $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
            
            $sql = "SELECT 
                        id, external_id, name, name_ar, category, category_ar, subcategory,
                        rate_per_1k_lyd, min, max, type, description, description_ar,
                        orders_count, sort_order, provider
                    FROM services_cache 
                    {$whereClause}
                    {$orderBy}
                    LIMIT {$limit} OFFSET {$offset}";
            
            return Database::fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("خطأ في فلترة الخدمات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * بناء جملة ORDER BY حسب نوع الترتيب
     */
    private static function buildOrderBy($filters) {
        $orderBy = 'ORDER BY ';
        
        switch ($filters['sort'] ?? 'default') {
            case 'price_low':
                $orderBy .= 'rate_per_1k_lyd ASC';
                break;
            case 'price_high':
                $orderBy .= 'rate_per_1k_lyd DESC';
                break;
            case 'popular':
                $orderBy .= 'orders_count DESC, rate_per_1k_lyd ASC';
                break;
            case 'name':
                $orderBy .= 'name_ar ASC';
                break;
            case 'min_quantity':
                $orderBy .= 'min ASC';
                break;
            case 'max_quantity':
                $orderBy .= 'max DESC';
                break;
            default:
                $orderBy .= 'sort_order ASC, orders_count DESC, rate_per_1k_lyd ASC';
                break;
        }
        
        return $orderBy;
    }
    
    /**
     * جلب التصنيفات المتاحة
     */
    public static function getCategories() {
        try {
            $categories = Database::fetchAll("
                SELECT DISTINCT category, category_ar, COUNT(*) as count
                FROM services_cache 
                WHERE category IS NOT NULL AND category != ''
                GROUP BY category, category_ar
                ORDER BY count DESC, category_ar ASC
            ");
            
            return $categories;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب التصنيفات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب التصنيفات الفرعية
     */
    public static function getSubcategories($category = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($category) {
                $whereClause = 'WHERE (category = ? OR category_ar = ?)';
                $params[] = $category;
                $params[] = $category;
            }
            
            $subcategories = Database::fetchAll("
                SELECT DISTINCT subcategory, COUNT(*) as count
                FROM services_cache 
                {$whereClause}
                AND subcategory IS NOT NULL AND subcategory != ''
                GROUP BY subcategory
                ORDER BY count DESC, subcategory ASC
            ", $params);
            
            return $subcategories;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب التصنيفات الفرعية: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات الفلترة
     */
    public static function getFilterStats($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // نفس شروط الفلترة
            if (!empty($filters['category'])) {
                $whereConditions[] = "(category LIKE ? OR category_ar LIKE ? OR subcategory LIKE ?)";
                $categoryFilter = '%' . $filters['category'] . '%';
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
                $params[] = $categoryFilter;
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(name LIKE ? OR name_ar LIKE ? OR description LIKE ? OR description_ar LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            $stats = Database::fetchOne("
                SELECT 
                    COUNT(*) as total_services,
                    MIN(rate_per_1k_lyd) as min_price,
                    MAX(rate_per_1k_lyd) as max_price,
                    AVG(rate_per_1k_lyd) as avg_price
                FROM services_cache 
                {$whereClause}
            ", $params);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الفلترة: " . $e->getMessage());
            return [
                'total_services' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'avg_price' => 0
            ];
        }
    }
    
    /**
     * البحث السريع في الخدمات
     */
    public static function quickSearch($query, $limit = 10) {
        try {
            if (strlen($query) < 2) return [];
            
            $searchTerm = '%' . $query . '%';
            
            $results = Database::fetchAll("
                SELECT id, name, name_ar, category_ar, rate_per_1k_lyd
                FROM services_cache 
                WHERE (name LIKE ? OR name_ar LIKE ? OR description LIKE ? OR description_ar LIKE ?)
                ORDER BY 
                    CASE 
                        WHEN name LIKE ? THEN 1
                        WHEN name_ar LIKE ? THEN 2
                        ELSE 3
                    END,
                    orders_count DESC
                LIMIT {$limit}
            ", [
                $searchTerm, $searchTerm, $searchTerm, $searchTerm,
                $query . '%', $query . '%'
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            error_log("خطأ في البحث السريع: " . $e->getMessage());
            return [];
        }
    }
}
?>

