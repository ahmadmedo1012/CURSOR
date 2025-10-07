<?php
require_once __DIR__ . '/db.php';

class Auth {
    
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // إعدادات كوكي آمنة
            ini_set('session.cookie_httponly', 1);
            // ini_set('session.cookie_secure', 1); // Disabled for HTTP - enable for HTTPS
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax'); // Add SameSite attribute
            
            // إعدادات إضافية للـ session
            ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes
            ini_set('session.gc_maxlifetime', 3600); // 1 hour
            
            session_start();
            
            // Regenerate session ID for security
            if (!isset($_SESSION['session_regenerated'])) {
                session_regenerate_id(true);
                $_SESSION['session_regenerated'] = true;
            }
            
            // Ensure CSRF token exists
            if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
    
    // CSRF Protection
    public static function generateCsrfToken() {
        self::startSession();
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyCsrfToken($token) {
        self::startSession();
        if (!isset($_SESSION['csrf_token']) || !isset($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function csrfField() {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    public static function requireCsrf() {
        // CSRF protection enabled
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::startSession();
            
            $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            
            if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
                http_response_code(403);
                die('طلب غير صالح. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.');
            }
        }
    }
    
    public static function login($phone, $password) {
        try {
            $user = Database::fetchOne(
                "SELECT id, phone, name, password_hash FROM users WHERE phone = ?",
                [$phone]
            );
            
            if (!$user || !self::verifyPassword($password, $user['password_hash'])) {
                return false;
            }
            
            // إنشاء جلسة جديدة
            $sessionToken = self::generateSessionToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            Database::query(
                "INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $sessionToken, $expiresAt]
            );
            
            // حفظ بيانات المستخدم في الجلسة
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['session_token'] = $sessionToken;
            
            return $user;
            
        } catch (Exception $e) {
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
            return false;
        }
    }
    
    public static function logout() {
        self::startSession();
        
        if (isset($_SESSION['session_token'])) {
            try {
                // حذف الجلسة من قاعدة البيانات
                Database::query(
                    "DELETE FROM sessions WHERE session_token = ?",
                    [$_SESSION['session_token']]
                );
            } catch (Exception $e) {
                error_log("خطأ في تسجيل الخروج: " . $e->getMessage());
            }
        }
        
        // تدمير الجلسة
        session_destroy();
        session_start();
    }
    
    public static function register($phone, $name, $password) {
        try {
            // التحقق من وجود الهاتف
            $existing = Database::fetchOne(
                "SELECT id FROM users WHERE phone = ?",
                [$phone]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'رقم الهاتف مسجل مسبقاً'];
            }
            
            // التحقق من صحة رقم الهاتف
            if (!self::isValidPhone($phone)) {
                return ['success' => false, 'message' => 'رقم الهاتف غير صحيح'];
            }
            
            // التحقق من قوة كلمة المرور
            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'];
            }
            
            // إنشاء المستخدم
            $passwordHash = self::hashPassword($password);
            
            Database::query(
                "INSERT INTO users (phone, name, password_hash) VALUES (?, ?, ?)",
                [$phone, $name, $passwordHash]
            );
            
            $userId = Database::lastInsertId();
            
            // إنشاء المحفظة للمستخدم الجديد
            Database::query(
                "INSERT INTO wallets (user_id, balance) VALUES (?, 0)",
                [$userId]
            );
            
            return ['success' => true, 'user_id' => $userId];
            
        } catch (Exception $e) {
            error_log("خطأ في التسجيل: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ في إنشاء الحساب: ' . $e->getMessage()];
        }
    }
    
    public static function currentUser() {
        self::startSession();
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        // التحقق من صحة الجلسة
        try {
            $session = Database::fetchOne(
                "SELECT s.*, u.phone, u.name FROM sessions s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.session_token = ? AND s.expires_at > NOW()",
                [$_SESSION['session_token']]
            );
            
            if (!$session) {
                self::logout();
                return null;
            }
            
            return [
                'id' => $session['user_id'],
                'phone' => $session['phone'],
                'name' => $session['name']
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في التحقق من الجلسة: " . $e->getMessage());
            self::logout();
            return null;
        }
    }
    
    public static function requireLogin() {
        $user = self::currentUser();
        if (!$user) {
            header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        return $user;
    }
    
    public static function is_logged_in(): bool {
        return !!self::currentUser();
    }
    
    public static function isValidPhone($phone) {
        // تنظيف رقم الهاتف
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // التحقق من الصيغة الليبية
        return preg_match('/^(\+218|218|0)?[0-9]{9}$/', $phone);
    }
    
    public static function normalizePhone($phone) {
        // تنظيف وتحويل إلى صيغة موحدة
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // إزالة البادئات المختلفة
        $phone = preg_replace('/^(\+218|218)/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }
        
        // إضافة بادئة +218
        return '+218' . $phone;
    }
    
    public static function updateProfile($userId, $name, $newPassword = null) {
        try {
            if ($newPassword) {
                if (strlen($newPassword) < 6) {
                    return ['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'];
                }
                
                $passwordHash = self::hashPassword($newPassword);
                Database::query(
                    "UPDATE users SET name = ?, password_hash = ? WHERE id = ?",
                    [$name, $passwordHash, $userId]
                );
            } else {
                Database::query(
                    "UPDATE users SET name = ? WHERE id = ?",
                    [$name, $userId]
                );
            }
            
            // تحديث بيانات الجلسة
            $_SESSION['user_name'] = $name;
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("خطأ في تحديث الملف الشخصي: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ في تحديث البيانات'];
        }
    }
}
?>
