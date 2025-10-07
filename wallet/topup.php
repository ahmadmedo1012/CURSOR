<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$user = Auth::requireLogin();

$pageTitle = 'شحن المحفظة';
$error = '';
$success = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting - 3 topup requests per 10 minutes per session/IP
    $rateLimitKey = 'topup_attempts_' . (session_id() ?: $_SERVER['REMOTE_ADDR']);
    $currentTime = time();
    $timeWindow = 600; // 10 minutes
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
        error_log("Rate limit exceeded for wallet topup - IP: " . $_SERVER['REMOTE_ADDR'] . " - Session: " . session_id());
        $error = '⏰ تم تجاوز عدد المحاولات المسموح بها (' . $maxAttempts . ' طلبات كل 10 دقائق). يرجى الانتظار قبل المحاولة مرة أخرى.';
    } else {
        // Record this attempt
        $_SESSION['rate_limits'][$rateLimitKey][] = $currentTime;
        
        Auth::requireCsrf();
    
    // Normalize and validate inputs
    $operator = isset($_POST['operator']) ? trim($_POST['operator']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $phone = isset($_POST['phone']) ? preg_replace('/[^0-9+]/', '', trim($_POST['phone'])) : '';
    $reference = isset($_POST['reference']) ? mb_substr(trim($_POST['reference']), 0, 100, 'UTF-8') : '';
    $notes = isset($_POST['notes']) ? mb_substr(trim($_POST['notes']), 0, 500, 'UTF-8') : '';
    
    // Validation
    if (empty($operator) || $amount <= 0 || empty($phone)) {
        $error = '❌ جميع الحقول المطلوبة يجب ملؤها';
    } elseif (!in_array($operator, ['libyana', 'madar'], true)) {
        $error = '⚠️ المشغل المحدد غير صحيح';
    } elseif ($amount < 35 || $amount > 1000) {
        $error = '💰 المبلغ يجب أن يكون بين ' . Formatters::formatMoney(35) . ' و ' . Formatters::formatMoney(1000);
    } elseif (!preg_match('/^(\+218|218|0)?[0-9]{9}$/', $phone)) {
        $error = '📱 رقم الهاتف غير صحيح. يجب أن يكون رقم ليبي صحيح';
    } else {
        try {
            // إنشاء طلب شحن
            Database::query(
                "INSERT INTO wallet_transactions (user_id, type, amount, operator, reference, status) VALUES (?, 'topup', ?, ?, ?, 'pending')",
                [$user['id'], $amount, $operator, $reference]
            );
            
            $transactionId = Database::lastInsertId();
            
            $success = "✅ تم إنشاء طلب الشحن بنجاح! رقم الطلب: #{$transactionId}";
            
            // مسح البيانات بعد النجاح
            $_POST = [];
            
        } catch (Exception $e) {
            error_log("Wallet topup error: " . $e->getMessage());
            $error = "خطأ في إنشاء طلب الشحن. يرجى المحاولة لاحقاً.";
        }
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 600px; margin: 2rem auto;">
        <div class="card-header">
            <h1 class="card-title">شحن المحفظة</h1>
            <p class="card-subtitle">أدخل تفاصيل عملية الشحن</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 1rem 0; padding: 1rem 1.5rem; border-radius: 8px; border-right: 4px solid var(--error-color); display: flex; align-items: start; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <strong style="display: block; margin-bottom: 0.25rem;">خطأ في العملية</strong>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin: 1rem 0; padding: 1rem 1.5rem; border-radius: 8px; border-right: 4px solid var(--success-color); display: flex; align-items: start; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">✅</span>
                <div style="flex: 1;">
                    <strong style="display: block; margin-bottom: 0.25rem; color: var(--success-color);">تم بنجاح!</strong>
                    <span><?php echo htmlspecialchars($success); ?></span>
                    
                    <!-- تفاصيل عملية التحويل -->
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(40, 167, 69, 0.2); background: rgba(40, 167, 69, 0.05); border-radius: 8px; padding: 1rem;">
                        <h4 style="color: var(--success-color); margin-bottom: 0.75rem; font-size: 1rem;">🎯 خطوات التحويل:</h4>
                        <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: center;">
                            <div style="color: var(--text-primary); font-weight: 600;">
                                <?php echo htmlspecialchars($_POST['operator'] ?? ''); ?> 📱
                            </div>
                            <button type="button" class="copy-btn copy-operator" onclick="copyOperatorNumber('<?php echo htmlspecialchars($_POST['operator'] ?? ''); ?>')" title="نسخ رقم <?php echo htmlspecialchars($_POST['operator'] ?? ''); ?>">
                                📋 نسخ الرقم
                            </button>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: center; margin-top: 0.5rem;">
                            <div style="color: var(--text-primary); font-weight: 600;">
                                <?php echo Formatters::formatMoney($_POST['amount'] ?? 0); ?>
                            </div>
                            <button type="button" class="copy-btn copy-amount" onclick="copyAmount('<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>')" title="نسخ المبلغ">
                                💰 نسخ المبلغ
                            </button>
                        </div>
                        
                        <!-- رسالة التحويل المقترحة -->
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(40, 167, 69, 0.2);">
                            <strong style="color: var(--success-color); display: block; margin-bottom: 0.5rem;">💬 رسالة التحويل:</strong>
                            <div style="background: var(--color-card); padding: 0.75rem; border-radius: 6px; font-style: italic; color: var(--text-primary); direction: ltr; text-align: center; font-family: monospace;">
                                Transfer <?php echo Formatters::formatMoney($_POST['amount'] ?? 0); ?> to <?php echo htmlspecialchars($_POST['operator'] ?? ''); ?> - Order #<?php echo $transactionId ?? ''; ?>
                            </div>
                            <div style="text-align: center; margin-top: 0.5rem;">
                                <button type="button" class="copy-btn copy-message" onclick="copyTransferMessage('<?php echo htmlspecialchars($_POST['operator'] ?? ''); ?>', '<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>', '<?php echo $transactionId ?? ''; ?>')" title="نسخ رسالة التحويل">
                                    📝 نسخ الرسالة
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(40, 167, 69, 0.2);">
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">
                            يرجى الانتظار حتى تتم مراجعة طلبك من قبل الإدارة. سيتم إشعارك عند إضافة المبلغ لمحفظتك.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-validate>
            <?php echo Auth::csrfField(); ?>
            <div class="form-group">
                <label for="operator" class="form-label">مشغل الاتصالات</label>
                <select id="operator" name="operator" class="form-control" required>
                    <option value="">اختر المشغل</option>
                    <option value="libyana" <?php echo ($_POST['operator'] ?? '') === 'libyana' ? 'selected' : ''; ?>>ليبيانا</option>
                    <option value="madar" <?php echo ($_POST['operator'] ?? '') === 'madar' ? 'selected' : ''; ?>>مدار</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="amount" class="form-label">المبلغ (LYD)</label>
                <input type="number" 
                       id="amount" 
                       name="amount" 
                       class="form-control" 
                       placeholder="100"
                       min="35"
                       max="1000"
                       step="0.01"
                       inputmode="decimal"
                       value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                       aria-describedby="amount-hint"
                       required>
                <small class="form-text" id="amount-hint" style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                    <span style="color: var(--text-secondary);">💵 الحد الأدنى: <strong style="color: var(--accent-color);"><?php echo Formatters::formatMoney(35); ?></strong></span>
                    <span style="color: var(--text-secondary);">💰 الحد الأقصى: <strong style="color: var(--accent-color);"><?php echo Formatters::formatMoney(1000); ?></strong></span>
                </small>
            </div>
            
            <div class="form-group">
                <label for="phone" class="form-label">رقم الهاتف المحوّل منه</label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       class="form-control" 
                       placeholder="0912345678"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                       required>
                <small style="color: var(--text-secondary);">
                    رقم الهاتف الذي تم إجراء عملية التحويل منه
                </small>
            </div>
            
            <div class="form-group">
                <label for="reference" class="form-label">رقم المرجع/الكود</label>
                <input type="text" 
                       id="reference" 
                       name="reference" 
                       class="form-control" 
                       placeholder="رقم المرجع من رسالة التحويل"
                       value="<?php echo htmlspecialchars($_POST['reference'] ?? ''); ?>">
                <small style="color: var(--text-secondary);">
                    رقم المرجع أو الكود من رسالة تأكيد التحويل (اختياري)
                </small>
            </div>
            
            <div class="form-group">
                <label for="notes" class="form-label">ملاحظات إضافية</label>
                <textarea id="notes" 
                          name="notes" 
                          class="form-control" 
                          rows="3"
                          placeholder="أي ملاحظات إضافية..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-block">إرسال طلب الشحن</button>
            </div>
        </form>
        
        <!-- معلومات مهمة -->
        <div class="alert alert-warning" style="margin-top: 2rem;">
            <h4 style="color: var(--warning-color); margin-bottom: 1rem;">تعليمات مهمة</h4>
            <ol style="padding-right: 1.5rem;">
                <li>قم بإجراء عملية التحويل إلى أحد الأرقام التالية:</li>
                <ul style="margin: 1rem 0; padding-right: 1.5rem;">
                    <li><strong>ليبيانا:</strong> 0912345678</li>
                    <li><strong>مدار:</strong> 0923456789</li>
                </ul>
                <li>احتفظ برقم المرجع من رسالة تأكيد التحويل</li>
                <li>أدخل تفاصيل التحويل في النموذج أعلاه</li>
                <li>سيتم مراجعة طلبك وإضافة المبلغ خلال 24 ساعة</li>
                <li>ستتلقى إشعاراً عند الموافقة على طلبك</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <a href="/wallet/" class="btn btn-primary" style="margin-left: 1rem;">العودة للمحفظة</a>
            <a href="/account/" class="btn">حسابي</a>
        </div>
    </div>
</div>

<script>
'use strict';

// Copy functions for wallet topup
function copyOperatorNumber(operator) {
    const numbers = {
        'libyana': '0912345678',
        'madar': '0923456789'
    };
    const number = numbers[operator] || 'غير محدد';
    copyToClipboard(number, `تم نسخ رقم ${operator}: ${number}`);
}

function copyAmount(amount) {
    const formattedAmount = parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    copyToClipboard(formattedAmount, `تم نسخ المبلغ: ${formattedAmount} LYD`);
}

function copyTransferMessage(operator, amount, orderId) {
    const formattedAmount = parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const message = `Transfer ${formattedAmount} LYD to ${operator} - Order #${orderId}`;
    copyToClipboard(message, 'تم نسخ رسالة التحويل');
}

function copyToClipboard(text, message) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message, 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text, message);
        });
    } else {
        fallbackCopyToClipboard(text, message);
    }
}

function fallbackCopyToClipboard(text, message) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast(message, 'success');
    } catch (err) {
        showToast('فشل في النسخ', 'error');
    }
    
    document.body.removeChild(textArea);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 1000;
        font-weight: 600;
        animation: slideInOut 3s ease-in-out;
        max-width: 350px;
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// Rate limiting check for form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[data-validate]');
    if (form) {
        form.addEventListener('submit', function(e) {
            // إضافة تأكيد المراجعة للمستخدم
            const operator = document.getElementById('operator').value;
            const amount = document.getElementById('amount').value;
            
            if (operator && amount) {
                const numbers = {
                    'libyana': '0912345678',
                    'madar': '0923456789'
                };
                
                const confirmMessage = `البحث عن عملية التحويل:
🔸 المشغل: ${operator}
🔸 المبلغ: ${amount} LYD
🔸 الرقم المستهدف: ${numbers[operator] || 'غير محدد'}

هل أكدت تنفيذ عملية التحويل في تطبيق المحفظة؟`;
                
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    showToast('تم إلغاء الطلب', 'warning');
                    return false;
                }
            }
        });
    }
});
</script>

<style>
/* Copy Buttons for Wallet */
.copy-btn {
    background: transparent;
    border: 1px solid rgba(40, 167, 69, 0.3);
    color: var(--success-color);
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
}

.copy-btn:hover {
    background: var(--success-color);
    color: white;
    border-color: var(--success-color);
    transform: translateY(-1px);
}

.copy-operator {
    border-color: rgba(26, 60, 140, 0.3);
    color: var(--primary-color);
}

.copy-operator:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.copy-amount {
    border-color: rgba(201, 162, 39, 0.3);
    color: var(--accent-color);
}

.copy-amount:hover {
    background: var(--accent-color);
    border-color: var(--accent-color);
}

.copy-message {
    border-color: rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

.copy-message:hover {
    background: #3b82f6;
    border-color: #3b82f6;
}

/* Success Alert Improvements */
.alert-success .copy-btn {
    border-color: rgba(255, 255, 255, 0.4);
    color: rgba(255, 255, 255, 0.9);
}

.alert-success .copy-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border-color: rgba(255, 255, 255, 0.6);
}

/* Toast Animations */
@keyframes slideInOut {
    0% { transform: translateX(100%); opacity: 0; }
    10%, 90% { transform: translateX(0); opacity: 1; }
    100% { transform: translateX(100%); opacity: 0; }
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .copy-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        gap: 0.375rem;
    }
    
    .alert-success div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
    }
    
    .alert-success .copy-btn {
        justify-self: center;
        justify-content: center;
    }
}

/* RTL Support */
[dir="rtl"] .copy-btn {
    direction: rtl;
}

[dir="rtl"] .toast {
    right: auto;
    left: 20px;
}

/* Enhanced Form Validation Messages */
.form-control:invalid:not(:placeholder-shown) {
    border-color: var(--error-color);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15);
}

.form-control:valid:not(:placeholder-shown) {
    border-color: var(--success-color);
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15);
}
</style>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
