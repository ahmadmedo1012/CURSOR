<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';

$pageTitle = 'طلب خدمة';
$pageDescription = 'تفاصيل الخدمة وطلب خدمات وسائل التواصل الاجتماعي والألعاب';
$ogType = 'product';

// التحقق من وجود معرف الخدمة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /catalog.php');
    exit;
}

$serviceId = intval($_GET['id']);

// جلب بيانات الخدمة
try {
    $service = Database::fetchOne(
        "SELECT * FROM services_cache WHERE id = ?",
        [$serviceId]
    );
    
    if (!$service) {
        header('Location: /catalog.php');
        exit;
    }
    
    // تحديث عنوان الصفحة بناءً على الخدمة
    $pageTitle = ($service['name_ar'] ?? $service['name']);
    $pageDescription = 'تفاصيل خدمة ' . ($service['name_ar'] ?? $service['name']) . ' - ' . ($service['category_ar'] ?? $service['category']);
} catch (Exception $e) {
    $errorMessage = "خطأ في جلب بيانات الخدمة: " . $e->getMessage();
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav" style="margin-bottom: 1rem;">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item"><a href="/catalog.php">الخدمات</a></li>
            <li class="breadcrumb-item"><span class="breadcrumb-ellipsis">...</span></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($service['name_ar'] ?: $service['name']); ?></li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo htmlspecialchars($service['name_ar'] ?: $service['name']); ?></h1>
            <p class="card-subtitle">أدخل تفاصيل طلبك</p>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <strong>⚠️ خطأ:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                <br><a href="/catalog.php" class="btn btn-secondary" style="margin-top: 1rem;">← العودة للخدمات</a>
            </div>
        <?php endif; ?>
        
        <!-- معلومات الخدمة -->
        <div class="alert alert-info" style="background: rgba(26, 60, 140, 0.2); border-color: var(--primary-color); color: #b8c5d6;">
            <h4 style="color: var(--accent-color); margin-bottom: 1rem;">معلومات الخدمة</h4>
            <div class="grid grid-2">
                    <div>
                        <p><strong>الفئة:</strong> <?php echo htmlspecialchars($service['category_ar'] ?: $service['category']); ?></p>
                    <p><strong>السعر لكل 1000:</strong> <span style="color: var(--accent-color);">
                        <?php 
                        $lyd = isset($service['rate_per_1k_lyd']) && $service['rate_per_1k_lyd'] !== null
                            ? (float)$service['rate_per_1k_lyd']
                            : ((float)($service['rate_per_1k'] ?? 0) * EXCHANGE_USD_TO_LYD);
                        echo Formatters::formatMoney($lyd); 
                        ?>
                    </span></p>
                </div>
                <div>
                    <p><strong>الحد الأدنى:</strong> <?php echo Formatters::formatQuantity($service['min']); ?></p>
                    <p><strong>الحد الأقصى:</strong> <?php echo Formatters::formatQuantity($service['max']); ?></p>
                </div>
            </div>
            <?php if (!empty($service['description'])): ?>
                <p style="margin-top: 1rem;"><strong>الوصف:</strong> <?php echo htmlspecialchars($service['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- نموذج الطلب -->
        <form method="POST" action="/order.php">
            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
            
            <div class="form-group">
                <label for="quantity" class="form-label">الكمية المطلوبة</label>
                <input type="number" 
                       id="quantity" 
                       name="quantity" 
                       class="form-control" 
                       min="<?php echo $service['min']; ?>" 
                       max="<?php echo $service['max']; ?>" 
                       value="<?php echo $service['min']; ?>"
                       required>
                <small style="color: var(--text-secondary);">
                    الحد الأدنى: <?php echo number_format($service['min']); ?> | الحد الأقصى: <?php echo number_format($service['max']); ?>
                </small>
            </div>
            
            <div class="form-group">
                <label for="target" class="form-label">الرابط أو اسم المستخدم</label>
                <input type="text" 
                       id="target" 
                       name="target" 
                       class="form-control" 
                       placeholder="https://example.com أو @username" 
                       required>
                <small style="color: var(--text-secondary);">
                    أدخل الرابط الكامل أو اسم المستخدم حسب نوع الخدمة
                </small>
            </div>
            
            <!-- حساب السعر -->
            <div class="alert" style="background: rgba(201, 162, 39, 0.2); border-color: var(--accent-color); color: #fff3cd;">
                <h4 style="color: var(--accent-color); margin-bottom: 1rem;">حساب السعر</h4>
                <div id="price-calculation">
                    <p>السعر لكل 1000: <span id="rate-display"><?php echo Formatters::formatMoney($lyd); ?></span></p>
                    <p>الكمية: <span id="quantity-display"><?php echo Formatters::formatQuantity($service['min']); ?></span></p>
                    <p><strong>إجمالي السعر: <span id="total-price" style="color: var(--accent-color); font-size: 1.2rem;"><?php echo Formatters::formatMoneyCompact($lyd * $service['min'] / 1000); ?></span> LYD</strong></p>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-block">تأكيد الطلب</button>
            </div>
        </form>
        
        <!-- ملاحظات مهمة -->
        <div class="alert alert-warning">
            <h4 style="color: var(--warning-color); margin-bottom: 1rem;">ملاحظات مهمة</h4>
            <ul style="padding-right: 1.5rem;">
                <li>تأكد من صحة الرابط أو اسم المستخدم قبل تأكيد الطلب</li>
                <li>الأسعار محسوبة بالدينار الليبي (LYD)</li>
                <li>سيتم بدء تنفيذ الطلب فور تأكيد الدفع</li>
                <li>يمكنك تتبع حالة طلبك من صفحة تتبع الطلبات</li>
            </ul>
        </div>
    </div>
</div>

<script>
// حساب السعر التلقائي
document.getElementById('quantity').addEventListener('input', function() {
    const quantity = parseInt(this.value) || 0;
    const rate = parseFloat(<?php echo json_encode($lyd); ?>) || 0;
    const totalPrice = (rate * quantity / 1000);
    
    if (isNaN(totalPrice)) {
        console.error('Invalid price calculation:', { quantity, rate, totalPrice });
        return;
    }
    
    document.getElementById('quantity-display').textContent = quantity.toLocaleString('en-US');
    document.getElementById('total-price').textContent = totalPrice.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' LYD';
});

// التحقق من صحة الكمية
document.getElementById('quantity').addEventListener('change', function() {
    const quantity = parseInt(this.value);
    const min = parseInt(<?php echo json_encode($service['min']); ?>) || 1;
    const max = parseInt(<?php echo json_encode($service['max']); ?>) || 1000000;
    
    if (quantity < min) {
        this.value = min;
        this.dispatchEvent(new Event('input'));
    } else if (quantity > max) {
        this.value = max;
        this.dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
