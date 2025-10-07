<?php
require_once '../config/config.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

$pageTitle = 'تسجيل دخول الإدارة';
$error = '';

// إذا كان مسجل دخول بالفعل
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/');
    exit;
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        
        $redirect = $_GET['redirect'] ?? '/admin/';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}

include '../templates/partials/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 400px; margin: 4rem auto;">
        <div class="card-header">
            <h1 class="card-title">تسجيل دخول الإدارة</h1>
            <p class="card-subtitle">لوحة تحكم GameBox</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">اسم المستخدم</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           placeholder="أدخل اسم المستخدم"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required 
                           autocomplete="username"
                           aria-describedby="username-error">
                    <div id="username-error" class="form-error"></div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-control" 
                       placeholder="أدخل كلمة المرور"
                       required 
                       autocomplete="current-password"
                       aria-describedby="password-error">
                <div id="password-error" class="form-error"></div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-block">تسجيل الدخول</button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <a href="/" style="color: var(--text-secondary); text-decoration: none;">
                ← العودة للموقع الرئيسي
            </a>
        </div>
    </div>
</div>

<style>
/* Form error messages */
.form-error {
    display: none;
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    padding: 0.375rem 0.5rem;
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
    border-radius: 6px;
}

.form-error.show {
    display: block;
}

.form-control.error {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25);
}

/* Enhanced focus-visible for better accessibility */
input:focus-visible,
button:focus-visible {
    outline: 2px solid #ffc107;
    outline-offset: 2px;
    border-radius: 4px;
}

/* Mobile responsiveness for login page */
@media (max-width: 430px) {
    .container {
        padding: 1rem;
    }
    
    .card {
        margin: 2rem auto;
        max-width: 100%;
    }
    
    .form-control {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .btn {
        font-size: 16px;
        padding: 0.75rem;
    }
}

/* Accessibility improvements */
.form-control:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}
</style>

<script>
// PRODUCTION GUARDS - Disable all debug output
(function() {
    'use strict';
    
    window.__DEBUG__ = false;
    window.__PROD__ = true;
    
    // Disable console methods in production
    if (!window.__DEBUG__) {
        for (const m of ['log','debug','info','warn','table','trace','time','timeEnd']) {
            console[m] = ()=>{};
        }
        if (window.performance) {
            ['mark','measure','clearMarks','clearMeasures'].forEach(k=>{
                if(performance[k]) performance[k]=()=>{};
            });
        }
    }
})();

// Form validation with Arabic error messages
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const usernameError = document.getElementById('username-error');
    const passwordError = document.getElementById('password-error');
    
    // Validation functions
    function validateUsername() {
        const username = usernameInput.value.trim();
        if (username.length === 0) {
            showFieldError(usernameInput, usernameError, 'اسم المستخدم مطلوب');
            return false;
        }
        if (username.length < 3) {
            showFieldError(usernameInput, usernameError, 'اسم المستخدم قصير جداً');
            return false;
        }
        hideFieldError(usernameInput, usernameError);
        return true;
    }
    
    function validatePassword() {
        const password = passwordInput.value;
        if (password.length === 0) {
            showFieldError(passwordInput, passwordError, 'كلمة المرور مطلوبة');
            return false;
        }
        if (password.length < 6) {
            showFieldError(passwordInput, passwordError, 'كلمة المرور قصيرة جداً');
            return false;
        }
        hideFieldError(passwordInput, passwordError);
        return true;
    }
    
    function showFieldError(input, errorElement, message) {
        input.classList.add('error');
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
    
    function hideFieldError(input, errorElement) {
        input.classList.remove('error');
        errorElement.classList.remove('show');
    }
    
    // Event listeners
    usernameInput.addEventListener('blur', validateUsername);
    passwordInput.addEventListener('blur', validatePassword);
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const isUsernameValid = validateUsername();
        const isPasswordValid = validatePassword();
        
        if (isUsernameValid && isPasswordValid) {
            // If validation passes, submit the form
            form.submit();
        } else {
            // Show general error message
            if (window.showToast) {
                window.showToast('يرجى تصحيح الأخطاء قبل المتابعة', 'error');
            }
        }
    });
});
</script>

<?php include '../templates/partials/footer.php'; ?>
