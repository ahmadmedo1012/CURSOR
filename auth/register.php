<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();

$pageTitle = 'إنشاء حساب';
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
    // Rate limiting - 3 registration attempts per 15 minutes per IP
    $rateLimitKey = 'register_attempts_' . $_SERVER['REMOTE_ADDR'];
    $currentTime = time();
    $timeWindow = 900; // 15 minutes
    $maxAttempts = 3;

    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }

    // Clean old entries
    if (isset($_SESSION['rate_limits'][$rateLimitKey])) {
        $_SESSION['rate_limits'][$rateLimitKey] = array_filter(
            $_SESSION['rate_limits'][$rateLimitKey],
            function($timestamp) use ($currentTime, $timeWindow) {
                return ($currentTime - $timestamp) < $timeWindow;
            }
        );
    }

    // Check rate limit
    $attempts = $_SESSION['rate_limits'][$rateLimitKey] ?? [];
    if (count($attempts) >= $maxAttempts) {
        error_log("Rate limit exceeded for registration - IP: " . $_SERVER['REMOTE_ADDR']);
        $error = '⏰ تم تجاوز عدد محاولات التسجيل المسموح بها (' . $maxAttempts . ' محاولات كل 15 دقيقة). يرجى الانتظار قبل المحاولة مرة أخرى.';
    } else {
        // Record this attempt
        $_SESSION['rate_limits'][$rateLimitKey][] = $currentTime;
        
        try {
            Auth::requireCsrf();
        } catch (Exception $e) {
            $error = 'خطأ في التحقق من الأمان. يرجى إعادة المحاولة.';
        }
        
        // Normalize and validate inputs
        $phone = isset($_POST['phone']) ? preg_replace('/[^0-9+]/', '', trim($_POST['phone'])) : '';
        $name = isset($_POST['name']) ? mb_substr(trim($_POST['name']), 0, 100, 'UTF-8') : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validation
        if (empty($phone) || empty($name) || empty($password) || empty($confirmPassword)) {
            $error = 'جميع الحقول مطلوبة';
        } elseif (mb_strlen($name, 'UTF-8') < 3) {
            $error = 'الاسم يجب أن يكون 3 أحرف على الأقل';
        } elseif (!preg_match('/^(\+218|218|0)?[0-9]{9}$/', $phone)) {
            $error = 'رقم الهاتف غير صحيح. يجب أن يكون رقم ليبي صحيح';
        } elseif (mb_strlen($password, 'UTF-8') < 6) {
            $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        } elseif ($password !== $confirmPassword) {
            $error = 'كلمات المرور غير متطابقة';
        } else {
            $phone = Auth::normalizePhone($phone);
            $result = Auth::register($phone, $name, $password);
            
            if ($result['success']) {
                // تسجيل دخول تلقائي بعد إنشاء الحساب
                $user = Auth::login($phone, $password);
                if ($user) {
                    $redirect = $_SESSION['redirect_after_login'] ?? '/account/';
                    unset($_SESSION['redirect_after_login']);
                    echo '<script>window.refreshAuthUI && window.refreshAuthUI(); location.replace("' . $redirect . '");</script>';
                    exit;
                }
                $success = 'تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول.';
                // مسح البيانات بعد النجاح
                $_POST = [];
            } else {
                $error = $result['message'];
            }
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 400px; margin: 2rem auto;">
        <div class="card-header">
            <h1 class="card-title">إنشاء حساب جديد</h1>
            <p class="card-subtitle">انضم إلى GameBox واحصل على أفضل الخدمات</p>
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
                <label for="name" class="form-label">الاسم الكامل</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-control" 
                       placeholder="أدخل اسمك الكامل"
                       autocomplete="name"
                       minlength="3"
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                       aria-describedby="name-hint"
                       required>
                <small class="form-text" id="name-hint">الاسم الكامل باللغة العربية أو الإنجليزية</small>
            </div>
            
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
                       placeholder="6 أحرف على الأقل"
                       autocomplete="new-password"
                       minlength="6"
                       aria-describedby="password-hint"
                       required>
                <small class="form-text" id="password-hint">
                    يجب أن تكون 6 أحرف على الأقل
                </small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-control" 
                       placeholder="أعد إدخال كلمة المرور"
                       required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">إنشاء الحساب</button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">لديك حساب بالفعل؟</p>
            <a href="/auth/login.php" class="btn btn-primary">تسجيل الدخول</a>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="/" style="color: var(--text-secondary); text-decoration: none;">
                ← العودة للرئيسية
            </a>
        </div>
    </div>
</div>

<script>
// التحقق من تطابق كلمات المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        showFieldError(this, 'كلمات المرور غير متطابقة');
    } else {
        showFieldError(this, '');
    }
});

document.getElementById('password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password').value;
    if (confirmPassword) {
        document.getElementById('confirm_password').dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
