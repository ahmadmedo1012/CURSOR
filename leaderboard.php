<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Utils/db.php';

Auth::startSession();

$pageTitle = '🏆 المتصدرون';
$pageDescription = 'أكبر المستخدمين إنفاقاً هذا الشهر - احصل على جوائز شهرية';

// جلب البيانات مباشرة من قاعدة البيانات
try {
    $currentMonth = date('Y-m');
    $monthStart = date('Y-m-01 00:00:00');
    $monthEnd = date('Y-m-01 00:00:00', strtotime('+1 month'));
    
    // جلب أكبر المستخدمين إنفاقاً
    $sql = "SELECT 
                wt.user_id,
                u.name as user_name,
                u.phone as user_phone,
                SUM(ABS(wt.amount)) as spent,
                COUNT(wt.id) as transactions_count
            FROM wallet_transactions wt
            JOIN users u ON wt.user_id = u.id
            WHERE wt.type = 'deduct'
                AND wt.status = 'approved'
                AND wt.created_at >= ? 
                AND wt.created_at < ?
            GROUP BY wt.user_id
            ORDER BY spent DESC
            LIMIT 10";
    
    $leaderboardData = Database::fetchAll($sql, [$monthStart, $monthEnd]);
    
    // تنسيق البيانات
    $topSpenders = [];
    foreach ($leaderboardData as $row) {
        $name = $row['user_name'] ?? 'مستخدم';
        $phone = $row['user_phone'] ?? '';
        
        // إخفاء الاسم
        if (strlen($name) > 2) {
            $nameMasked = mb_substr($name, 0, 2) . ' م.';
        } else {
            $nameMasked = 'مستخدم';
        }
        
        // إخفاء رقم الهاتف
        if (strlen($phone) > 4) {
            $phoneMasked = '***' . substr($phone, -4);
        } else {
            $phoneMasked = '***';
        }
        
        $topSpenders[] = [
            'user_id' => (int)$row['user_id'],
            'name_masked' => $nameMasked,
            'phone_masked' => $phoneMasked,
            'spent' => (float)$row['spent'],
            'transactions_count' => (int)$row['transactions_count']
        ];
    }
    
} catch (Exception $e) {
    error_log("Leaderboard error: " . $e->getMessage());
    $topSpenders = [];
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">🏆 المتصدرون</h1>
        <p class="page-subtitle">أكبر المستخدمين إنفاقاً هذا الشهر</p>
        <p class="page-date"><?php echo date('F Y'); ?></p>
    </div>

    <!-- معلومات الجوائز -->
    <div class="prizes-info">
        <h3>🎁 الجوائز الشهرية</h3>
        <div class="prizes-grid">
            <div class="prize-item">
                <span class="prize-rank">🥇 المركز الأول</span>
                <span class="prize-amount">40 LYD</span>
            </div>
            <div class="prize-item">
                <span class="prize-rank">🥈 المركز الثاني</span>
                <span class="prize-amount">25 LYD</span>
            </div>
            <div class="prize-item">
                <span class="prize-rank">🥉 المركز الثالث</span>
                <span class="prize-amount">10 LYD</span>
            </div>
            <div class="prize-item">
                <span class="prize-rank">🏅 المراكز 4-7</span>
                <span class="prize-amount">1 LYD</span>
            </div>
        </div>
    </div>

    <!-- جدول المتصدرين -->
    <div class="leaderboard-container">
        <?php if (empty($topSpenders)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <h3>لا توجد بيانات حالياً</h3>
                <p>لم يتم تسجيل أي إنفاق هذا الشهر بعد. كن أول من يظهر في قائمة المتصدرين!</p>
            </div>
        <?php else: ?>
            <div class="leaderboard-list">
                <?php foreach ($topSpenders as $index => $user): ?>
                    <?php 
                    $rank = $index + 1;
                    $prize = ($rank === 1 ? 40 : $rank === 2 ? 25 : $rank === 3 ? 10 : ($rank >= 4 && $rank <= 7 ? 1 : 0));
                    ?>
                    <div class="leaderboard-item rank-<?php echo $rank; ?>">
                        <div class="rank-badge">
                            <?php if ($rank <= 3): ?>
                                <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                            <?php else: ?>
                                #<?php echo $rank; ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['name_masked']); ?></div>
                            <div class="user-phone"><?php echo htmlspecialchars($user['phone_masked']); ?></div>
                        </div>
                        <div class="spending-info">
                            <div class="spent-amount"><?php echo number_format($user['spent'], 2); ?> LYD</div>
                            <div class="transactions-count"><?php echo $user['transactions_count']; ?> طلب</div>
                        </div>
                        <?php if ($prize > 0): ?>
                        <div class="prize-badge">
                            +<?php echo $prize; ?> LYD
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- معلومات إضافية -->
    <div class="leaderboard-info">
        <h3>📋 كيف تعمل لوحة المتصدرين؟</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon">💰</div>
                <div class="info-text">
                    <h4>الإنفاق</h4>
                    <p>يتم حساب إجمالي ما أنفقته على الخدمات خلال الشهر</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">🏆</div>
                <div class="info-text">
                    <h4>الجوائز</h4>
                    <p>أكبر 7 مستخدمين يحصلون على جوائز نقدية شهرية</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">🔒</div>
                <div class="info-text">
                    <h4>الخصوصية</h4>
                    <p>أسماء المستخدمين محمية ولا تظهر إلا جزئياً</p>
                </div>
            </div>
            <div class="info-item">
                <div class="info-icon">📅</div>
                <div class="info-text">
                    <h4>التحديث</h4>
                    <p>تتحدث اللوحة تلقائياً مع كل طلب جديد</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== LEADERBOARD STYLES ===== */
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
    margin-bottom: 0.5rem;
}

.page-date {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.prizes-info {
    background: linear-gradient(135deg, var(--accent-color), #e6b800);
    color: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(201, 162, 39, 0.3);
}

.prizes-info h3 {
    margin: 0 0 1.5rem 0;
    font-size: 1.5rem;
    text-align: center;
    font-weight: 700;
}

.prizes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.prize-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    text-align: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.prize-rank {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.prize-amount {
    font-size: 1.3rem;
    font-weight: 700;
}

.leaderboard-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    line-height: 1.6;
}

.leaderboard-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.leaderboard-item {
    display: grid;
    grid-template-columns: 60px 1fr auto 100px;
    gap: 1rem;
    align-items: center;
    padding: 1.5rem;
    border-radius: 12px;
    background: var(--elev-bg);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.leaderboard-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-color), #e6b800);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.leaderboard-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.leaderboard-item:hover::before {
    opacity: 1;
}

.rank-1 {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
    border-color: rgba(255, 215, 0, 0.3);
}

.rank-2 {
    background: linear-gradient(135deg, rgba(192, 192, 192, 0.1), rgba(192, 192, 192, 0.05));
    border-color: rgba(192, 192, 192, 0.3);
}

.rank-3 {
    background: linear-gradient(135deg, rgba(205, 127, 50, 0.1), rgba(205, 127, 50, 0.05));
    border-color: rgba(205, 127, 50, 0.3);
}

.rank-badge {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--card-bg);
    border-radius: 50%;
    border: 2px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.user-phone {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.spending-info {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.spent-amount {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--accent-color);
    font-variant-numeric: tabular-nums;
}

.transactions-count {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.prize-badge {
    background: linear-gradient(135deg, var(--accent-color), #e6b800);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-align: center;
    box-shadow: 0 2px 8px rgba(201, 162, 39, 0.3);
}

.leaderboard-info {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.leaderboard-info h3 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    text-align: center;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: var(--elev-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.info-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.info-text h4 {
    font-size: 1.1rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.info-text p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .prizes-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .prize-item {
        padding: 1rem 0.5rem;
    }
    
    .leaderboard-item {
        grid-template-columns: 1fr;
        gap: 1rem;
        text-align: center;
    }
    
    .spending-info {
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1rem 0;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .prizes-info {
        padding: 1.5rem;
    }
    
    .leaderboard-container {
        padding: 1.5rem;
    }
    
    .leaderboard-info {
        padding: 1.5rem;
    }
}
</style>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>