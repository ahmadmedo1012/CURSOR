<?php
require_once '../config/config.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

$pageTitle = 'تسجيل دخول الإدارة';
$error = '';

// إذا كان مسجل دخول بالفعل
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        
        $redirect = $_GET['redirect'] ?? '/admin/dashboard.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الإدارة - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/site.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-login-body">
    <div class="admin-login-container">
        <div class="admin-login-background">
            <div class="admin-login-pattern"></div>
        </div>
        
        <div class="admin-login-card">
            <div class="admin-login-header">
                <div class="admin-login-logo">
                    <div class="logo-icon">🎮</div>
                    <h1>GameBox</h1>
                    <p>مركز إدارة أحمد موبايل</p>
                </div>
            </div>
            
            <div class="admin-login-body">
                <?php if ($error): ?>
                    <div class="admin-login-error">
                        <div class="error-icon">⚠️</div>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="admin-login-form">
                    <div class="admin-form-group">
                        <label for="username">اسم المستخدم</label>
                        <div class="admin-input-wrapper">
                            <div class="input-icon">👤</div>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   placeholder="أدخل اسم المستخدم"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="admin-form-group">
                        <label for="password">كلمة المرور</label>
                        <div class="admin-input-wrapper">
                            <div class="input-icon">🔒</div>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   placeholder="أدخل كلمة المرور">
                            <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="admin-login-btn">
                        <span class="btn-text">تسجيل الدخول</span>
                        <span class="btn-icon">🚀</span>
                    </button>
                </form>
                
                <div class="admin-login-footer">
                    <div class="security-notice">
                        <div class="security-icon">🛡️</div>
                        <span>اتصال آمن ومحمي</span>
                    </div>
                    <a href="/" class="back-to-site">
                        ← العودة للموقع الرئيسي
                    </a>
                </div>
            </div>
        </div>
        
        <div class="admin-login-info">
            <div class="info-card">
                <h3>مرحباً بك في لوحة التحكم</h3>
                <p>إدارة شاملة لجميع خدمات GameBox</p>
                
                <div class="info-features">
                    <div class="feature-item">
                        <div class="feature-icon">📊</div>
                        <div class="feature-text">إحصائيات مفصلة</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔔</div>
                        <div class="feature-text">إدارة الإشعارات</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">💰</div>
                        <div class="feature-text">مراقبة المحافظ</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">⚙️</div>
                        <div class="feature-text">إعدادات متقدمة</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body.admin-login-body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1A3C8C 0%, #2c5aa0 50%, #C9A227 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .admin-login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            width: 100%;
            max-width: 1200px;
            padding: 2rem;
            align-items: center;
        }

        .admin-login-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
        }

        .admin-login-pattern {
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .admin-login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 8px 16px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            padding: 3rem;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .admin-login-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .admin-login-logo {
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .admin-login-logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1A3C8C, #C9A227);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .admin-login-logo p {
            color: #666;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .admin-login-error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 8px 16px rgba(255, 107, 107, 0.3);
        }

        .error-icon {
            font-size: 1.5rem;
        }

        .error-message {
            font-weight: 600;
        }

        .admin-login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .admin-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .admin-form-group label {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }

        .admin-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            right: 1rem;
            font-size: 1.2rem;
            color: #666;
            z-index: 2;
            transition: color 0.3s ease;
        }

        .admin-input-wrapper input {
            width: 100%;
            padding: 1rem 3rem 1rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .admin-input-wrapper input:focus {
            outline: none;
            border-color: #C9A227;
            box-shadow: 0 0 0 4px rgba(201, 162, 39, 0.15);
            background: white;
            transform: translateY(-2px);
        }

        .admin-input-wrapper.focused .input-icon {
            color: #C9A227;
        }

        .password-toggle {
            position: absolute;
            left: 1rem;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #C9A227;
        }

        .admin-login-btn {
            background: linear-gradient(135deg, #C9A227, #e6b800);
            color: #000;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 8px 16px rgba(201, 162, 39, 0.3);
            font-family: inherit;
        }

        .admin-login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(201, 162, 39, 0.4);
        }

        .admin-login-btn:active {
            transform: translateY(-1px);
        }

        .btn-icon {
            font-size: 1.2rem;
        }

        .admin-login-footer {
            margin-top: 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .security-notice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .security-icon {
            font-size: 1rem;
        }

        .back-to-site {
            color: #1A3C8C;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .back-to-site:hover {
            color: #C9A227;
            text-decoration: none;
        }

        .admin-login-info {
            position: relative;
            z-index: 1;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .info-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .info-card p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .info-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .feature-icon {
            font-size: 1.5rem;
        }

        .feature-text {
            font-weight: 600;
        }

        /* استجابة للشاشات الصغيرة */
        @media (max-width: 768px) {
            .admin-login-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1rem;
            }

            .admin-login-card {
                padding: 2rem;
            }

            .admin-login-logo h1 {
                font-size: 2rem;
            }

            .info-features {
                grid-template-columns: 1fr;
            }

            .admin-login-info {
                order: -1;
            }
        }

        @media (max-width: 480px) {
            .admin-login-card {
                padding: 1.5rem;
            }

            .admin-login-logo h1 {
                font-size: 1.8rem;
            }

            .logo-icon {
                font-size: 3rem;
            }
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

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // تأثير الكتابة في الحقول
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.admin-input-wrapper input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.parentElement.classList.remove('focused');
                    }
                });
                
                // التحقق من القيم الموجودة
                if (input.value !== '') {
                    input.parentElement.classList.add('focused');
                }
            });

            // تأثير كتابة متحرك للنموذج
            const form = document.querySelector('.admin-login-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const btn = this.querySelector('.admin-login-btn');
                    btn.innerHTML = '<span class="btn-text">جاري التحقق...</span><span class="btn-icon">⏳</span>';
                    btn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>

