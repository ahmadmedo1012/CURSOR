<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$user = Auth::requireLogin();

$pageTitle = 'المحفظة';
$pageDescription = 'إدارة محفظتك الإلكترونية - عرض الرصيد، تاريخ المعاملات، وشحن المحفظة بسهولة';
$ogType = 'website';

// جلب بيانات المحفظة والمعاملات
try {
    // الرصيد الحالي
    $wallet = Database::fetchOne(
        "SELECT balance FROM wallets WHERE user_id = ?",
        [$user['id']]
    );
    $balance = $wallet ? $wallet['balance'] : 0;
    
    // آخر المعاملات
    $transactions = Database::fetchAll(
        "SELECT * FROM wallet_transactions 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 10",
        [$user['id']]
    );
    
    // إحصائيات سريعة
    $stats = Database::fetchAll(
        "SELECT type, status, COUNT(*) as count, SUM(amount) as total 
         FROM wallet_transactions 
         WHERE user_id = ? 
         GROUP BY type, status",
        [$user['id']]
    );
    
    $statsData = [];
    foreach ($stats as $stat) {
        $key = $stat['type'] . '_' . $stat['status'];
        $statsData[$key] = $stat;
    }
    
} catch (Exception $e) {
    $balance = 0;
    $transactions = [];
    $statsData = [];
    $errorMessage = "خطأ في جلب بيانات المحفظة: " . $e->getMessage();
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav" style="margin-bottom: 1rem;">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item"><a href="/account/">حسابي</a></li>
            <li class="breadcrumb-item active" aria-current="page">المحفظة</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">المحفظة</h1>
            <p class="card-subtitle">إدارة رصيدك والمعاملات المالية</p>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error" style="margin: 1rem 0; padding: 1rem 1.5rem; border-radius: 8px; border-right: 4px solid var(--error-color);">
                <strong>⚠️ خطأ:</strong> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <!-- الرصيد الحالي -->
        <div class="balance-card" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; text-align: center; box-shadow: 0 8px 24px rgba(26, 60, 140, 0.3);">
            <h2 style="color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.25rem; opacity: 0.9;">الرصيد الحالي</h2>
            <div style="font-size: 3.5rem; font-weight: 800; color: var(--accent-color); margin-bottom: 1rem; letter-spacing: -1px;">
                <?php echo Formatters::formatMoney($balance); ?>
            </div>
            <a href="/wallet/topup.php" class="btn btn-lg" style="background: var(--accent-color); color: var(--text-on-accent); min-width: 200px;">
                💰 شحن المحفظة
            </a>
        </div>
        
        <!-- إحصائيات سريعة -->
        <?php if (!empty($statsData)): ?>
            <div class="grid grid-3" style="margin-bottom: 2rem; gap: 1rem;">
                <div style="text-align: center; padding: 1.5rem; background: var(--card-bg); border-radius: 12px; border: 2px solid rgba(40, 167, 69, 0.2);">
                    <h3 style="color: var(--success-color); font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?php echo Formatters::formatMoney($statsData['topup_approved']['total'] ?? 0); ?>
                    </h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">إجمالي الشحنات المنجزة</p>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: var(--card-bg); border-radius: 12px; border: 2px solid rgba(255, 193, 7, 0.2);">
                    <h3 style="color: var(--warning-color); font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?php echo intval($statsData['topup_pending']['count'] ?? 0); ?> <span style="font-size: 1rem; opacity: 0.7;">طلب</span>
                    </h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">في الانتظار</p>
                </div>
                
                <div style="text-align: center; padding: 1.5rem; background: var(--card-bg); border-radius: 12px; border: 2px solid rgba(26, 60, 140, 0.2);">
                    <h3 style="color: var(--primary-color); font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?php echo count($transactions); ?> <span style="font-size: 1rem; opacity: 0.7;">معاملة</span>
                    </h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">إجمالي المعاملات</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- آخر المعاملات -->
        <?php if (!empty($transactions)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">آخر المعاملات</h3>
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table class="table wallet-transactions-table" role="table" aria-label="جدول المعاملات المالية">
                            <thead>
                                <tr>
                                    <th scope="col" style="text-align: right; min-width: 100px;">النوع</th>
                                    <th scope="col" style="text-align: right; min-width: 140px;">المبلغ</th>
                                    <th scope="col" style="text-align: center; min-width: 100px;">المشغل</th>
                                    <th scope="col" style="text-align: center; min-width: 120px;">الحالة</th>
                                    <th scope="col" style="text-align: center; min-width: 140px;">التاريخ</th>
                                    <th scope="col" style="text-align: center; min-width: 120px;">المرجع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td style="text-align: right; font-weight: 600;">
                                            <?php if ($transaction['type'] === 'topup'): ?>
                                                <span style="color: var(--success-color); display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    <span style="font-size: 1.25rem;">💰</span> شحن
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--error-color); display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    <span style="font-size: 1.25rem;">💸</span> خصم
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; font-weight: 700; font-size: 1.1rem; <?php echo $transaction['type'] === 'topup' ? 'color: var(--success-color);' : 'color: var(--error-color);'; ?>">
                                            <?php echo ($transaction['type'] === 'topup' ? '+' : '-') . Formatters::formatMoney($transaction['amount']); ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($transaction['operator']): ?>
                                                <span style="text-transform: capitalize; padding: 0.25rem 0.75rem; background: rgba(201, 162, 39, 0.15); border-radius: 6px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($transaction['operator']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); opacity: 0.5;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $statusClass = 'warning';
                                            $statusText = $transaction['status'];
                                            $statusIcon = '⏳';
                                            
                                            switch ($transaction['status']) {
                                                case 'approved':
                                                    $statusClass = 'success';
                                                    $statusText = 'مقبول';
                                                    $statusIcon = '✓';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'error';
                                                    $statusText = 'مرفوض';
                                                    $statusIcon = '✕';
                                                    break;
                                                default:
                                                    $statusText = 'انتظار';
                                            }
                                            ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; background: var(--<?php echo $statusClass; ?>-color); color: <?php echo $statusClass === 'warning' ? 'var(--text-on-accent)' : 'white'; ?>; padding: 0.4rem 0.75rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                                                <span><?php echo $statusIcon; ?></span> <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">
                                                <?php echo Formatters::formatDate($transaction['created_at']); ?>
                                            </div>
                                            <small style="color: var(--text-secondary); font-size: 0.85rem;">
                                                <?php echo Formatters::formatTime($transaction['created_at']); ?>
                                            </small>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($transaction['reference']): ?>
                                                <code style="background: rgba(201, 162, 39, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; color: var(--accent-color); font-weight: 600;">
                                                    <?php echo htmlspecialchars($transaction['reference']); ?>
                                                </code>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); opacity: 0.5;">-</span>
                                            <?php endif; ?>
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
                    <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;">💰</div>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">لا توجد معاملات بعد</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        ابدأ بشحن محفظتك لاستخدام الخدمات بسهولة
                    </p>
                    <a href="/wallet/topup.php" class="btn btn-lg">شحن المحفظة</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- معلومات مهمة -->
        <div class="alert alert-info" style="margin-top: 2rem;">
            <h4 style="color: var(--accent-color); margin-bottom: 1rem;">معلومات مهمة</h4>
            <ul style="padding-right: 1.5rem;">
                <li>يمكنك شحن محفظتك عبر خدمة ليبيانا أو مدار</li>
                <li>طلبات الشحن تحتاج موافقة إدارية قبل إضافة المبلغ</li>
                <li>يمكنك استخدام رصيدك لدفع تكلفة الطلبات</li>
                <li>جميع المعاملات محفوظة ويمكنك مراجعتها في أي وقت</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
