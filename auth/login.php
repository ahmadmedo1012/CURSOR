<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();

$pageTitle = 'تسجيل الدخول';
$error = '';
$success = '';

// إذا كان المستخدم مسجل دخول بالفعل
$user = Auth::currentUser();
if ($user) {
    header('Location: /account/');
    exit;
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    
    // Normalize and validate inputs
    $phone = isset($_POST['phone']) ? preg_replace('/[^0-9+]/', '', trim($_POST['phone'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validation
    if (empty($phone) || empty($password)) {
        $error = 'جميع الحقول مطلوبة';
    } elseif (mb_strlen($password, 'UTF-8') < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $phone = Auth::normalizePhone($phone);
        $user = Auth::login($phone, $password);
        
        if ($user) {
            // إعادة التوجيه بعد تسجيل الدخول
            $redirect = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? '/account/';
            unset($_SESSION['redirect_after_login']); // حذف الرابط المحفوظ
            echo '<script>window.refreshAuthUI && window.refreshAuthUI(); location.replace("' . $redirect . '");</script>';
            exit;
        } else {
            $error = 'رقم الهاتف أو كلمة المرور غير صحيحة';
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 400px; margin: 2rem auto;">
        <div class="card-header">
            <h1 class="card-title">تسجيل الدخول</h1>
            <p class="card-subtitle">أدخل بياناتك للوصول لحسابك</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-validate>
            <?php echo Auth::csrfField(); ?>
            <div class="form-group">
                <label for="phone" class="form-label">رقم الهاتف</label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       class="form-control" 
                       placeholder="+218912345678 أو 0912345678"
                       inputmode="tel"
                       autocomplete="tel"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                       aria-describedby="phone-hint"
                       required>
                <small class="form-text" id="phone-hint">
                    يمكنك إدخال الرقم مع أو بدون +218
                </small>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-control" 
                       placeholder="أدخل كلمة المرور"
                       autocomplete="current-password"
                       minlength="6"
                       required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">تسجيل الدخول</button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">ليس لديك حساب؟</p>
            <a href="/auth/register.php" class="btn btn-primary">إنشاء حساب جديد</a>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="/" style="color: var(--text-secondary); text-decoration: none;">
                ← العودة للرئيسية
            </a>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
