<?php

/**
 * Professional Catalog Page - Services Directory
 * @version 2.1
 */

// Load core dependencies
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Services/PeakerrClient.php';
require_once BASE_PATH . '/src/Services/CustomerServiceFilter.php';

// Initialize session
Auth::startSession();
$currentUser = Auth::currentUser() ?? null;

// Page metadata
$pageTitle = 'الخدمات';
$pageDescription = 'تصفح جميع خدمات وسائل التواصل الاجتماعي والألعاب - متابعين، إعجابات، مشاهدات، عملات وأكثر بأفضل الأسعار';
$ogType = 'website';

// Input sanitization
function sanitizeInput($input, $type = 'string')
{
    if (is_null($input)) return null;

    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Get and validate parameters
$platform = sanitizeInput($_GET['platform'] ?? 'all', 'string');
$serviceType = sanitizeInput($_GET['type'] ?? 'all', 'string');
$sort = sanitizeInput($_GET['sort'] ?? 'default', 'string');
$searchQuery = sanitizeInput($_GET['q'] ?? '', 'string');
$sync = sanitizeInput($_GET['sync'] ?? '', 'string');

// Validate sort parameter
$allowedSorts = ['default', 'popular', 'cheap', 'new', 'name'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'default';
}

// Rate limiting for sync
$syncAllowed = false;
if ($sync === '1') {
    $lastSync = $_SESSION['last_sync'] ?? 0;
    if ((time() - $lastSync) >= 300) {
        $syncAllowed = true;
        $_SESSION['last_sync'] = time();
    }
}

// Handle API sync
if ($syncAllowed && $sync === '1') {
    try {
        $peakerr = new PeakerrClient();
        $apiServices = $peakerr->getServices();

        if (is_array($apiServices) && !empty($apiServices)) {
            Database::query("START TRANSACTION");

            try {
                Database::query("DELETE FROM services_cache");

                if (isset($_SESSION['services_cache'])) {
                    unset($_SESSION['services_cache']);
                }

                $addedCount = 0;
                $skippedCount = 0;

                foreach ($apiServices as $service) {
                    $externalId = sanitizeInput($service['service'] ?? $service['id'] ?? uniqid(), 'string');
                    $name = sanitizeInput($service['name'] ?? $service['service_name'] ?? 'خدمة غير محددة', 'string');
                    $category = sanitizeInput($service['category'] ?? $service['type'] ?? 'عام', 'string');
                    $rate = sanitizeInput($service['rate'] ?? $service['price_per_1k'] ?? $service['rate_per_1k'] ?? 0, 'float');
                    $min = sanitizeInput($service['min'] ?? $service['minimum'] ?? 0, 'int');
                    $max = sanitizeInput($service['max'] ?? $service['maximum'] ?? 0, 'int');

                    if (empty($name) || $rate <= 0 || $min < 0 || $max < $min) {
                        $skippedCount++;
                        continue;
                    }

                    Database::query(
                        "INSERT INTO services_cache (external_id, name, category, rate_per_1k, min, max, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$externalId, $name, $category, $rate, $min, $max]
                    );
                    $addedCount++;
                }

                Database::query("COMMIT");
                $successMessage = "تم تحديث $addedCount خدمة بنجاح!" . ($skippedCount > 0 ? " (تم تجاهل $skippedCount خدمة غير صالحة)" : "");
            } catch (Exception $e) {
                Database::query("ROLLBACK");
                throw $e;
            }
        } else {
            $errorMessage = "لم يتم العثور على خدمات في API أو كانت الاستجابة فارغة.";
        }
    } catch (Exception $e) {
        error_log("Sync error: " . $e->getMessage());
        $errorMessage = "خطأ في تحديث الخدمات: " . $e->getMessage();
    }
} elseif ($sync === '1' && !$syncAllowed) {
    $errorMessage = "يمكن تحديث الخدمات مرة واحدة كل 5 دقائق فقط. يرجى المحاولة لاحقاً.";
}

// Get services data
try {
    // Get platforms
    $platformsResult = Database::fetchAll("
        SELECT DISTINCT group_slug, name, COUNT(*) as service_count 
        FROM services_cache 
        WHERE group_slug IS NOT NULL AND group_slug != '' 
        GROUP BY group_slug, name 
        ORDER BY service_count DESC, name ASC
    ");
    $platforms = [];
    foreach ($platformsResult as $row) {
        $platforms[$row['group_slug']] = [
            'name' => $row['name'],
            'count' => (int)$row['service_count']
        ];
    }

    // Get service types
    $typesResult = Database::fetchAll("
        SELECT DISTINCT subcategory, COUNT(*) as service_count 
        FROM services_cache 
        WHERE subcategory IS NOT NULL AND subcategory != '' 
        GROUP BY subcategory 
        ORDER BY service_count DESC, subcategory ASC
    ");
    $serviceTypes = [];
    foreach ($typesResult as $row) {
        $serviceTypes[$row['subcategory']] = [
            'name' => $row['subcategory'],
            'count' => (int)$row['service_count']
        ];
    }

    // Get services using CustomerServiceFilter
    list($whereClause, $params) = CustomerServiceFilter::buildCatalogWhere($platform, $serviceType, $searchQuery);
    $orderBy = CustomerServiceFilter::resolveOrder($sort);

    $sql = "SELECT 
                `id`, `external_id`, `name`, `name_ar`, `category`, `category_ar`, 
                `group_slug`, `rate_per_1k`, `rate_per_1k_lyd`, `rate_per_1k_usd`,
                `min`, `max`, `type`, `subcategory`, `description`, `description_ar`,
                `orders_count`, `updated_at`, `sort_order`
            FROM `services_cache` 
            {$whereClause}
            {$orderBy}
            LIMIT 1000";

    $allServices = Database::fetchAll($sql, $params);

    // Group services
    $groupedServices = [];
    $totalServices = 0;

    foreach ($allServices as $service) {
        if (empty($service['name']) && empty($service['name_ar'])) {
            continue;
        }

        $platformKey = $service['group_slug'] ?? 'other';
        $typeKey = $service['subcategory'] ?? 'عام';

        if (!isset($groupedServices[$platformKey])) {
            $groupedServices[$platformKey] = [];
        }

        if (!isset($groupedServices[$platformKey][$typeKey])) {
            $groupedServices[$platformKey][$typeKey] = [];
        }

        $groupedServices[$platformKey][$typeKey][] = $service;
        $totalServices++;
    }

    // Get statistics
    $stats = CustomerServiceFilter::getQuickStats($platform, $serviceType);
} catch (Exception $e) {
    error_log("Catalog data processing error: " . $e->getMessage());
    $errorMessage = "خطأ في جلب الخدمات. يرجى المحاولة لاحقاً.";
    $groupedServices = [];
    $stats = ['total_services' => 0];
    $platforms = [];
    $serviceTypes = [];
    $totalServices = 0;
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <!-- Page Header -->
    <div class="catalog-header">
        <div class="catalog-title-section">
            <h1 class="catalog-title">
                <?php if ($platform !== 'all' && isset($platforms[$platform])): ?>
                    خدمات <?php echo htmlspecialchars($platforms[$platform]['name']); ?>
                <?php else: ?>
                    جميع الخدمات
                <?php endif; ?>
            </h1>
            <p class="catalog-subtitle">اختر من بين أفضل الخدمات لتنمية حساباتك على وسائل التواصل الاجتماعي</p>

            <?php if (!empty($searchQuery)): ?>
                <div class="search-context">
                    <span class="search-label">نتائج البحث عن:</span>
                    <span class="search-term">"<?php echo htmlspecialchars($searchQuery); ?>"</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($totalServices > 0): ?>
            <div class="catalog-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($totalServices); ?></div>
                    <div class="stat-label">خدمة متاحة</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($groupedServices); ?></div>
                    <div class="stat-label">منصة</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo Formatters::formatMoney($stats['min_price'] ?? 0); ?></div>
                    <div class="stat-label">أقل سعر</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="catalog-filters">
        <div class="filters-row">
            <!-- Search -->
            <div class="filter-group">
                <label for="search-input" class="filter-label">🔍 البحث</label>
                <input type="text"
                    id="search-input"
                    class="search-input"
                    placeholder="ابحث عن خدمة..."
                    value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <!-- Platform Filter -->
            <div class="filter-group">
                <label for="platform-filter" class="filter-label">🌐 المنصة</label>
                <select id="platform-filter" class="filter-select">
                    <option value="all" <?php echo $platform === 'all' ? 'selected' : ''; ?>>جميع المنصات</option>
                    <?php foreach ($platforms as $key => $platformData): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $platform === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($platformData['name']); ?>
                            <?php if (isset($platformData['count'])): ?>
                                (<?php echo $platformData['count']; ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type Filter -->
            <div class="filter-group">
                <label for="type-filter" class="filter-label">🎯 النوع</label>
                <select id="type-filter" class="filter-select">
                    <option value="all" <?php echo $serviceType === 'all' ? 'selected' : ''; ?>>جميع الأنواع</option>
                    <?php foreach ($serviceTypes as $key => $typeData): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $serviceType === $key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($typeData['name']); ?>
                            <?php if (isset($typeData['count'])): ?>
                                (<?php echo $typeData['count']; ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort Filter -->
            <div class="filter-group">
                <label for="sort-filter" class="filter-label">📊 الترتيب</label>
                <select id="sort-filter" class="filter-select">
                    <option value="default" <?php echo $sort === 'default' ? 'selected' : ''; ?>>الافتراضي</option>
                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>الأكثر طلباً</option>
                    <option value="cheap" <?php echo $sort === 'cheap' ? 'selected' : ''; ?>>الأقل سعراً</option>
                    <option value="new" <?php echo $sort === 'new' ? 'selected' : ''; ?>>الأحدث</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>حسب الاسم</option>
                </select>
            </div>
        </div>

        <!-- Quick Filters -->
        <div class="quick-filters">
            <button class="quick-filter-btn" data-filter="platform" data-value="all">جميع المنصات</button>
            <button class="quick-filter-btn" data-filter="type" data-value="all">جميع الأنواع</button>
            <button class="quick-filter-btn" data-filter="sort" data-value="cheap">أقل سعر</button>
            <button class="quick-filter-btn" data-filter="sort" data-value="popular">الأكثر طلباً</button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <strong>✅ نجح!</strong> <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-error">
            <strong>❌ خطأ!</strong> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Search Results Header -->
    <?php if (!empty($searchQuery)): ?>
        <div class="search-results-header">
            <h2 class="results-title">
                نتائج البحث عن: "<span class="search-term"><?php echo htmlspecialchars($searchQuery); ?></span>"
            </h2>
            <div class="results-count"><?php echo $totalServices; ?> خدمة</div>
        </div>
    <?php endif; ?>

    <!-- Services Display -->
    <?php if (empty($groupedServices)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3 class="empty-title">لا توجد خدمات متاحة</h3>
            <p class="empty-description">
                <?php if (!empty($searchQuery)): ?>
                    لم يتم العثور على خدمات تطابق البحث "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>".
                    <br>جرب كلمات مفتاحية أخرى أو تصفح جميع الخدمات.
                <?php else: ?>
                    لم يتم العثور على خدمات تطابق الفلاتر المحددة.
                <?php endif; ?>
            </p>
            <div class="empty-actions">
                <?php if (!empty($searchQuery)): ?>
                    <a href="?platform=<?php echo urlencode($platform); ?>&type=<?php echo urlencode($serviceType); ?>&sort=<?php echo urlencode($sort); ?>" class="btn btn-primary">عرض جميع الخدمات</a>
                <?php endif; ?>
                <?php if ($syncAllowed): ?>
                    <a href="?sync=1&platform=<?php echo urlencode($platform); ?>&type=<?php echo urlencode($serviceType); ?>&sort=<?php echo urlencode($sort); ?>&q=<?php echo urlencode($searchQuery); ?>" class="btn btn-secondary">تحديث الخدمات</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Services Display -->
        <div class="catalog-content" id="catalog-content">
            <?php foreach ($groupedServices as $platformKey => $platformServices): ?>
                <?php
                $platformName = $platforms[$platformKey]['name'] ?? ucfirst($platformKey);
                $platformServiceCount = array_sum(array_map('count', $platformServices));
                ?>

                <section class="cat-platform">
                    <header class="cat-platform__head">
                        <span class="icon">📱</span>
                        <h2><?php echo htmlspecialchars($platformName); ?></h2>
                        <span class="platform-count"><?php echo $platformServiceCount; ?> خدمة</span>
                    </header>

                    <?php foreach ($platformServices as $typeKey => $services): ?>
                        <div class="cat-type-section">
                            <h3 class="cat-type"><?php echo htmlspecialchars($typeKey); ?></h3>
                            <div class="cat-grid">
                                <?php foreach ($services as $service):
                                    $price = $service['rate_per_1k_lyd'] ?? $service['rate_per_1k'] ?? $service['rate_per_1k_usd'];
                                    $serviceName = $service['name_ar'] ?? $service['name'] ?? 'خدمة غير محددة';
                                    $serviceId = (int)$service['id'];
                                    $minQty = (int)$service['min'];
                                    $maxQty = (int)$service['max'];
                                    $ordersCount = (int)($service['orders_count'] ?? 0);
                                ?>
                                    <article class="svc">
                                        <h4 class="svc__title"><?php echo htmlspecialchars($serviceName); ?></h4>
                                        <div class="svc__meta">
                                            <span class="badge badge--price">LYD <?php echo number_format((float)$price, 2); ?>/1k</span>
                                            <span class="badge">min <?php echo number_format($minQty); ?></span>
                                            <span class="badge">max <?php echo number_format($maxQty); ?></span>
                                            <?php if ($ordersCount > 0): ?>
                                                <span class="badge"><?php echo number_format($ordersCount); ?> طلب</span>
                                            <?php endif; ?>
                                        </div>
                                        <a class="btn btn--primary" href="/service.php?id=<?php echo $serviceId; ?>">اطلب الآن</a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Basic Catalog Styles */
    .catalog-header {
        background: linear-gradient(135deg, var(--card-bg), var(--elev-bg));
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .catalog-title-section {
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .catalog-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, var(--accent-color), #e6b800);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .catalog-subtitle {
        font-size: 1.2rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .search-context {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        background: var(--elev-bg);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        display: inline-block;
    }

    .search-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .search-term {
        color: var(--accent-color);
        font-weight: 600;
    }

    .catalog-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .stat-item {
        text-align: center;
        padding: 1rem;
        background: var(--elev-bg);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent-color);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .catalog-filters {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    .filters-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .search-input,
    .filter-select {
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--elev-bg);
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.1);
    }

    .quick-filters {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .quick-filter-btn {
        padding: 0.5rem 1rem;
        background: var(--elev-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        color: var(--text-primary);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .quick-filter-btn:hover {
        background: var(--accent-color);
        color: white;
        border-color: var(--accent-color);
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.1);
        border-color: #4CAF50;
        color: #2E7D32;
    }

    .alert-error {
        background: rgba(244, 67, 54, 0.1);
        border-color: #F44336;
        color: #C62828;
    }

    .search-results-header {
        background: var(--elev-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        text-align: center;
    }

    .results-title {
        font-size: 1.5rem;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .results-count {
        font-size: 1rem;
        color: var(--text-secondary);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
    }

    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }

    .empty-title {
        font-size: 1.8rem;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .empty-description {
        font-size: 1.1rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .empty-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .cat-platform {
        margin-block: 20px 28px;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }

    .cat-platform__head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-color);
    }

    .cat-platform__head .icon {
        font-size: 1.5rem;
    }

    .cat-platform__head h2 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        flex: 1;
    }

    .platform-count {
        background: var(--accent-color);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .cat-type-section {
        margin-bottom: 2rem;
    }

    .cat-type {
        margin: 16px 0 12px;
        font-weight: 800;
        font-size: 1.2rem;
        color: var(--text-primary);
        border-inline-start: 4px solid var(--accent-color);
        padding-inline-start: 12px;
        background: linear-gradient(90deg, rgba(201, 162, 39, 0.1), transparent);
        padding: 8px 12px;
        border-radius: 0 8px 8px 0;
    }

    .cat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .svc {
        background: var(--elev-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        display: grid;
        gap: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .svc:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-color: var(--accent-color);
    }

    .svc__title {
        font-weight: 700;
        font-size: 1.1rem;
        line-height: 1.4;
        color: var(--text-primary);
        margin: 0;
        min-height: 2.8em;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .svc__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .badge {
        background: var(--elev-bg);
        border: 1px solid var(--border-color);
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 0.86rem;
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .badge--price {
        background: linear-gradient(180deg, #D6B544, var(--accent-color));
        color: #0E0F12;
        border-color: #B38F1F;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .btn--primary {
        background: linear-gradient(180deg, var(--primary-color), #1976d2);
        color: white;
        border: 1px solid var(--primary-color);
        border-radius: 8px;
        padding: 12px 20px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        display: inline-block;
        justify-self: start;
    }

    .btn--primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4);
        text-decoration: none;
        color: white;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: var(--primary-color);
        color: white;
        border: 1px solid var(--primary-color);
    }

    .btn-secondary {
        background: var(--secondary-color);
        color: white;
        border: 1px solid var(--secondary-color);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        text-decoration: none;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .catalog-title {
            font-size: 2rem;
        }

        .filters-row {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .catalog-stats {
            grid-template-columns: repeat(3, 1fr);
        }

        .cat-platform {
            padding: 1rem;
            margin-block: 16px 20px;
        }

        .cat-platform__head h2 {
            font-size: 1.4rem;
        }

        .cat-type {
            font-size: 1.1rem;
            margin: 12px 0 8px;
            padding: 6px 10px;
        }

        .cat-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .svc {
            padding: 16px;
        }

        .svc__title {
            font-size: 1rem;
            min-height: auto;
        }

        .svc__meta {
            gap: 4px;
        }

        .badge {
            font-size: 0.8rem;
            padding: 3px 8px;
        }

        .btn--primary {
            padding: 10px 16px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 480px) {
        .catalog-header {
            padding: 1.5rem;
        }

        .catalog-title {
            font-size: 1.8rem;
        }

        .catalog-stats {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .stat-item {
            padding: 0.75rem;
        }

        .stat-number {
            font-size: 1.5rem;
        }

        .cat-platform {
            padding: 0.75rem;
        }

        .cat-platform__head {
            gap: 8px;
            margin-bottom: 16px;
        }

        .cat-platform__head h2 {
            font-size: 1.2rem;
        }

        .cat-type {
            font-size: 1rem;
            padding: 5px 8px;
        }

        .svc {
            padding: 12px;
        }

        .svc__meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
    }
</style>

<script defer>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const platformFilter = document.getElementById('platform-filter');
        const typeFilter = document.getElementById('type-filter');
        const sortFilter = document.getElementById('sort-filter');
        const quickFilterBtns = document.querySelectorAll('.quick-filter-btn');

        let searchTimeout;

        // Search with debounce
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    updateCatalog();
                }, 500);
            });
        }

        // Filters
        if (platformFilter) {
            platformFilter.addEventListener('change', updateCatalog);
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', updateCatalog);
        }

        if (sortFilter) {
            sortFilter.addEventListener('change', updateCatalog);
        }

        // Quick filter buttons
        quickFilterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.dataset.filter;
                const value = this.dataset.value;

                if (filter === 'platform') {
                    platformFilter.value = value;
                } else if (filter === 'type') {
                    typeFilter.value = value;
                } else if (filter === 'sort') {
                    sortFilter.value = value;
                }

                updateCatalog();
            });
        });

        function updateCatalog() {
            const params = new URLSearchParams();

            if (searchInput && searchInput.value.trim()) {
                params.set('q', searchInput.value.trim());
            }

            if (platformFilter && platformFilter.value !== 'all') {
                params.set('platform', platformFilter.value);
            }

            if (typeFilter && typeFilter.value !== 'all') {
                params.set('type', typeFilter.value);
            }

            if (sortFilter && sortFilter.value !== 'default') {
                params.set('sort', sortFilter.value);
            }

            const url = window.location.pathname + (params.toString() ? '?' + params.toString() : '');

            // Show loading
            const catalogContent = document.getElementById('catalog-content');
            if (catalogContent) {
                catalogContent.style.opacity = '0.6';
                catalogContent.style.pointerEvents = 'none';
            }

            // Navigate
            window.location.href = url;
        }
    });
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>