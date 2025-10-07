<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Services/ProviderManager.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'مزامنة المزودين المتعددين';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $providerManager = new ProviderManager();
    $providers = $_POST['providers'] ?? [];
    
    foreach ($providers as $providerName) {
        try {
            $provider = $providerManager->getProvider($providerName);
            if (!$provider) {
                $results[$providerName] = ['success' => false, 'message' => 'مزود غير موجود'];
                continue;
            }
            
            // جلب الخدمات من المزود
            $services = $provider->getServices();
            
            if ($services['ok']) {
                // حذف الخدمات القديمة لهذا المزود
                Database::query("DELETE FROM services_cache WHERE provider = ?", [$providerName]);
                
                $addedCount = 0;
                foreach ($services as $service) {
                    if (is_array($service) && isset($service['id'])) {
                        // إضافة معرف المزود
                        $service['provider'] = $providerName;
                        
                        // إدراج الخدمة
                        Database::query(
                            "INSERT INTO services_cache (external_id, provider, name, category, rate_per_1k, min, max, type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $service['id'] ?? $service['service'] ?? uniqid(),
                                $providerName,
                                $service['name'] ?? $service['service_name'] ?? 'خدمة غير محددة',
                                $service['category'] ?? $service['type'] ?? 'عام',
                                floatval($service['rate'] ?? $service['price_per_1k'] ?? 0),
                                intval($service['min'] ?? $service['minimum'] ?? 0),
                                intval($service['max'] ?? $service['maximum'] ?? 0),
                                $service['type'] ?? $service['service_type'] ?? 'عام',
                                $service['description'] ?? $service['desc'] ?? ''
                            ]
                        );
                        $addedCount++;
                    }
                }
                
                $results[$providerName] = [
                    'success' => true, 
                    'message' => "تم إضافة $addedCount خدمة بنجاح"
                ];
            } else {
                $results[$providerName] = [
                    'success' => false, 
                    'message' => $services['error'] ?? 'خطأ غير معروف'
                ];
            }
        } catch (Exception $e) {
            $results[$providerName] = [
                'success' => false, 
                'message' => $e->getMessage()
            ];
        }
    }
}

// جلب المزودين المتاحين
$providerManager = new ProviderManager();
$availableProviders = $providerManager->getAvailableProviders();

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="card-subtitle">مزامنة الخدمات من مزودين متعددين</p>
        </div>
        
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>اختر المزودين للمزامنة:</label>
                    <?php foreach ($availableProviders as $provider): ?>
                        <div class="checkbox-group">
                            <input type="checkbox" name="providers[]" value="<?php echo htmlspecialchars($provider); ?>" id="provider_<?php echo $provider; ?>">
                            <label for="provider_<?php echo $provider; ?>"><?php echo ucfirst($provider); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn btn-primary">بدء المزامنة</button>
            </form>
            
            <?php if (!empty($results)): ?>
                <div class="mt-4">
                    <h3>نتائج المزامنة:</h3>
                    <?php foreach ($results as $provider => $result): ?>
                        <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
                            <strong><?php echo ucfirst($provider); ?>:</strong> 
                            <?php echo htmlspecialchars($result['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

