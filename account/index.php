<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$user = Auth::requireLogin();

$pageTitle = 'حسابي';

// جلب إحصائيات المستخدم
try {
    // عدد الطلبات
    $ordersCount = Database::fetchOne(
        "SELECT COUNT(*) as count FROM orders WHERE user_id = ?",
        [$user['id']]
    )['count'];
    
    // الرصيد الحالي
    $wallet = Database::fetchOne(
        "SELECT balance FROM wallets WHERE user_id = ?",
        [$user['id']]
    );
    $balance = $wallet ? $wallet['balance'] : 0;
    
    // آخر الطلبات
    $recentOrders = Database::fetchAll(
        "SELECT o.*, s.name as service_name 
         FROM orders o 
         LEFT JOIN services_cache s ON o.service_id = s.id 
         WHERE o.user_id = ? 
         ORDER BY o.created_at DESC 
         LIMIT 5",
        [$user['id']]
    );
    
} catch (Exception $e) {
    $ordersCount = 0;
    $balance = 0;
    $recentOrders = [];
    $errorMessage = "خطأ في جلب البيانات: " . $e->getMessage();
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">مرحباً، <?php echo htmlspecialchars($user['name']); ?></h1>
            <p class="card-subtitle">نظرة عامة على حسابك</p>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <!-- إحصائيات سريعة -->
        <div class="grid grid-3" style="margin-bottom: 2rem;">
            <div class="card" style="text-align: center;">
                <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;">📋</div>
                <h3><?php echo number_format($ordersCount); ?></h3>
                <p style="color: var(--text-secondary);">إجمالي الطلبات</p>
            </div>
            
            <div class="card" style="text-align: center;">
                <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;">💰</div>
                <h3><?php echo number_format($balance, 2); ?> LYD</h3>
                <p style="color: var(--text-secondary);">الرصيد الحالي</p>
            </div>
            
            <div class="card" style="text-align: center;">
                <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;">📞</div>
                <h3><?php echo htmlspecialchars($user['phone']); ?></h3>
                <p style="color: var(--text-secondary);">رقم الهاتف</p>
            </div>
        </div>
        
        <!-- الروابط السريعة -->
        <div class="grid grid-2" style="margin-bottom: 2rem;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">إدارة الحساب</h3>
                </div>
                <div class="card-body">
                    <a href="/account/profile.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        تعديل الملف الشخصي
                    </a>
                    <a href="/account/orders.php" class="btn btn-primary btn-block" style="margin-bottom: 1rem;">
                        طلباتي
                    </a>
                    <a href="/wallet/" class="btn btn-success btn-block">
                        المحفظة
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">خدمات سريعة</h3>
                </div>
                <div class="card-body">
                    <a href="/catalog.php" class="btn btn-block" style="margin-bottom: 1rem;">
                        تصفح الخدمات
                    </a>
                    <a href="/track.php" class="btn btn-primary btn-block" style="margin-bottom: 1rem;">
                        تتبع الطلب
                    </a>
                    <a href="https://wa.me/218912345678" target="_blank" class="btn btn-success btn-block">
                        تواصل معنا
                    </a>
                </div>
            </div>
        </div>
        
        <!-- آخر الطلبات -->
        <?php if (!empty($recentOrders)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">آخر الطلبات</h3>
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>الخدمة</th>
                                    <th>الكمية</th>
                                    <th>السعر</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['service_name'] ?: 'غير محدد'); ?></td>
                                        <td><?php echo number_format($order['quantity']); ?></td>
                                        <td><?php echo number_format($order['price_lyd'], 2); ?> LYD</td>
                                        <td>
                                            <span style="background: var(--warning-color); color: var(--dark-bg); padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: bold;">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="/account/orders.php" class="btn">عرض جميع الطلبات</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem 2rem;">
                    <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;">📋</div>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">لا توجد طلبات بعد</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        ابدأ بطلب أول خدمة لك واحصل على أفضل الخدمات بأسعار تنافسية
                    </p>
                    <a href="/catalog.php" class="btn btn-lg">تصفح الخدمات</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
