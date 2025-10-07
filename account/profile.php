<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$user = Auth::requireLogin();

$pageTitle = 'تعديل الملف الشخصي';
$error = '';
$success = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    
    // Normalize and validate inputs
    $name = isset($_POST['name']) ? mb_substr(trim($_POST['name']), 0, 100, 'UTF-8') : '';
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validation
    if (empty($name)) {
        $error = 'الاسم مطلوب';
    } elseif (mb_strlen($name, 'UTF-8') < 3) {
        $error = 'الاسم يجب أن يكون 3 أحرف على الأقل';
    } elseif (!empty($newPassword)) {
        // تغيير كلمة المرور
        if (empty($currentPassword)) {
            $error = 'كلمة المرور الحالية مطلوبة لتغيير كلمة المرور';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'كلمات المرور الجديدة غير متطابقة';
        } elseif (mb_strlen($newPassword, 'UTF-8') < 6) {
            $error = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل';
        } else {
            // التحقق من كلمة المرور الحالية
            $userData = Database::fetchOne(
                "SELECT password_hash FROM users WHERE id = ?",
                [$user['id']]
            );
            
            if (!Auth::verifyPassword($currentPassword, $userData['password_hash'])) {
                $error = 'كلمة المرور الحالية غير صحيحة';
            } else {
                $result = Auth::updateProfile($user['id'], $name, $newPassword);
                if ($result['success']) {
                    $success = 'تم تحديث الملف الشخصي وكلمة المرور بنجاح';
                    $user['name'] = $name; // تحديث بيانات الجلسة
                } else {
                    $error = $result['message'];
                }
            }
        }
    } else {
        // تحديث الاسم فقط
        $result = Auth::updateProfile($user['id'], $name);
        if ($result['success']) {
            $success = 'تم تحديث الملف الشخصي بنجاح';
            $user['name'] = $name; // تحديث بيانات الجلسة
        } else {
            $error = $result['message'];
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 600px; margin: 2rem auto;">
        <div class="card-header">
            <h1 class="card-title">تعديل الملف الشخصي</h1>
            <p class="card-subtitle">يمكنك تحديث بياناتك الشخصية هنا</p>
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
                       class="form-control" 
                       value="<?php echo htmlspecialchars($user['phone']); ?>"
                       disabled
                       style="background: rgba(255, 255, 255, 0.1); color: var(--text-secondary);">
                <small style="color: var(--text-secondary);">
                    رقم الهاتف غير قابل للتغيير
                </small>
            </div>
            
            <div class="form-group">
                <label for="name" class="form-label">الاسم الكامل</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-control" 
                       placeholder="أدخل اسمك الكامل"
                       value="<?php echo htmlspecialchars($user['name']); ?>"
                       required>
            </div>
            
            <div style="border-top: 1px solid var(--border-color); padding-top: 2rem; margin-top: 2rem;">
                <h3 style="color: var(--accent-color); margin-bottom: 1rem;">تغيير كلمة المرور (اختياري)</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    اترك هذه الحقول فارغة إذا كنت لا تريد تغيير كلمة المرور
                </p>
                
                <div class="form-group">
                    <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-control" 
                           placeholder="أدخل كلمة المرور الحالية">
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control" 
                           placeholder="6 أحرف على الأقل"
                           minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control" 
                           placeholder="أعد إدخال كلمة المرور الجديدة">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 2rem;">
                <button type="submit" class="btn btn-block">حفظ التغييرات</button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <a href="/account/" class="btn btn-primary" style="margin-left: 1rem;">العودة للحساب</a>
            <a href="/auth/logout.php" class="btn" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                تسجيل الخروج
            </a>
        </div>
    </div>
</div>

<script>
// التحقق من تطابق كلمات المرور الجديدة
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        showFieldError(this, 'كلمات المرور غير متطابقة');
    } else {
        showFieldError(this, '');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password').value;
    if (confirmPassword) {
        document.getElementById('confirm_password').dispatchEvent(new Event('input'));
    }
});

// التحقق من كلمة المرور الحالية عند الحاجة
document.getElementById('current_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const currentPassword = this.value;
    
    if (newPassword && !currentPassword) {
        showFieldError(this, 'كلمة المرور الحالية مطلوبة لتغيير كلمة المرور');
    } else {
        showFieldError(this, '');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const currentPassword = document.getElementById('current_password');
    if (this.value && !currentPassword.value) {
        currentPassword.dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
