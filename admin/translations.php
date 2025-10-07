<?php
require_once '../config/config.php';
require_once '../src/Utils/db.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$pageTitle = 'ترجمة الخدمات';
$message = '';
$messageType = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = intval($_POST['service_id'] ?? 0);
    $nameAr = trim($_POST['name_ar'] ?? '');
    $categoryAr = trim($_POST['category_ar'] ?? '');
    $descriptionAr = trim($_POST['description_ar'] ?? '');
    
    if ($serviceId && ($nameAr || $categoryAr || $descriptionAr)) {
        try {
            // حفظ في جدول الترجمات
            Database::query(
                "INSERT INTO service_translations (service_id, name_ar, category_ar, description_ar) 
                 VALUES (?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 name_ar = VALUES(name_ar), 
                 category_ar = VALUES(category_ar), 
                 description_ar = VALUES(description_ar)",
                [$serviceId, $nameAr ?: null, $categoryAr ?: null, $descriptionAr ?: null]
            );
            
            // تحديث جدول الخدمات مباشرة
            $updateFields = [];
            $params = [];
            
            if ($nameAr) {
                $updateFields[] = "name_ar = ?";
                $params[] = $nameAr;
            }
            if ($categoryAr) {
                $updateFields[] = "category_ar = ?";
                $params[] = $categoryAr;
            }
            if ($descriptionAr) {
                $updateFields[] = "description_ar = ?";
                $params[] = $descriptionAr;
            }
            
            if (!empty($updateFields)) {
                $params[] = $serviceId;
                Database::query(
                    "UPDATE services_cache SET " . implode(', ', $updateFields) . " WHERE id = ?",
                    $params
                );
            }
            
            $message = "تم حفظ الترجمة بنجاح";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "خطأ في حفظ الترجمة: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "يرجى اختيار خدمة وإدخال ترجمة واحدة على الأقل";
        $messageType = 'error';
    }
}

// البحث عن الخدمات
$searchQuery = $_GET['search'] ?? '';
$services = [];

if (!empty($searchQuery)) {
    try {
        $services = Database::fetchAll(
            "SELECT s.*, st.name_ar, st.category_ar, st.description_ar 
             FROM services_cache s 
             LEFT JOIN service_translations st ON s.id = st.service_id 
             WHERE s.name LIKE ? OR s.category LIKE ? 
             ORDER BY s.name 
             LIMIT 20",
            ["%{$searchQuery}%", "%{$searchQuery}%"]
        );
    } catch (Exception $e) {
        $message = "خطأ في البحث: " . $e->getMessage();
        $messageType = 'error';
    }
}

include '../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">ترجمة الخدمات</h1>
            <p class="card-subtitle">إضافة وتعيين الترجمات العربية للخدمات</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- نموذج البحث -->
        <form method="GET" style="margin-bottom: 2rem;">
            <div class="form-group">
                <label for="search" class="form-label">البحث عن الخدمة</label>
                <div style="display: flex; gap: 1rem;">
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="form-control" 
                           placeholder="ابحث بالاسم أو الفئة..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn">بحث</button>
                </div>
            </div>
        </form>
        
        <!-- نتائج البحث -->
        <?php if (!empty($services)): ?>
            <div class="services-list">
                <?php foreach ($services as $service): ?>
                    <div class="card" style="margin-bottom: 1rem;">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="card-subtitle"><?php echo htmlspecialchars($service['category']); ?></p>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                
                                <div class="grid grid-2">
                                    <div class="form-group">
                                        <label for="name_ar_<?php echo $service['id']; ?>" class="form-label">الاسم العربي</label>
                                        <input type="text" 
                                               id="name_ar_<?php echo $service['id']; ?>" 
                                               name="name_ar" 
                                               class="form-control" 
                                               placeholder="الاسم باللغة العربية"
                                               value="<?php echo htmlspecialchars($service['name_ar'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="category_ar_<?php echo $service['id']; ?>" class="form-label">الفئة العربية</label>
                                        <input type="text" 
                                               id="category_ar_<?php echo $service['id']; ?>" 
                                               name="category_ar" 
                                               class="form-control" 
                                               placeholder="الفئة باللغة العربية"
                                               value="<?php echo htmlspecialchars($service['category_ar'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description_ar_<?php echo $service['id']; ?>" class="form-label">الوصف العربي</label>
                                    <textarea id="description_ar_<?php echo $service['id']; ?>" 
                                              name="description_ar" 
                                              class="form-control" 
                                              rows="3"
                                              placeholder="الوصف باللغة العربية"><?php echo htmlspecialchars($service['description_ar'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success">حفظ الترجمة</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($searchQuery)): ?>
            <div class="alert alert-warning">
                <p>لم يتم العثور على خدمات تطابق البحث: "<?php echo htmlspecialchars($searchQuery); ?>"</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p>استخدم نموذج البحث أعلاه للعثور على الخدمات التي تريد ترجمتها</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="/admin/" class="btn btn-primary">العودة للوحة الإدارة</a>
        </div>
    </div>
</div>

<?php include '../templates/partials/footer.php'; ?>
