<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Utils/db.php';
require_once __DIR__ . '/../src/Services/TranslationService.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'إعداد نظام الخدمات المتقدم';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['setup_advanced'])) {
            // تشغيل migration تحسين الخدمات
            $migrationFile = __DIR__ . '/../database/010_improve_services.sql';
            
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($stmt) {
                        return !empty($stmt) && !preg_match('/^--/', $stmt);
                    }
                );
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        try {
                            Database::query($statement);
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'already exists') === false && 
                                strpos($e->getMessage(), 'Duplicate') === false) {
                                throw $e;
                            }
                        }
                    }
                }
                
                $results[] = "✅ تم إضافة الأعمدة الجديدة للخدمات";
            }
            
            $results[] = "🎉 تم إعداد نظام الخدمات المتقدم بنجاح!";
            
        } elseif (isset($_POST['translate_all'])) {
            // ترجمة جميع الخدمات
            $translatedCount = TranslationService::translateAllServices();
            $results[] = "✅ تم ترجمة {$translatedCount} خدمة تلقائياً";
            
        } elseif (isset($_POST['update_descriptions'])) {
            // تحديث أوصاف الخدمات من API
            require_once __DIR__ . '/../src/Services/PeakerrClient.php';
            
            $peakerr = new PeakerrClient();
            $apiServices = $peakerr->getServices();
            
            $updatedCount = 0;
            
            if (is_array($apiServices)) {
                foreach ($apiServices as $apiService) {
                    $externalId = $apiService['service'] ?? $apiService['id'] ?? '';
                    $description = $apiService['description'] ?? $apiService['desc'] ?? '';
                    
                    if (!empty($externalId) && !empty($description)) {
                        // ترجمة الوصف
                        $descriptionAr = TranslationService::translateServiceDescription($description);
                        
                        // تحديث في قاعدة البيانات
                        Database::query(
                            "UPDATE services_cache SET description = ?, description_ar = ? WHERE external_id = ?",
                            [$description, $descriptionAr, $externalId]
                        );
                        
                        $updatedCount++;
                    }
                }
            }
            
            $results[] = "✅ تم تحديث أوصاف {$updatedCount} خدمة من API";
            
        } elseif (isset($_POST['update_subcategories'])) {
            // تحديث التصنيفات الفرعية
            $services = Database::fetchAll("SELECT id, name, category FROM services_cache");
            
            $updatedCount = 0;
            foreach ($services as $service) {
                $subcategory = TranslationService::extractSubcategory($service['name'], $service['category']);
                
                Database::query(
                    "UPDATE services_cache SET subcategory = ? WHERE id = ?",
                    [$subcategory, $service['id']]
                );
                
                $updatedCount++;
            }
            
            $results[] = "✅ تم تحديث التصنيفات الفرعية لـ {$updatedCount} خدمة";
        }
        
    } catch (Exception $e) {
        $results[] = "❌ خطأ: " . $e->getMessage();
    }
}

// إحصائيات الخدمات
$stats = Database::fetchOne("
    SELECT 
        COUNT(*) as total_services,
        COUNT(CASE WHEN name_ar IS NOT NULL AND name_ar != '' THEN 1 END) as translated_names,
        COUNT(CASE WHEN description IS NOT NULL AND description != '' THEN 1 END) as with_descriptions,
        COUNT(CASE WHEN description_ar IS NOT NULL AND description_ar != '' THEN 1 END) as translated_descriptions,
        COUNT(CASE WHEN subcategory IS NOT NULL AND subcategory != '' THEN 1 END) as with_subcategories
    FROM services_cache
");

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="card-subtitle">إعداد نظام الخدمات المتقدم مع الترجمة التلقائية</p>
        </div>
        
        <div class="card-body">
            <?php if (!empty($results)): ?>
                <div class="alert alert-info">
                    <h3>نتائج العملية:</h3>
                    <ul>
                        <?php foreach ($results as $result): ?>
                            <li><?php echo htmlspecialchars($result); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- إحصائيات الخدمات -->
            <div class="grid grid-2 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3>إحصائيات الخدمات</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>إجمالي الخدمات:</strong> <?php echo number_format($stats['total_services']); ?></p>
                        <p><strong>الأسماء المترجمة:</strong> <?php echo number_format($stats['translated_names']); ?></p>
                        <p><strong>مع أوصاف:</strong> <?php echo number_format($stats['with_descriptions']); ?></p>
                        <p><strong>أوصاف مترجمة:</strong> <?php echo number_format($stats['translated_descriptions']); ?></p>
                        <p><strong>مع تصنيفات فرعية:</strong> <?php echo number_format($stats['with_subcategories']); ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>حالة الترجمة</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $translationProgress = $stats['total_services'] > 0 ? ($stats['translated_names'] / $stats['total_services']) * 100 : 0;
                        $descriptionProgress = $stats['total_services'] > 0 ? ($stats['translated_descriptions'] / $stats['total_services']) * 100 : 0;
                        ?>
                        <div class="progress-item">
                            <label>ترجمة الأسماء:</label>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $translationProgress; ?>%"></div>
                            </div>
                            <span><?php echo number_format($translationProgress, 1); ?>%</span>
                        </div>
                        
                        <div class="progress-item">
                            <label>ترجمة الأوصاف:</label>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $descriptionProgress; ?>%"></div>
                            </div>
                            <span><?php echo number_format($descriptionProgress, 1); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- إعداد النظام -->
            <form method="POST" class="mb-4">
                <div class="form-group">
                    <h3>إعداد النظام</h3>
                    <p>إضافة الأعمدة الجديدة للخدمات (الوصف، التصنيف الفرعي، إلخ)</p>
                </div>
                
                <button type="submit" name="setup_advanced" class="btn btn-primary">
                    إعداد النظام المتقدم
                </button>
            </form>
            
            <!-- ترجمة الخدمات -->
            <form method="POST" class="mb-4">
                <div class="form-group">
                    <h3>ترجمة الخدمات</h3>
                    <p>ترجمة جميع أسماء وتصنيفات الخدمات من الإنجليزية إلى العربية تلقائياً</p>
                </div>
                
                <button type="submit" name="translate_all" class="btn btn-accent">
                    ترجمة جميع الخدمات
                </button>
            </form>
            
            <!-- تحديث الأوصاف -->
            <form method="POST" class="mb-4">
                <div class="form-group">
                    <h3>تحديث الأوصاف</h3>
                    <p>جلب أوصاف الخدمات من API وترجمتها تلقائياً</p>
                </div>
                
                <button type="submit" name="update_descriptions" class="btn btn-info">
                    تحديث الأوصاف من API
                </button>
            </form>
            
            <!-- تحديث التصنيفات الفرعية -->
            <form method="POST" class="mb-4">
                <div class="form-group">
                    <h3>تحديث التصنيفات الفرعية</h3>
                    <p>استخراج وتحديث التصنيفات الفرعية للخدمات (متابعين، إعجابات، إلخ)</p>
                </div>
                
                <button type="submit" name="update_subcategories" class="btn btn-warning">
                    تحديث التصنيفات الفرعية
                </button>
            </form>
            
            <div class="mt-4">
                <h3>الخطوات التالية:</h3>
                <ol>
                    <li>قم بإعداد النظام المتقدم</li>
                    <li>ترجمة جميع الخدمات</li>
                    <li>تحديث الأوصاف من API</li>
                    <li>تحديث التصنيفات الفرعية</li>
                    <li>اذهب إلى <a href="/catalog_new.php">الصفحة الجديدة</a> لرؤية النتائج</li>
                </ol>
            </div>
            
            <div class="mt-4">
                <h3>الميزات الجديدة:</h3>
                <div class="grid grid-2">
                    <div>
                        <h4>للإدارة:</h4>
                        <ul>
                            <li>ترجمة تلقائية بدون قاموس</li>
                            <li>جلب أوصاف من API</li>
                            <li>تصنيفات فرعية ذكية</li>
                            <li>فلترة متقدمة</li>
                            <li>ترتيب متعدد الخيارات</li>
                        </ul>
                    </div>
                    <div>
                        <h4>للعملاء:</h4>
                        <ul>
                            <li>بحث سريع ودقيق</li>
                            <li>فلترة حسب السعر</li>
                            <li>فلترة حسب التصنيف</li>
                            <li>ترتيب متقدم</li>
                            <li>عرض تفاصيل الخدمات</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.progress-item label {
    min-width: 120px;
    font-weight: 500;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: rgba(201, 162, 39, 0.2);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-color), #e6b800);
    transition: width 0.3s ease;
}

.progress-item span {
    min-width: 40px;
    text-align: center;
    font-weight: 600;
    color: var(--accent-color);
}
</style>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

