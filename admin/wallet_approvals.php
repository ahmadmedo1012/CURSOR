<?php
require_once '../config/config.php';
require_once '../src/Utils/db.php';
require_once '../src/Utils/auth.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$pageTitle = 'إدارة طلبات الشحن';
$message = '';
$messageType = '';

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $action = $_POST['action'] ?? '';
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    
    if ($transactionId && in_array($action, ['approve', 'reject'])) {
        try {
            // جلب بيانات المعاملة
            $transaction = Database::fetchOne(
                "SELECT * FROM wallet_transactions WHERE id = ? AND status = 'pending'",
                [$transactionId]
            );
            
            if (!$transaction) {
                throw new Exception('المعاملة غير موجودة أو تمت معالجتها مسبقاً');
            }
            
            if ($action === 'approve') {
                // Safe wallet approval with audit logging
                Database::query("BEGIN");
                
                // التحقق من صحة البيانات
                $formattedAmount = number_format($transaction['amount'], 2, '.', '');
                
                // تحديث حالة المعاملة
                Database::query(
                    "UPDATE wallet_transactions SET status = 'approved' WHERE id = ?",
                    [$transactionId]
                );
                
                // تحديث رصيد المحفظة مع الحساب الآمن
                Database::query(
                    "INSERT INTO wallets (user_id, balance) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE balance = COALESCE(balance, 0) + ?",
                    [$transaction['user_id'] ?? 0, $transaction['amount'] ?? 0, $transaction['amount'] ?? 0]
                );
                
                // سجل التدقيق (append-only audit log)
                $auditLog = sprintf(
                    "[%s] WALLET_APPROVED: Transaction #%d, User %d, Amount %s LYD, Reference: %s, Operator: %s\n",
                    date('Y-m-d H:i:s'),
                    $transactionId,
                    $transaction['user_id'] ?? 0,
                    $formattedAmount,
                    $transaction['reference'] ?? 'N/A',
                    $transaction['operator'] ?? 'N/A'
                );
                
                // كتابة سجل التدقيق في ملف السجل الموجود
                $logFile = '../logs/wallet-audit.log';
                if (!is_dir(dirname($logFile))) {
                    mkdir(dirname($logFile), 0755, true);
                }
                file_put_contents($logFile, $auditLog, FILE_APPEND | LOCK_EX);
                
                Database::query("COMMIT");
                $message = "تم الموافقة على طلب الشحن بقيمة " . $formattedAmount . " LYD وتم إضافة المبلغ للمحفظة";
                $messageType = 'success';
                
                // إضافة رابط مرجعي إلى سجل الأنشطة
                $logViewUrl = "/admin/reports.php?logs=activity&action=" . urlencode('approve_TXN') . "&target=" . $transactionId;
                
            } else {
                // Safe wallet rejection with audit logging
                $formattedAmount = number_format($transaction['amount'], 2, '.', '');
                
                // رفض الشحن مع إضافة سجل تدقيق
                Database::query(
                    "UPDATE wallet_transactions SET status = 'rejected' WHERE id = ?",
                    [$transactionId]
                );
                
                // سجل التدقيق للرفض
                $auditLog = sprintf(
                    "[%s] WALLET_REJECTED: Transaction #%d, User %d, Amount %s LYD, Reference: %s, Operator: %s\n",
                    date('Y-m-d H:i:s'),
                    $transactionId,
                    $transaction['user_id'] ?? 0,
                    $formattedAmount,
                    $transaction['reference'] ?? 'N/A',
                    $transaction['operator'] ?? 'N/A'
                );
                
                // كتابة سجل التدقيق في ملف السجل الموجود
                $logFile = '../logs/wallet-audit.log';
                if (!is_dir(dirname($logFile))) {
                    mkdir(dirname($logFile), 0755, true);
                }
                file_put_contents($logFile, $auditLog, FILE_APPEND | LOCK_EX);
                
                $message = "تم رفض طلب الشحن بقيمة " . $formattedAmount . " LYD";
                $messageType = 'warning';
                
                // إضافة رابط مرجعي إلى سجل الأنشطة
                $logViewUrl = "/admin/reports.php?logs=activity&action=" . urlencode('reject_TXN') . "&target=" . $transactionId;
            }
            
        } catch (Exception $e) {
            if (isset($transaction) && $transaction) {
                Database::query("ROLLBACK");
            }
            $message = "خطأ في معالجة الطلب: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// جلب طلبات الشحن المعلقة
try {
    // Get total count
    $totalCount = Database::fetchOne(
        "SELECT COUNT(*) as count 
         FROM wallet_transactions 
         WHERE status = 'pending' AND type = 'topup'"
    )['count'] ?? 0;
    
    $totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 0;
    
    $pendingRequests = Database::fetchAll(
        "SELECT wt.*, u.name as user_name, u.phone as user_phone 
         FROM wallet_transactions wt 
         LEFT JOIN users u ON wt.user_id = u.id 
         WHERE wt.type = 'topup' AND COALESCE(wt.status, 'pending') = 'pending' 
         ORDER BY wt.created_at ASC
         LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );
    
    // إحصائيات
    $stats = Database::fetchAll(
        "SELECT COALESCE(status, 'pending') as status, COUNT(*) as count, SUM(COALESCE(amount, 0)) as total 
         FROM wallet_transactions 
         WHERE type = 'topup' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY COALESCE(status, 'pending')"
    );
    
    // Enhanced wallet summary data
    $walletStats = Database::fetchOne(
        "SELECT 
            COALESCE(SUM(CASE WHEN type = 'credit' AND status = 'approved' THEN amount END), 0) as total_credited,
            COALESCE(SUM(CASE WHEN type = 'debit' AND status = 'approved' THEN amount END), 0) as total_debited,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as total_last_30d
         FROM wallet_transactions
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        []
    );
    
    // Top users by wallet volume (last 30 days)
    $topUsersByVolume = Database::fetchAll(
        "SELECT 
            u.id, u.phone, u.name,
            COALESCE(SUM(CASE WHEN wt.type = 'credit' AND wt.status = 'approved' THEN wt.amount END), 0) as total_credited,
            COALESCE(SUM(CASE WHEN wt.type = 'debit' AND wt.status = 'approved' THEN wt.amount END), 0) as total_used,
            COUNT(wt.id) as transaction_count
         FROM users u
         LEFT JOIN wallet_transactions wt ON u.id = wt.user_id AND wt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY u.id, u.phone, u.name
         HAVING COALESCE(SUM(CASE WHEN wt.type = 'credit' AND wt.status = 'approved' THEN wt.amount END), 0) > 0 
             OR COALESCE(SUM(CASE WHEN wt.type = 'debit' AND wt.status = 'approved' THEN wt.amount END), 0) > 0
         ORDER BY (COALESCE(SUM(CASE WHEN wt.type = 'credit' AND wt.status = 'approved' THEN wt.amount END), 0) + 
                   COALESCE(SUM(CASE WHEN wt.type = 'debit' AND wt.status = 'approved' THEN wt.amount END), 0)) DESC
         LIMIT 10",
        []
    );

    // Get attachment details for requests that have attachments
    $attachmentData = [];
    
    // Check if wallet_attachments table exists
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'wallet_attachments'");
    
    if ($tableExists) {
        foreach ($pendingRequests as $request) {
            $attachments = Database::fetchAll(
                "SELECT id, filename, file_path, file_type, file_size FROM wallet_attachments WHERE transaction_id = ?",
                [$request['id']]
            );
            if (!empty($attachments)) {
                $attachmentData[$request['id']] = $attachments;
            }
        }
    }
    
} catch (Exception $e) {
    $pendingRequests = [];
    $stats = [];
    $totalCount = 0;
    $totalPages = 0;
    $walletStats = ['total_credited' => 0, 'total_debited' => 0, 'pending_count' => 0, 'total_last_30d' => 0];
    $topUsersByQuantity = [];
    $attachmentData = [];
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = 'error';
}

include '../templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">إدارة طلبات الشحن</h1>
            <p class="card-subtitle">مراجعة وموافقة طلبات شحن المحافظ</p>
            <div class="card-actions">
                <button class="btn btn-primary" onclick="showRewardsModal()">
                    🏆 تنفيذ جوائز الشهر الماضي
                </button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Enhanced Wallet Summary -->
        <div class="wallet-summary-section">
            <div class="summary-grid">
                <!-- 30-day Overview -->
                <div class="summary-card">
                    <div class="summary-icon">💰</div>
                    <div class="summary-content">
                        <h3><?php echo number_format($walletStats['total_credited'] ?? 0, 2); ?> LYD</h3>
                        <p>إجمالي وارد (آخر 30 يوم)</p>
                        <small>معتمد فقط</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">💸</div>
                    <div class="summary-content">
                        <h3><?php echo number_format($walletStats['total_debited'] ?? 0, 2); ?> LYD</h3>
                        <p>إجمالي منصرف (آخر 30 يوم)</p>
                        <small>معتمد فقط</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">⏳</div>
                    <div class="summary-content">
                        <h3><?php echo $walletStats['pending_count'] ?? 0; ?> طلب</h3>
                        <p>طلبات في انتظار المراجعة</p>
                        <small>تتطلب الإجراء</small>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">📊</div>
                    <div class="summary-content">
                        <h3><?php echo $walletStats['total_last_30d'] ?? 0; ?> معاملة</h3>
                        <p>إجمالي المعاملات (آخر 30 يوم)</p>
                        <small>جميع الحالات</small>
                    </div>
                </div>
            </div>
            
            <!-- Top Users Section -->
            <?php if (!empty($topUsersByVolume)): ?>
                <div class="top-users-section">
                    <h4>🔝 أكبر العملاء بالحجم (آخر 30 يوم)</h4>
                    <div class="top-users-grid">
                    
                    <?php foreach (array_slice($topUsersByVolume, 0, 5) as $index => $user): ?>
                        <div class="user-card">
                            <div class="user-rank">#<?php echo $index + 1; ?></div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['name'] ?: $user['username'] ?: $user['phone']); ?></div>
                                <div class="user-meta"><?php echo htmlspecialchars($user['phone']); ?></div>
                            </div>
                            <div class="user-stats">
                                <div class="stat-entry">
                                    <span class="stat-label">وارد:</span>
                                    <span class="stat-value"><?php echo number_format($user['total_credited'], 2); ?> LYD</span>
                                </div>
                                <div class="stat-entry">
                                    <span class="stat-label">منصرف:</span>
                                    <span class="stat-value"><?php echo number_format($user['total_used'], 2); ?> LYD</span>
                                </div>
                                <div class="stat-entry">
                                    <span class="stat-label">عمليات:</span>
                                    <span class="stat-value"><?php echo $user['transaction_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- طلبات الشحن المعلقة -->
        <?php if (!empty($pendingRequests)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">طلبات الشحن المعلقة</h3>
                </div>
                <div class="card-body">
                    <div class="requests-grid-container">
                        <div class="requests-grid">
                            <thead>
                                <tr>
                                    <th scope="col">رقم الطلب</th>
                                    <th scope="col">المستخدم</th>
                                    <th scope="col">المبلغ</th>
                                    <th scope="col">المشغل</th>
                                    <th scope="col">رقم الهاتف</th>
                                    <th scope="col">المرجع</th>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <strong>#<?php echo $request['id']; ?></strong>
                                                <button type="button" class="btn-copy-id" onclick="copyToClipboard('<?php echo $request['id']; ?>', 'تم نسخ رقم الطلب')" title="نسخ رقم الطلب">
                                                    📋
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['user_name']); ?></strong>
                                            <br><small style="color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($request['user_phone']); ?>
                                            </small>
                                        </td>
                                        <td style="color: var(--accent-color); font-weight: bold;">
                                            <?php echo Formatters::formatMoney($request['amount']); ?>
                                        </td>
                                        <td>
                                            <span style="text-transform: capitalize;">
                                                <?php echo htmlspecialchars($request['operator']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($request['reference'] ?: '-'); ?>
                                        </td>
                                        <td>
                                            <?php if ($request['reference']): ?>
                                                <code style="background: rgba(255,255,255,0.1); padding: 0.25rem; border-radius: 3px;">
                                                    <?php echo htmlspecialchars($request['reference']); ?>
                                                </code>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo Formatters::formatDate($request['created_at']); ?>
                                            <br><small style="color: var(--text-secondary);">
                                                <?php echo Formatters::formatTime($request['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" 
                                                        class="btn btn-sm" 
                                                        style="background: var(--success-color); color: white; margin-bottom: 0.5rem;"
                                                        onclick="return confirmAction('هل أنت متأكد من الموافقة على طلب الشحن رقم #<?php echo $request['id']; ?>؟')">
                                                    ✓ موافقة
                                                </button>
                                            </form>
                                            <br>
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" 
                                                        class="btn btn-sm" 
                                                        style="background: var(--error-color); color: white;"
                                                        onclick="return confirmAction('هل أنت متأكد من رفض طلب الشحن رقم #<?php echo $request['id']; ?>؟\n\nهذا الإجراء لا يمكن التراجع عنه.')">
                                                    ✗ رفض
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem 2rem;">
                    <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;">✅</div>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">لا توجد طلبات معلقة</h3>
                    <p style="color: var(--text-secondary);">
                        جميع طلبات الشحن تمت معالجتها
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- روابط إضافية -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="/admin/" class="btn btn-primary">لوحة الإدارة</a>
        </div>
    </div>
</div>

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

// Filter persistence and table management for wallet approvals
document.addEventListener('DOMContentLoaded', function() {
    const screenName = 'wallet_approvals';
    
    // Restore table preferences on load
    const savedPrefs = loadTablePreferences(screenName) || {};
    
    // Save current visible state of actions
    const actionCells = document.querySelectorAll('td:last-child');
    if (savedPrefs.showActions !== undefined && !savedPrefs.showActions) {
        actionCells.forEach(cell => cell.style.display = 'none');
    }
    
    // Set up action visibility toggle
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn btn-sm btn-outline';
    toggleBtn.innerHTML = (savedPrefs.showActions === false ? '👁️ إظهار الإجراءات' : '👁️‍🗨️ إخفاء الإجراءات');
    toggleBtn.onclick = function() {
        savedPrefs.showActions = savedPrefs.showActions !== false;
        actionCells.forEach(cell => cell.style.display = savedPrefs.showActions ? 'table-cell' : 'none');
        toggleBtn.innerHTML = (savedPrefs.showActions ? '👁️‍🗨️ إخفاء الإجراءات' : '👁️ إظهار الإجراءات');
        saveTablePreferences(screenName, savedPrefs);
    };
    
    // Add toggle button to page header
    const pageHeader = document.querySelector('.card-header');
    if (pageHeader) {
        pageHeader.appendChild(toggleBtn);
        toggleBtn.style.marginLeft = 'auto';
        toggleBtn.style.marginTop = '1rem';
    }
    
    // Enhanced confirmation dialogs
    const confirmButtons = document.querySelectorAll('[onclick*="confirmAction"]');
    confirmButtons.forEach(btn => {
        const originalConfirm = btn.onclick;
        btn.onclick = function(e) {
            // Get confirmation message from onclick attribute
            const match = btn.onclick.toString().match(/confirmAction\('([^']+)'\)/);
            if (match && confirmAction(match[1])) {
                return true; // Continue with original action
            }
            return false; // Cancel action
        };
    });
});

// Enhanced wallet management functionality
function confirmWalletAction(action, transactionId, amount) {
    const actionText = action === 'approve' ? 'الموافقة' : 'الرفض';
    const actionIcon = action === 'approve' ? '✅' : '❌';
    const actionDetails = action === 'approve' 
        ? `سيتم إضافة ${amount} LYD للمحفظة`
        : `سيتم رفض طلب الشحن بقيمة ${amount} LYD`;
    
    return confirm(
        `${actionIcon} تأكيد ${actionText}\n\n` +
        `رقم الطلب: #${transactionId}\n` +
        `المبلغ: ${amount} LYD\n\n` +
        `${actionDetails}\n\n` +
        `هل أنت متأكد من تنفيذ هذا الإجراء؟\n` +
        `${
            action === 'approve' 
            ? '⚠️ تحذير: لا يمكن التراجع عن الموافقة' 
            : '⚠️ تحذير: لا يمكن التراجع عن الرفض'
        }`
    );
}

function copyToClipboard(text, message = 'تم النسخ') {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showEnhancedToast(message, 'success');
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
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    showEnhancedToast(message, 'success');
}

function showEnhancedToast(message, type = 'info') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `wallet-toast wallet-toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 4000);
}

// Save current view preferences when action buttons are clicked
document.querySelectorAll('form[method="POST"]').forEach(form => {
    const observer = new MutationObserver(() => {
        const screenName = 'wallet_approvals';
        const currentPrefs = loadTablePreferences(screenName) || {};
        currentPrefs.timestamp = Date.now();
        saveTablePreferences(screenName, currentPrefs);
    });
    
    observer.observe(form, { childList: true, subtree: true });
});
</script>

<!-- Enhanced Wallet Management Styles -->
<style>
/* Wallet Summary Section */
.wallet-summary-section {
    margin-bottom: 2rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.summary-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.summary-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.summary-content p {
    margin: 0 0 0.25rem 0;
    color: var(--text-primary);
    font-weight: 600;
}

.summary-content small {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

/* Top Users Section */
.top-users-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--color-elev);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.top-users-section h4 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.top-users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.user-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1rem;
    border: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
    align-items: center;
}

.user-rank {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.user-stats {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
}

.stat-entry {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
}

.stat-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Enhanced Requests Grid */
.requests-grid-container {
    padding: 0;
}

.requests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1rem;
}

.request-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    position: relative;
}

.request-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.request-id {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.request-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color);
}

.copy-btn-small {
    width: 24px;
    height: 24px;
    border: none;
    background: var(--color-elev);
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    opacity: 0.7;
    transition: all 0.3s ease;
}

.copy-btn-small:hover {
    opacity: 1;
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.attachment-indicator {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Request Body Sections */
.user-section {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-contact,
.user-email {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.125rem;
}

.transaction-section {
    margin-bottom: 1rem;
}

.amount-display {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.amount-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-color);
}

.amount-currency {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.reference-section,
.operator-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.reference-label,
.operator-label {
    color: var(--text-secondary);
}

.reference-value,
.operator-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Attachments Section */
.attachments-section {
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--color-elev);
    border-radius: 8px;
}

.attachments-label {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 0.5rem;
}

.attachment-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem;
    background: var(--card-bg);
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

.attachment-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.file-icon {
    width: 60px;
    height: 60px;
    background: var(--color-elev);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.attachment-name {
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-align: center;
    word-break: break-word;
}

.request-time {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

/* Action Buttons */
.request-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.action-form {
    flex: 1;
}

.action-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.approve-btn {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.approve-btn:hover {
    background: linear-gradient(135deg, #218838, #1ba085);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.decline-btn {
    background: linear-gradient(135deg, #dc3545, #fd7e14);
    color: white;
}

.decline-btn:hover {
    background: linear-gradient(135deg, #c82333, #e0a800);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

/* Toast Notifications */
.wallet-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    background: var(--card-bg);
    color: var(--text-primary);
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 300px;
    animation: slideInFromRight 0.3s ease;
}

.wallet-toast-success {
    border-inline-start: 4px solid #28a745;
}

/* Mobile responsiveness for wallet components */
@media (max-width: 430px) {
    .wallet-toast {
        right: 10px;
        inset-inline-start: 10px;
        min-width: auto;
        width: auto;
        top: 10px;
    }
    
    .requests-grid {
        grid-template-columns: 1fr;
    }
    
    .user-card {
        padding: 0.75rem;
    }
    
    .request-card {
        padding: 1rem;
    }
}

.wallet-toast-error {
    border-inline-start: 4px solid #dc3545;
}

.toast-icon {
    font-size: 1.2rem;
}

.toast-message {
    flex: 1;
    font-weight: 600;
}

.toast-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.toast-close:hover {
    background: var(--color-elev);
    color: var(--text-primary);
}

@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .top-users-grid {
        grid-template-columns: 1fr;
    }
    
    .requests-grid {
        grid-template-columns: 1fr;
    }
    
    .request-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .user-section {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .attachments-grid {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    }
    
    .request-actions {
        flex-direction: column;
    }
    
    .wallet-toast {
        min-width: auto;
        width: calc(100% - 40px);
        right: 20px;
        inset-inline-start: 20px;
    }
}

@media (max-width: 430px) {
    .summary-card {
        padding: 1rem;
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .transaction-section {
        text-align: center;
    }
    
    .reference-section,
    .operator-section {
        flex-direction: column;
        gap: 0.25rem;
        text-align: center;
    }
}
</style>

<script>
// وظائف إدارة جوائز الشهر الماضي
function showRewardsModal() {
    const modal = document.createElement('div');
    modal.id = 'rewardsModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        font-family: Cairo;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        ">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.5rem;">🏆 تنفيذ جوائز الشهر الماضي</h2>
                <p style="margin: 0; color: #666; font-size: 0.9rem;">سيتم منح المكافآت للمراكز الأولى حسب الجدول التالي:</p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; color: #333; font-size: 1.1rem;">جدول المكافآت:</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥇 المركز الأول:</span>
                        <span style="font-weight: 600; color: #ffd700;">40 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥈 المركز الثاني:</span>
                        <span style="font-weight: 600; color: #c0c0c0;">25 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>🥉 المركز الثالث:</span>
                        <span style="font-weight: 600; color: #cd7f32;">10 LYD</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: #f8f9fa; border-radius: 6px;">
                        <span>المراكز 4-7:</span>
                        <span style="font-weight: 600; color: #28a745;">1 LYD لكل مركز</span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button onclick="executeRewards()" style="
                    background: #28a745;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">تنفيذ الجوائز</button>
                <button onclick="closeRewardsModal()" style="
                    background: #6c757d;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.5rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">إلغاء</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeRewardsModal() {
    const modal = document.getElementById('rewardsModal');
    if (modal) {
        modal.remove();
    }
}

function executeRewards() {
    const modal = document.getElementById('rewardsModal');
    if (modal) {
        modal.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
                <h3 style="margin: 0 0 1rem 0; color: #333;">جاري تنفيذ الجوائز...</h3>
                <p style="margin: 0; color: #666;">يرجى الانتظار</p>
            </div>
        `;
    }
    
    // إرسال طلب تنفيذ الجوائز
    fetch('/admin/execute_rewards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'execute_rewards' })
    })
    .then(response => response.json())
    .then(data => {
        closeRewardsModal();
        
        if (data.success) {
            showRewardsResult(data);
        } else {
            // Silent error handling in production
        }
    })
    .catch(error => {
        closeRewardsModal();
        // Silent error handling in production
    });
}

function showRewardsResult(data) {
    const modal = document.createElement('div');
    modal.id = 'rewardsResultModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        font-family: Cairo;
    `;
    
    let processedUsersHtml = '';
    if (data.processed_users && data.processed_users.length > 0) {
        processedUsersHtml = `
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; color: #333; font-size: 1.1rem;">المستخدمون الذين تم منحهم الجوائز:</h3>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px;">
                    ${data.processed_users.map(user => `
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <div>
                                <div style="font-weight: 600;">${user.user_name || 'مستخدم غير معروف'}</div>
                                <div style="font-size: 0.8rem; color: #666;">المركز ${user.rank}</div>
                            </div>
                            <div style="font-weight: 600; color: #28a745;">${user.amount} LYD</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        ">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h2 style="margin: 0 0 0.5rem 0; color: #333; font-size: 1.5rem;">تم تنفيذ الجوائز بنجاح!</h2>
                <p style="margin: 0; color: #666; font-size: 0.9rem;">${data.message}</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #28a745;">${data.processed}</div>
                    <div style="font-size: 0.9rem; color: #666;">مستخدم تم منحه الجائزة</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #007bff;">${data.total_amount}</div>
                    <div style="font-size: 0.9rem; color: #666;">LYD إجمالي المبلغ</div>
                </div>
            </div>
            
            ${processedUsersHtml}
            
            <div style="text-align: center;">
                <button onclick="closeRewardsResultModal()" style="
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 0.75rem 2rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 1rem;
                ">موافق</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closeRewardsResultModal() {
    const modal = document.getElementById('rewardsResultModal');
    if (modal) {
        modal.remove();
    }
}
</script>

<?php include '../templates/partials/footer.php'; ?>
