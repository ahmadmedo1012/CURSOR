<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Services/PlatformIcons.php';

class CustomerServiceFilter {
    
    // Column detection cache to avoid repeated queries
    private static $columnCache = null;
    
    /**
     * Detect which columns are available in services_cache table
     */
    private static function detectAvailableColumns() {
        if (self::$columnCache !== null) {
            return self::$columnCache;
        }
        
        $columns = [
            'service_platform' => false,
            'service_type' => false,
            'group_slug' => false,
            'subcategory' => false,
            'is_active' => false,
            'is_visible' => false,
            'is_deleted' => false,
            'orders_count' => false,
            'sort_order' => false,
            'updated_at' => false
        ];
        
        try {
            // Check for platform columns
            $platformResult = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE 'service_platform'");
            if ($platformResult) {
                $columns['service_platform'] = true;
            } else {
                $groupResult = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE 'group_slug'");
                if ($groupResult) {
                    $columns['group_slug'] = true;
                }
            }
            
            // Check for type columns
            $typeResult = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE 'service_type'");
            if ($typeResult) {
                $columns['service_type'] = true;
            } else {
                $subcategoryResult = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE 'subcategory'");
                if ($subcategoryResult) {
                    $columns['subcategory'] = true;
                }
            }
            
            // Check for flag columns
            $flagColumns = ['is_active', 'is_visible', 'is_deleted'];
            foreach ($flagColumns as $col) {
                $result = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE ?", [$col]);
                if ($result) {
                    $columns[$col] = true;
                }
            }
            
            // Check for optional columns
            $optionalColumns = ['orders_count', 'sort_order', 'updated_at'];
            foreach ($optionalColumns as $col) {
                $result = Database::fetchOne("SHOW COLUMNS FROM services_cache LIKE ?", [$col]);
                if ($result) {
                    $columns[$col] = true;
                }
            }
            
        } catch (Exception $e) {
            error_log("Column detection error: " . $e->getMessage());
            // Fallback: assume only basic columns exist
            $columns = [
                'service_platform' => false,
                'service_type' => false,
                'group_slug' => false,
                'subcategory' => false,
                'is_active' => false,
                'is_visible' => false,
                'is_deleted' => false,
                'orders_count' => false,
                'sort_order' => false,
                'updated_at' => false
            ];
        }
        
        self::$columnCache = $columns;
        return self::$columnCache;
    }
    
    /**
     * Unified catalog WHERE function - returns WHERE string and parameters
     * Uses only: group_slug (platform), subcategory, name/name_ar columns
     * Used by ALL catalog queries (COUNT, SELECT, platform counts, type counts)
     */
    public static function buildCatalogWhere($platform = 'all', $type = 'all', $q = '') {
        $parts = [];
        $binds = [];
        
        // Platform filter (using group_slug column)
        if ($platform && $platform !== 'all') {
            $parts[] = "LOWER(`group_slug`) = LOWER(?)";
            $binds[] = $platform;
        }
        
        // Type filter
        if ($type && $type !== 'all') {
            $parts[] = "LOWER(`subcategory`) = LOWER(?)";
            $binds[] = $type;
        }
        
        // Search filter
        if ($q) {
            $parts[] = "LOWER(COALESCE(`name_ar`,`name`)) LIKE CONCAT('%', LOWER(?), '%')";
            $binds[] = $q;
        }
        
        $whereSql = $parts ? "WHERE " . implode(" AND ", $parts) : "";
        
        return [$whereSql, $binds];
    }
    
    /**
     * Safe ORDER BY using whitelist with numeric price sorting
     */
    public static function resolveOrder($sort) {
        $s = strtolower(trim($sort ?: 'default'));
        $price = "COALESCE(`rate_per_1k_lyd`,`rate_per_1k`,`rate_per_1k_usd`)+0";
        
        switch($s){
            case 'popular': return "ORDER BY `orders_count` DESC, `id` DESC";
            case 'cheap'  : return "ORDER BY {$price} ASC, `id` DESC";
            case 'new'    : return "ORDER BY `updated_at` DESC, `id` DESC";
            default       : return "ORDER BY `sort_order` DESC, `orders_count` DESC, `id` DESC";
        }
    }
    
    // المنصات الرئيسية مع الأيقونات والألوان
    private static $platforms = [
        'instagram' => [
            'name' => 'انستغرام',
            'name_en' => 'Instagram',
            'icon' => '📷',
            'color' => '#E4405F',
            'gradient' => 'linear-gradient(135deg, #833ab4 0%, #fd1d1d 50%, #fcb045 100%)'
        ],
        'facebook' => [
            'name' => 'فيسبوك',
            'name_en' => 'Facebook',
            'icon' => '👥',
            'color' => '#1877F2',
            'gradient' => 'linear-gradient(135deg, #1877f2 0%, #42a5f5 100%)'
        ],
        'tiktok' => [
            'name' => 'تيك توك',
            'name_en' => 'TikTok',
            'icon' => '🎵',
            'color' => '#000000',
            'gradient' => 'linear-gradient(135deg, #000000 0%, #ff0050 50%, #00f2ea 100%)'
        ],
        'youtube' => [
            'name' => 'يوتيوب',
            'name_en' => 'YouTube',
            'icon' => '📺',
            'color' => '#FF0000',
            'gradient' => 'linear-gradient(135deg, #ff0000 0%, #cc0000 100%)'
        ],
        'twitter' => [
            'name' => 'تويتر',
            'name_en' => 'Twitter',
            'icon' => '🐦',
            'color' => '#1DA1F2',
            'gradient' => 'linear-gradient(135deg, #1da1f2 0%, #0d8bd9 100%)'
        ],
        'telegram' => [
            'name' => 'تيليجرام',
            'name_en' => 'Telegram',
            'icon' => '✈️',
            'color' => '#0088CC',
            'gradient' => 'linear-gradient(135deg, #0088cc 0%, #229ed9 100%)'
        ],
        'snapchat' => [
            'name' => 'سناب شات',
            'name_en' => 'Snapchat',
            'icon' => '👻',
            'color' => '#FFFC00',
            'gradient' => 'linear-gradient(135deg, #fffc00 0%, #ffd700 100%)'
        ],
        'linkedin' => [
            'name' => 'لينكد إن',
            'name_en' => 'LinkedIn',
            'icon' => '💼',
            'color' => '#0077B5',
            'gradient' => 'linear-gradient(135deg, #0077b5 0%, #005885 100%)'
        ],
        'pinterest' => [
            'name' => 'بينتيريست',
            'name_en' => 'Pinterest',
            'icon' => '📌',
            'color' => '#BD081C',
            'gradient' => 'linear-gradient(135deg, #bd081c 0%, #e60023 100%)'
        ],
        'whatsapp' => [
            'name' => 'واتساب',
            'name_en' => 'WhatsApp',
            'icon' => '💬',
            'color' => '#25D366',
            'gradient' => 'linear-gradient(135deg, #25d366 0%, #128c7e 100%)'
        ]
    ];
    
    // أنواع الخدمات الشائعة
    private static $serviceTypes = [
        'followers' => [
            'name' => 'متابعين',
            'name_en' => 'Followers',
            'icon' => '👥',
            'description' => 'زيادة عدد المتابعين'
        ],
        'likes' => [
            'name' => 'إعجابات',
            'name_en' => 'Likes',
            'icon' => '❤️',
            'description' => 'زيادة الإعجابات على المنشورات'
        ],
        'views' => [
            'name' => 'مشاهدات',
            'name_en' => 'Views',
            'icon' => '👁️',
            'description' => 'زيادة المشاهدات للمحتوى'
        ],
        'comments' => [
            'name' => 'تعليقات',
            'name_en' => 'Comments',
            'icon' => '💬',
            'description' => 'زيادة التعليقات على المنشورات'
        ],
        'shares' => [
            'name' => 'مشاركات',
            'name_en' => 'Shares',
            'icon' => '🔄',
            'description' => 'زيادة المشاركات والمشاركة'
        ],
        'subscribers' => [
            'name' => 'مشتركين',
            'name_en' => 'Subscribers',
            'icon' => '🔔',
            'description' => 'زيادة المشتركين في القناة'
        ],
        'plays' => [
            'name' => 'تشغيلات',
            'name_en' => 'Plays',
            'icon' => '▶️',
            'description' => 'زيادة عدد التشغيلات'
        ],
        'downloads' => [
            'name' => 'تحميلات',
            'name_en' => 'Downloads',
            'icon' => '⬇️',
            'description' => 'زيادة عدد التحميلات'
        ],
        'reactions' => [
            'name' => 'تفاعلات',
            'name_en' => 'Reactions',
            'icon' => '😍',
            'description' => 'زيادة التفاعلات العامة'
        ],
        'engagement' => [
            'name' => 'تفاعل',
            'name_en' => 'Engagement',
            'icon' => '📈',
            'description' => 'تحسين التفاعل العام'
        ]
    ];
    
    /**
     * جلب المنصات المتاحة مع عدد الخدمات
     */
    public static function getAvailablePlatforms() {
        try {
            // Use unified WHERE function (excluding platform filter for platform counts)
            list($whereSql, $binds) = self::buildCatalogWhere('all', 'all', '');
            
            // Get platform counts using unified WHERE
            $sql = "SELECT LOWER(`group_slug`) AS plat, COUNT(*) AS cnt 
                    FROM services_cache 
                    {$whereSql}
                    GROUP BY LOWER(`group_slug`) 
                    ORDER BY cnt DESC";
            
            $platforms = Database::fetchAll($sql, $binds);
            
            $result = [];
            foreach ($platforms as $row) {
                $platformKey = $row['plat'];
                if (isset(self::$platforms[$platformKey])) {
                    $result[$platformKey] = array_merge(self::$platforms[$platformKey], [
                        'count' => intval($row['cnt'])
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب المنصات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب أنواع الخدمات المتاحة لمنصة معينة
     */
    public static function getServiceTypesForPlatform($platform = 'all') {
        try {
            // Use unified WHERE function (including platform filter, excluding type filter)
            list($whereSql, $binds) = self::buildCatalogWhere($platform, 'all', '');
            
            // Get type counts using unified WHERE
            $sql = "SELECT LOWER(`subcategory`) AS t, COUNT(*) AS cnt 
                    FROM services_cache 
                    {$whereSql}
                    GROUP BY LOWER(`subcategory`) 
                    ORDER BY cnt DESC";
            
            $types = Database::fetchAll($sql, $binds);
            
            $result = [];
            foreach ($types as $row) {
                $typeKey = $row['t'];
                if (isset(self::$serviceTypes[$typeKey])) {
                    $result[$typeKey] = array_merge(self::$serviceTypes[$typeKey], [
                        'count' => intval($row['cnt'])
                    ]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب أنواع الخدمات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب الخدمات مع server-side pagination
     */
    public static function getServicesPaginated($platform = 'all', $serviceType = 'all', $searchQuery = '', $sort = 'default', $page = 1, $perPage = 24) {
        try {
            // Clamp page size
            $perPage = max(12, min(48, intval($perPage))); // Between 12-48
            $page = max(1, intval($page)); // At least page 1
            
            // Use unified WHERE function
            list($whereSql, $binds) = self::buildCatalogWhere($platform, $serviceType, $searchQuery);
            
            // Count total services for pagination
            $countSql = "SELECT COUNT(*) as total FROM services_cache {$whereSql}";
            $total = Database::fetchOne($countSql, $binds)['total'];
            
            // Calculate pagination
            $lastPage = max(1, ceil($total / $perPage));
            
            // Always clamp page to last_page if it exceeds it
            if ($page > $lastPage) {
                $page = $lastPage;
            }
            
            // Safe ORDER BY using whitelist
            $orderSql = self::resolveOrder($sort);
            
            // Calculate OFFSET
            $offset = ($page - 1) * $perPage;
            
            // Get services data with unified SELECT
            $servicesSql = "SELECT 
                        `id`,
                        COALESCE(`name_ar`,`name`) AS name,
                        `group_slug` AS platform,
                        `subcategory` AS type,
                        `rate_per_1k_lyd`,
                        `rate_per_1k`,
                        `rate_per_1k_usd`,
                        `min`,
                        `max`,
                        `orders_count`,
                        `sort_order`,
                        `updated_at`
                    FROM services_cache 
                    {$whereSql}
                    {$orderSql}
                    LIMIT {$perPage} OFFSET {$offset}";
            
            $services = Database::fetchAll($servicesSql, $binds);
            
            return [
                'services' => $services,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'has_next' => $page < $lastPage,
                'has_prev' => $page > 1
            ];
            
        } catch (Exception $e) {
            error_log("CustomerServiceFilter::getServicesPaginated error: " . $e->getMessage());
            return [
                'services' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'last_page' => 1,
                'has_next' => false,
                'has_prev' => false
            ];
        }
    }

    /**
     * جلب الخدمات حسب المنصة والنوع (Legacy method)
     */
    public static function getServicesByPlatformAndType($platform = 'all', $serviceType = 'all', $sort = 'default', $limit = 100, $offset = 0) {
        try {
            // Detect available columns in the database
            $columns = self::detectAvailableColumns();
            
            // Permissive filters - allow services with any valid rate or missing rate data
            $whereConditions = ['(rate_per_1k_lyd > 0 OR rate_per_1k_lyd IS NULL OR rate_per_1k > 0)'];
            
            // Add NULL-safe flag conditions only if columns exist
            if ($columns['is_active']) {
                $whereConditions[] = '(COALESCE(is_active, 1) = 1)';
            }
            if ($columns['is_visible']) {
                $whereConditions[] = '(COALESCE(is_visible, 1) = 1)';
            }
            if ($columns['is_deleted']) {
                $whereConditions[] = '(COALESCE(is_deleted, 0) = 0)';
            }
            $params = [];
            
            // فلترة حسب المنصة
            if ($platform !== 'all' && isset(self::$platforms[$platform])) {
                $platformData = self::$platforms[$platform];
                $whereConditions[] = "(
                    LOWER(COALESCE(category, '')) LIKE ? OR 
                    LOWER(COALESCE(name, '')) LIKE ? OR 
                    LOWER(COALESCE(name_ar, '')) LIKE ? OR
                    LOWER(COALESCE(category_ar, '')) LIKE ?
                )";
                $params = array_merge($params, [
                    '%' . strtolower(trim($platform)) . '%',
                    '%' . strtolower(trim($platformData['name_en'])) . '%',
                    '%' . strtolower(trim($platformData['name'])) . '%',
                    '%' . strtolower(trim($platformData['name'])) . '%'
                ]);
            }
            
            // فلترة حسب نوع الخدمة
            if ($serviceType !== 'all' && isset(self::$serviceTypes[$serviceType])) {
                $typeData = self::$serviceTypes[$serviceType];
                $whereConditions[] = "(
                    LOWER(COALESCE(name, '')) LIKE ? OR 
                    LOWER(COALESCE(name_ar, '')) LIKE ? OR 
                    LOWER(COALESCE(subcategory, '')) LIKE ? OR
                    LOWER(COALESCE(type, '')) LIKE ?
                )";
                $params = array_merge($params, [
                    '%' . strtolower(trim($serviceType)) . '%',
                    '%' . strtolower(trim($typeData['name_en'])) . '%',
                    '%' . strtolower(trim($typeData['name'])) . '%',
                    '%' . strtolower(trim($typeData['name'])) . '%'
                ]);
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // بناء ORDER BY
            $orderBy = self::buildOrderBy($sort, $columns);
            
            // Ensure safe pagination values
            $limit = max(1, min(1000, intval($limit))); // Between 1 and 1000
            $offset = max(0, intval($offset)); // Non-negative
            
            $sql = "SELECT 
                        id, external_id, name, name_ar, category, category_ar, subcategory,
                        rate_per_1k_lyd, rate_per_1k, min, max, type, description, description_ar,
                        orders_count, sort_order, 'peakerr' as provider
                    FROM services_cache 
                    {$whereClause}
                    {$orderBy}
                    LIMIT {$limit} OFFSET {$offset}";
            
            $services = Database::fetchAll($sql, $params);
            
            // ترجمة الخدمات تلقائياً إذا لم تكن مترجمة
            foreach ($services as &$service) {
                if (empty($service['name_ar'])) {
                    $service['name_ar'] = TranslationService::translateServiceName($service['name']);
                }
                if (empty($service['category_ar'])) {
                    $service['category_ar'] = TranslationService::translateCategory($service['category']);
                }
                if (empty($service['description_ar']) && !empty($service['description'])) {
                    $service['description_ar'] = TranslationService::translateServiceDescription($service['description']);
                }
                if (empty($service['subcategory'])) {
                    $service['subcategory'] = TranslationService::extractSubcategory($service['name'], $service['category']);
                }
            }
            
            return $services;
            
        } catch (Exception $e) {
            error_log("خطأ في جلب الخدمات: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * بناء جملة ORDER BY
     */
    private static function buildOrderBy($sort, $columns = null) {
        // If columns not passed, create empty array for backward compatibility
        if ($columns === null) {
            $columns = [
                'orders_count' => false,
                'sort_order' => false,
                'updated_at' => false
            ];
        }
        
        switch ($sort) {
            case 'price_low':
                return 'ORDER BY rate_per_1k_lyd ASC, COALESCE(name_ar, name) ASC';
            case 'price_high':
                return 'ORDER BY rate_per_1k_lyd DESC, COALESCE(name_ar, name) ASC';
            case 'popular':
                if ($columns['orders_count']) {
                    return 'ORDER BY orders_count DESC, rate_per_1k_lyd ASC';
                }
                return 'ORDER BY rate_per_1k_lyd ASC, COALESCE(name_ar, name) ASC';
            case 'speed':
                if ($columns['orders_count']) {
                    return 'ORDER BY orders_count DESC, (max - min) ASC, rate_per_1k_lyd ASC'; // More orders = faster, smaller range = simpler
                }
                return 'ORDER BY (max - min) ASC, rate_per_1k_lyd ASC';
            case 'rate':
                if ($columns['orders_count'] && $columns['sort_order']) {
                    return 'ORDER BY orders_count DESC, sort_order ASC, rate_per_1k_lyd ASC'; // Popular services first = higher rating
                } elseif ($columns['sort_order']) {
                    return 'ORDER BY sort_order ASC, rate_per_1k_lyd ASC';
                } elseif ($columns['orders_count']) {
                return 'ORDER BY orders_count DESC, rate_per_1k_lyd ASC';
                }
                return 'ORDER BY rate_per_1k_lyd ASC';
            case 'name':
                return 'ORDER BY COALESCE(name_ar, name) ASC, rate_per_1k_lyd ASC';
            case 'newest':
                if ($columns['updated_at'] && $columns['orders_count']) {
                    return 'ORDER BY updated_at DESC, orders_count DESC';
                } elseif ($columns['updated_at']) {
                    return 'ORDER BY updated_at DESC, rate_per_1k_lyd ASC';
                } elseif ($columns['orders_count']) {
                    return 'ORDER BY orders_count DESC, rate_per_1k_lyd ASC';
                }
                return 'ORDER BY rate_per_1k_lyd ASC';
            case 'min_quantity':
                return 'ORDER BY min ASC, rate_per_1k_lyd ASC';
            default:
                if ($columns['sort_order'] && $columns['orders_count']) {
                return 'ORDER BY sort_order ASC, orders_count DESC, rate_per_1k_lyd ASC';
                } elseif ($columns['sort_order']) {
                    return 'ORDER BY sort_order ASC, rate_per_1k_lyd ASC';
                } elseif ($columns['orders_count']) {
                    return 'ORDER BY orders_count DESC, rate_per_1k_lyd ASC';
                }
                return 'ORDER BY rate_per_1k_lyd ASC, COALESCE(name_ar, name) ASC';
        }
    }
    
    /**
     * البحث في الخدمات
     */
    public static function searchServices($query, $platform = 'all', $serviceType = 'all', $limit = 50, $offset = 0) {
        try {
            if (strlen($query) < 2) return [];
            
            $whereConditions = [
                '(rate_per_1k_lyd > 0 OR rate_per_1k_lyd IS NULL OR rate_per_1k > 0)',
                "(LOWER(COALESCE(name, '')) LIKE ? OR LOWER(COALESCE(name_ar, '')) LIKE ? OR LOWER(COALESCE(description, '')) LIKE ? OR LOWER(COALESCE(description_ar, '')) LIKE ?)"
            ];
            $searchTerm = '%' . strtolower(trim($query)) . '%';
            $params = [
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm
            ];
            
            // فلترة حسب المنصة
            if ($platform !== 'all' && isset(self::$platforms[$platform])) {
                $platformData = self::$platforms[$platform];
                $whereConditions[] = "(
                    LOWER(COALESCE(category, '')) LIKE ? OR 
                    LOWER(COALESCE(name, '')) LIKE ? OR 
                    LOWER(COALESCE(name_ar, '')) LIKE ? OR
                    LOWER(COALESCE(category_ar, '')) LIKE ?
                )";
                $params = array_merge($params, [
                    '%' . strtolower(trim($platform)) . '%',
                    '%' . strtolower(trim($platformData['name_en'])) . '%',
                    '%' . strtolower(trim($platformData['name'])) . '%',
                    '%' . strtolower(trim($platformData['name'])) . '%'
                ]);
            }
            
            // فلترة حسب نوع الخدمة
            if ($serviceType !== 'all' && isset(self::$serviceTypes[$serviceType])) {
                $typeData = self::$serviceTypes[$serviceType];
                $whereConditions[] = "(
                    LOWER(COALESCE(name, '')) LIKE ? OR 
                    LOWER(COALESCE(name_ar, '')) LIKE ? OR 
                    LOWER(COALESCE(subcategory, '')) LIKE ? OR
                    LOWER(COALESCE(type, '')) LIKE ?
                )";
                $params = array_merge($params, [
                    '%' . strtolower(trim($serviceType)) . '%',
                    '%' . strtolower(trim($typeData['name_en'])) . '%',
                    '%' . strtolower(trim($typeData['name'])) . '%',
                    '%' . strtolower(trim($typeData['name'])) . '%'
                ]);
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Ensure safe pagination values
            $limit = max(1, min(1000, intval($limit))); // Between 1 and 1000
            $offset = max(0, intval($offset)); // Non-negative
            
            $sql = "SELECT 
                        id, external_id, name, name_ar, category, category_ar, subcategory,
                        rate_per_1k_lyd, rate_per_1k, min, max, type, description, description_ar,
                        orders_count, sort_order, 'peakerr' as provider
                    FROM services_cache 
                    {$whereClause}
                    ORDER BY 
                        CASE 
                            WHEN name LIKE ? THEN 1
                            WHEN name_ar LIKE ? THEN 2
                            ELSE 3
                        END,
                        orders_count DESC
                    LIMIT {$limit} OFFSET {$offset}";
            
            $searchParams = array_merge($params, [$query . '%', $query . '%']);
            
            return Database::fetchAll($sql, $searchParams);
            
        } catch (Exception $e) {
            error_log("خطأ في البحث: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب إحصائيات سريعة
     */
    public static function getQuickStats($platform = 'all', $serviceType = 'all') {
        try {
            // Use unified WHERE function
            list($whereSql, $binds) = self::buildCatalogWhere($platform, $serviceType, '');
            
            // Get stats using unified WHERE
            $sql = "SELECT 
                        COUNT(*) as total_services,
                        MIN(COALESCE(`rate_per_1k_lyd`,`rate_per_1k`,`rate_per_1k_usd`) + 0) as min_price,
                        MAX(COALESCE(`rate_per_1k_lyd`,`rate_per_1k`,`rate_per_1k_usd`) + 0) as max_price,
                        AVG(COALESCE(`rate_per_1k_lyd`,`rate_per_1k`,`rate_per_1k_usd`) + 0) as avg_price
                    FROM services_cache 
                    {$whereSql}";
            
            $stats = Database::fetchOne($sql, $binds);
            
            return [
                'total_services' => intval($stats['total_services']),
                'min_price' => floatval($stats['min_price']),
                'max_price' => floatval($stats['max_price']),
                'avg_price' => floatval($stats['avg_price'])
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب الإحصائيات: " . $e->getMessage());
            return [
                'total_services' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'avg_price' => 0
            ];
        }
    }
}
?>

