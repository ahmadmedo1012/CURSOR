<?php
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Services/PeakerrClient.php';

// حماية بالـsession (مثل باقي admin)
Auth::startSession();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$balance = null;
$error = '';

try {
    $client = new PeakerrClient();
    // محاولة جلب معلومات الحساب
    $response = $client->getBalance();
    
    if ($response['ok']) {
        if (isset($response['balance'])) {
            $balance = $response['balance'];
        } elseif (isset($response['funds'])) {
            $balance = $response['funds'];
        } elseif (isset($response['raw'])) {
            $balance = $response['raw'];
        } else {
            $balance = 'معلومات غير متاحة';
        }
    } else {
        $error = $response['error'] ?? 'خطأ غير معروف';
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">فحص رصيد المزوّد</h2>
            <p class="card-subtitle">فحص الرصيد المتاح في حساب Peakerr API</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>خطأ:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($balance !== null): ?>
            <div class="alert alert-success">
                <strong>الرصيد المتاح:</strong> 
                <?php 
                if (is_numeric($balance)) {
                    echo '$' . number_format($balance, 2);
                } else {
                    echo htmlspecialchars($balance);
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="mt-3">
            <h4>معلومات مهمة:</h4>
            <ul>
                <li><strong>مشكلة "Not enough funds":</strong> يعني أن رصيدك في Peakerr API غير كافي لإتمام الطلب</li>
                <li><strong>الحل:</strong> يجب تعبئة رصيد حسابك في Peakerr API</li>
                <li><strong>ملاحظة:</strong> هذا الرصيد منفصل عن رصيد المحفظة في التطبيق</li>
            </ul>
        </div>

        <div class="mt-3">
            <a href="api_diagnostics.php" class="btn btn-primary">تشخيص API</a>
            <a href="index.php" class="btn">العودة للوحة التحكم</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/partials/footer.php'; ?>
