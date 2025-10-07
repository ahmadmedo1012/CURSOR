<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Services/ProviderManager.php';

Auth::startSession();

$pageTitle = 'إنشاء طلب جديد';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $serviceId = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $target = isset($_POST['target']) ? trim($_POST['target']) : '';
        
        if (empty($target)) {
            throw new Exception('الهدف مطلوب');
        }
        
        // تحديد نوع الهدف
        $isUrl = preg_match('~^https?://~i', $target);
        $link = $isUrl ? $target : '';
        $username = $isUrl ? '' : $target;
        
        // جلب معلومات الخدمة
        $service = Database::fetchOne(
            "SELECT * FROM services_cache WHERE id = ?",
            [$serviceId]
        );
        
        if (!$service) {
            throw new Exception('الخدمة غير موجودة');
        }
        
        // استخدام ProviderManager لإنشاء الطلب
        $providerManager = new ProviderManager();
        $orderResult = $providerManager->createOrder($service['external_id'], $quantity, $link, $username);
        
        if ($orderResult['ok']) {
            // حفظ الطلب في قاعدة البيانات
            Database::query(
                "INSERT INTO orders (external_id, provider, service_id, quantity, target, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $orderResult['order'] ?? uniqid(),
                    $orderResult['provider'] ?? 'unknown',
                    $serviceId,
                    $quantity,
                    $target
                ]
            );
            
            $successMessage = "تم إنشاء الطلب بنجاح! رقم الطلب: " . ($orderResult['order'] ?? 'غير محدد');
        } else {
            throw new Exception($orderResult['error'] ?? 'خطأ في إنشاء الطلب');
        }
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// جلب الخدمات المتاحة
$services = Database::fetchAll(
    "SELECT * FROM services_cache ORDER BY provider, category, name LIMIT 50"
);

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="card-subtitle">إنشاء طلب جديد من أي مزود متاح</p>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-group">
                <label for="service_id">اختر الخدمة:</label>
                <select name="service_id" id="service_id" required>
                    <option value="">-- اختر الخدمة --</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['id']; ?>" 
                                data-provider="<?php echo htmlspecialchars($service['provider']); ?>"
                                data-price="<?php echo $service['rate_per_1k_lyd']; ?>">
                            <?php echo htmlspecialchars($service['name']); ?> 
                            (<?php echo ucfirst($service['provider']); ?>) - 
                            <?php echo number_format($service['rate_per_1k_lyd'], 2); ?> LYD
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="quantity">الكمية:</label>
                <input type="number" name="quantity" id="quantity" required min="1">
            </div>
            
            <div class="form-group">
                <label for="target">الهدف (رابط أو اسم مستخدم):</label>
                <input type="text" name="target" id="target" required 
                       placeholder="https://example.com أو @username">
            </div>
            
            <button type="submit" class="btn btn-primary">إنشاء الطلب</button>
        </form>
    </div>
</div>

<script>
// عرض معلومات المزود عند اختيار الخدمة
document.getElementById('service_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const provider = selectedOption.dataset.provider;
    const price = selectedOption.dataset.price;
    
    if (provider) {
        // Provider and price selected
    }
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>

