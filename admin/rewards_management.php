<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Services/MonthlyLeaderboardService.php';
require_once BASE_PATH . '/src/Services/MonthlyRewardsService.php';

Auth::startSession();

// التحقق من تسجيل الدخول كمدير
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'إدارة جوائز المتصدرين';
$pageDescription = 'إدارة جوائز أفضل المستخدمين الشهرية';

// جلب البيانات
$currentMonthLeaderboard = MonthlyLeaderboardService::getCurrentMonthLeaderboard(10);
$previousMonthLeaderboard = MonthlyLeaderboardService::getPreviousMonthLeaderboard(10);

// جلب معلومات آخر تنفيذ للجوائز
$lastRewardExecution = MonthlyRewardsService::getLastRewardExecution();
$canExecuteRewards = MonthlyRewardsService::canExecuteRewards();

// جلب عدد الجوائز الموزعة من آخر تنفيذ
$lastRewardsCount = 0;
$lastRewardsAmount = 0;
if ($lastRewardExecution) {
    try {
        $prevMonth = date('Y-m', strtotime('first day of last month'));
        $result = Database::fetchAll(
            "SELECT COUNT(*) as count, SUM(amount) as total 
             FROM wallet_transactions 
             WHERE reference LIKE ? AND type = 'credit'",
            ["TOP-{$prevMonth}-%"]
        );
        
        if ($result && count($result) > 0) {
            $lastRewardsCount = (int)$result[0]['count'];
            $lastRewardsAmount = (float)$result[0]['total'];
        }
    } catch (Exception $e) {
        // تجاهل الخطأ
    }
}

// معالجة تنفيذ الجوائز
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_rewards') {
    if (!$canExecuteRewards) {
        $message = 'تم تنفيذ جوائز الشهر الماضي مسبقاً';
        $messageType = 'warning';
    } else {
        try {
            $result = MonthlyRewardsService::processPreviousMonthRewards($_SESSION['admin_id'] ?? null);
            
            if ($result['success']) {
                $message = sprintf(
                    'تم تنفيذ الجوائز بنجاح! تم معالجة %d مستخدم بإجمالي %.2f LYD',
                    $result['processed'],
                    $result['total_amount']
                );
                $messageType = 'success';
                
                // تحديث البيانات بعد التنفيذ
                $lastRewardExecution = MonthlyRewardsService::getLastRewardExecution();
                $canExecuteRewards = MonthlyRewardsService::canExecuteRewards();
                
                // إعادة حساب عدد الجوائز
                $prevMonth = date('Y-m', strtotime('first day of last month'));
                $result = Database::fetchAll(
                    "SELECT COUNT(*) as count, SUM(amount) as total 
                     FROM wallet_transactions 
                     WHERE reference LIKE ? AND type = 'credit'",
                    ["TOP-{$prevMonth}-%"]
                );
                
                if ($result && count($result) > 0) {
                    $lastRewardsCount = (int)$result[0]['count'];
                    $lastRewardsAmount = (float)$result[0]['total'];
                }
            } else {
                $message = 'خطأ في تنفيذ الجوائز: ' . $result['message'];
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'حدث خطأ: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">🏆 إدارة جوائز المتصدرين</h1>
        <p class="page-subtitle">إدارة جوائز أفضل المستخدمين الشهرية</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- معلومات آخر تنفيذ -->
    <div class="rewards-info-card">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">آخر تنفيذ</div>
                <div class="info-value">
                    <?php if ($lastRewardExecution): ?>
                        <?php echo date('Y-m-d H:i:s', strtotime($lastRewardExecution)); ?>
                    <?php else: ?>
                        لم يتم التنفيذ بعد
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">الجوائز الموزعة</div>
                <div class="info-value"><?php echo $lastRewardsCount; ?> جائزة</div>
            </div>
            <div class="info-item">
                <div class="info-label">إجمالي المبلغ</div>
                <div class="info-value"><?php echo number_format($lastRewardsAmount, 2); ?> LYD</div>
            </div>
            <div class="info-item">
                <div class="info-label">الحالة</div>
                <div class="info-value">
                    <?php if ($canExecuteRewards): ?>
                        <span class="status-badge status-ready">جاهز للتنفيذ</span>
                    <?php else: ?>
                        <span class="status-badge status-completed">تم التنفيذ</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($canExecuteRewards): ?>
        <div class="execute-section">
            <button class="btn btn-primary btn-lg" onclick="showExecuteModal()">
                🏆 تنفيذ جوائز الشهر الماضي
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- جداول المتصدرين -->
    <div class="leaderboards-section">
        <!-- أفضل 10 هذا الشهر -->
        <div class="leaderboard-card">
            <div class="card-header">
                <h2 class="card-title">📊 أفضل 10 — هذا الشهر</h2>
                <p class="card-subtitle">ترتيب مباشر لشهر <?php echo date('F Y'); ?></p>
            </div>
            <div class="card-body">
                <?php if (empty($currentMonthLeaderboard)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h3>لا توجد بيانات بعد</h3>
                    <p>الترتيب يتحدث أثناء الشهر. كن أول من يظهر في القائمة!</p>
                </div>
                <?php else: ?>
                <div class="leaderboard-table">
                    <div class="table-header">
                        <div class="col-rank">الترتيب</div>
                        <div class="col-user">المستخدم</div>
                        <div class="col-amount">المبلغ المنفق</div>
                        <div class="col-transactions">المعاملات</div>
                    </div>
                    <?php foreach ($currentMonthLeaderboard as $index => $user): ?>
                    <div class="table-row">
                        <div class="col-rank">
                            <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                <?php if ($index + 1 == 1): ?>
                                    <span class="medal gold">🥇</span>
                                <?php elseif ($index + 1 == 2): ?>
                                    <span class="medal silver">🥈</span>
                                <?php elseif ($index + 1 == 3): ?>
                                    <span class="medal bronze">🥉</span>
                                <?php else: ?>
                                    <span class="rank-number">#<?php echo $index + 1; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-user">
                            <div class="user-name"><?php echo htmlspecialchars($user['user_name'] ?: 'مستخدم غير معروف'); ?></div>
                            <div class="user-contact"><?php echo htmlspecialchars($user['user_phone']); ?></div>
                        </div>
                        <div class="col-amount">
                            <div class="amount-value"><?php echo number_format($user['spent'], 2); ?> LYD</div>
                        </div>
                        <div class="col-transactions">
                            <div class="transaction-count"><?php echo $user['transaction_count']; ?> معاملة</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- فائزون الشهر الماضي -->
        <div class="leaderboard-card">
            <div class="card-header">
                <h2 class="card-title">🏆 فائزون الشهر الماضي</h2>
                <p class="card-subtitle">النتائج النهائية لشهر <?php echo date('F Y', strtotime('first day of last month')); ?></p>
            </div>
            <div class="card-body">
                <?php if (empty($previousMonthLeaderboard)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <h3>لا توجد بيانات</h3>
                    <p>لم يتم العثور على نتائج للشهر الماضي</p>
                </div>
                <?php else: ?>
                <div class="leaderboard-table">
                    <div class="table-header">
                        <div class="col-rank">الترتيب</div>
                        <div class="col-user">المستخدم</div>
                        <div class="col-amount">المبلغ المنفق</div>
                        <div class="col-transactions">المعاملات</div>
                    </div>
                    <?php foreach ($previousMonthLeaderboard as $index => $user): ?>
                    <div class="table-row">
                        <div class="col-rank">
                            <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                <?php if ($index + 1 == 1): ?>
                                    <span class="medal gold">🥇</span>
                                <?php elseif ($index + 1 == 2): ?>
                                    <span class="medal silver">🥈</span>
                                <?php elseif ($index + 1 == 3): ?>
                                    <span class="medal bronze">🥉</span>
                                <?php else: ?>
                                    <span class="rank-number">#<?php echo $index + 1; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-user">
                            <div class="user-name"><?php echo htmlspecialchars($user['user_name'] ?: 'مستخدم غير معروف'); ?></div>
                            <div class="user-contact"><?php echo htmlspecialchars($user['user_phone']); ?></div>
                        </div>
                        <div class="col-amount">
                            <div class="amount-value"><?php echo number_format($user['spent'], 2); ?> LYD</div>
                        </div>
                        <div class="col-transactions">
                            <div class="transaction-count"><?php echo $user['transaction_count']; ?> معاملة</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- نافذة تأكيد التنفيذ -->
<div id="executeModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🏆 تأكيد تنفيذ الجوائز</h3>
            <button class="modal-close" onclick="closeExecuteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>هل أنت متأكد من تنفيذ جوائز الشهر الماضي؟</p>
            <div class="reward-table">
                <h4>جدول المكافآت:</h4>
                <div class="reward-item">
                    <span>🥇 المركز الأول:</span>
                    <span class="reward-amount">40.00 LYD</span>
                </div>
                <div class="reward-item">
                    <span>🥈 المركز الثاني:</span>
                    <span class="reward-amount">25.00 LYD</span>
                </div>
                <div class="reward-item">
                    <span>🥉 المركز الثالث:</span>
                    <span class="reward-amount">10.00 LYD</span>
                </div>
                <div class="reward-item">
                    <span>المراكز 4-7:</span>
                    <span class="reward-amount">1.00 LYD لكل مركز</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeExecuteModal()">إلغاء</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="execute_rewards">
                <button type="submit" class="btn btn-primary">تنفيذ الجوائز</button>
            </form>
        </div>
    </div>
</div>

<style>
/* تنسيقات صفحة إدارة الجوائز */
.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem 0;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, var(--accent-color), #e6b800);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    font-size: 1.2rem;
    color: var(--text-secondary);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid;
}

.alert-success {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-warning {
    background: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

.alert-error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

/* بطاقة معلومات الجوائز */
.rewards-info-card {
    background: var(--color-card);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-item {
    text-align: center;
}

.info-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-ready {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #cce5ff;
    color: #004085;
}

.execute-section {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid var(--color-border);
}

/* قسم الجداول */
.leaderboards-section {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

.leaderboard-card {
    background: var(--color-card);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
    padding: 1.5rem 2rem;
}

.card-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.card-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 0;
}

.card-body {
    padding: 0;
}

/* جدول المتصدرين */
.leaderboard-table {
    width: 100%;
}

.table-header {
    display: grid;
    grid-template-columns: 80px 1fr 120px 100px;
    background: var(--color-elev);
    padding: 1rem 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 1px solid var(--color-border);
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-row {
    display: grid;
    grid-template-columns: 80px 1fr 120px 100px;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.2s ease;
}

.table-row:hover {
    background: var(--color-elev);
}

.table-row:last-child {
    border-bottom: none;
}

.col-rank {
    display: flex;
    align-items: center;
    justify-content: center;
}

.col-user {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.col-amount {
    display: flex;
    align-items: center;
    justify-content: end;
}

.col-transactions {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* شارات الترتيب */
.rank-badge {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--color-elev);
    border: 2px solid var(--color-border);
}

.medal {
    font-size: 1.2rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.medal.gold {
    color: #ffd700;
}

.medal.silver {
    color: #c0c0c0;
}

.medal.bronze {
    color: #cd7f32;
}

.rank-number {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* معلومات المستخدم */
.user-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-contact {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.amount-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--accent-color);
}

.transaction-count {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* حالة فارغة */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

/* النافذة المنبثقة */
.modal {
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
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
    color: #333;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 2rem;
}

.reward-table {
    margin-top: 1rem;
}

.reward-table h4 {
    margin: 0 0 1rem 0;
    color: #333;
}

.reward-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.reward-item:last-child {
    border-bottom: none;
}

.reward-amount {
    font-weight: 600;
    color: var(--accent-color);
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid #eee;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* تحسينات للهواتف المحمولة */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .info-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .leaderboards-section {
        gap: 1.5rem;
    }
    
    .table-header,
    .table-row {
        grid-template-columns: 60px 1fr 100px 80px;
        padding: 0.75rem 1rem;
    }
    
    .rank-badge {
        width: 35px;
        height: 35px;
    }
    
    .medal {
        font-size: 1rem;
    }
    
    .user-name {
        font-size: 0.9rem;
    }
    
    .amount-value {
        font-size: 1rem;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .table-header,
    .table-row {
        grid-template-columns: 50px 1fr 80px 60px;
        padding: 0.5rem 0.75rem;
    }
    
    .rank-badge {
        width: 30px;
        height: 30px;
    }
    
    .medal {
        font-size: 0.9rem;
    }
    
    .rank-number {
        font-size: 0.8rem;
    }
    
    .user-name {
        font-size: 0.8rem;
    }
    
    .user-contact {
        font-size: 0.7rem;
    }
    
    .amount-value {
        font-size: 0.9rem;
    }
    
    .transaction-count {
        font-size: 0.7rem;
    }
}
</style>

<script>
function showExecuteModal() {
    document.getElementById('executeModal').style.display = 'flex';
}

function closeExecuteModal() {
    document.getElementById('executeModal').style.display = 'none';
}

// إغلاق النافذة عند النقر خارجها
document.getElementById('executeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeExecuteModal();
    }
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>


