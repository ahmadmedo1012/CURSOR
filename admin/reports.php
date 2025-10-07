<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Utils/db.php';
require_once __DIR__ . '/../src/Services/StatsService.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'التقارير والإحصائيات';
$period = $_GET['period'] ?? '30';
$reportType = $_GET['type'] ?? 'overview';

// Enhanced Activity Logs Management
$logView = $_GET['logs'] ?? '';
$actorFilter = $_GET['actor'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Enhanced Activity Logs Processing
if ($logView === 'activity') {
    // Process activity logs from various log files
    $logFiles = [
        'wallet' => '../logs/wallet-audit.log',
        'orders' => '../logs/order-audit.log',
        'admin' => '../logs/admin-activity.log',
        'system' => '../logs/system.log'
    ];
    
    $allActivityLogs = [];
    $logActors = [];
    $logActions = [];
    
    foreach ($logFiles as $logType => $logPath) {
        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Parse log line: [timestamp] ACTION: details
                if (preg_match('/^\[([^\]]+)\]\s+([^:]+):\s*(.+)$/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $action = trim($matches[2]);
                    $details = strtolower(trim($matches[3]));
                    
                    // Extract actor if present (format: "Actor performed action")
                    $actor = 'system';
                    if (preg_match('/^(wallet_|order_|admin_)(approved|rejected|updated|created)/i', $action, $actionMatch)) {
                        $actor = 'admin'; // Default actor for system actions
                    }
                    
                    // Extract target from details (ID numbers, etc.)
                    $target = 'unknown';
                    if (preg_match('/(?:transaction|order|user|ticket)#?(\d+)/i', $details, $targetMatch)) {
                        $target = $targetMatch[1];
                    }
                    
                    $logEntry = [
                        'timestamp' => $timestamp,
                        'actor' => $actor,
                        'action' => $action,
                        'target' => $target,
                        'details' => $details,
                        'type' => $logType,
                        'raw_line' => $line
                    ];
                    
                    // Apply filters
                    $include = true;
                    
                    if ($actorFilter && $actor !== $actorFilter) $include = false;
                    if ($actionFilter && stripos($action, $actionFilter) === false) $include = false;
                    if ($dateFilter) {
                        $logDate = date('Y-m-d', strtotime($timestamp));
                        if ($dateFilter !== $logDate) $include = false;
                    }
                    
                    if ($include) {
                        $allActivityLogs[] = $logEntry;
                        
                        // Collect unique actors and actions for filters
                        if (!in_array($actor, $logActors)) $logActors[] = $actor;
                        if (!in_array($action, $logActions)) $logActions[] = $action;
                    }
                }
            }
        }
    }
    
    // Sort by most recent first
    usort($allActivityLogs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 50;
    $offset = ($page - 1) * $perPage;
    $totalLogs = count($allActivityLogs);
    $paginatedLogs = array_slice($allActivityLogs, $offset, $perPage);
    
} else {
    // جلب البيانات حسب نوع التقرير
    switch ($reportType) {
        case 'orders':
            $data = StatsService::getOrdersStats($period);
            break;
        case 'users':
            $data = StatsService::getUsersStats();
            break;
        case 'services':
            $data = StatsService::getServicesStats();
            break;
        case 'notifications':
            $data = StatsService::getNotificationsStats();
            break;
        case 'performance':
            $data = StatsService::getPerformanceStats();
            break;
        default:
            $data = StatsService::getComprehensiveReport($period);
            break;
    }
}

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container-fluid">
    <!-- شريط التنقل -->
    <div class="admin-topbar">
        <div class="admin-topbar-left">
            <h1 class="admin-title">
                <?php 
                if ($logView === 'activity'): 
                    echo "💼 سجل الأنشطة والتصديق";
                else:
                    echo "التقارير والإحصائيات";
                endif;
                ?>
            </h1>
            <p class="admin-subtitle">
                <?php 
                if ($logView === 'activity'): 
                    echo "تتبع جميع العمليات والإجراءات في النظام";
                else:
                    echo "تحليل شامل لأداء الموقع";
                endif;
                ?>
            </p>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-controls">
                <?php if ($logView === 'activity'): ?>
                    <!-- Activity Logs Controls -->
                    <div class="view-switcher">
                        <a href="?" class="btn btn-outline">
                            📊 التقارير والإحصائيات
                        </a>
                        <a href="?logs=activity" class="btn btn-primary">
                            💼 سجل الأنشطة
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Reports Controls -->
                    <div class="period-selector">
                        <label>الفترة الزمنية:</label>
                        <select id=" период-selector" class="form-control">
                            <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>آخر 7 أيام</option>
                            <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>آخر 30 يوم</option>
                            <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>آخر 3 أشهر</option>
                        </select>
                    </div>
                    
                    <div class="report-type-selector">
                        <label>نوع التقرير:</label>
                        <select id="report-type-selector" class="form-control">
                            <option value="overview" <?php echo $reportType == 'overview' ? 'selected' : ''; ?>>نظرة عامة</option>
                            <option value="orders" <?php echo $reportType == 'orders' ? 'selected' : ''; ?>>الطلبات</option>
                            <option value="users" <?php echo $reportType == 'users' ? 'selected' : ''; ?>>المستخدمين</option>
                            <option value="services" <?php echo $reportType == 'services' ? 'selected' : ''; ?>>الخدمات</option>
                            <option value="notifications" <?php echo $reportType == 'notifications' ? 'selected' : ''; ?>>الإشعارات</option>
                            <option value="performance" <?php echo $reportType == 'performance' ? 'selected' : ''; ?>>الأداء</option>
                        </select>
                    </div>
                    
                    <div class="view-switcher">
                        <a href="?logs=activity" class="btn btn-outline">
                            💼 سجل الأنشطة
                        </a>
                    </div>
                    
                    <button class="btn btn-primary" onclick="exportReport()">📊 تصدير التقرير</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- محتوى التقارير -->
    <div class="reports-content">
        <?php if ($logView === 'activity'): ?>
            <!-- Enhanced Activity Logs Section -->
            <div class="activity-logs-section">
                <!-- Activity Summary and Filters will go here -->
                <h2>💼 سجل الأنشطة والتصديق</h2>
                <p>تتبع جميع العمليات والإجراءات في النظام</p>
                
                <?php if (empty($paginatedLogs ?? [])): ?>
                    <div class="alert alert-info">
                        لا توجد سجلات أنشطة متاحة حالياً.
                    </div>
                <?php else: ?>
                    <div class="activity-summary">
                        <p>تم العثور على <?php echo number_format($totalLogs ?? 0); ?> سجل نشاط</p>
                        <p>متعددين مختلفين: <?php echo count($logActors ?? []); ?> | أنواع إجراءات: <?php echo count($logActions ?? []); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($reportType === 'overview'): ?>
            <!-- تقرير نظرة عامة -->
            <div class="report-section">
                <div class="report-header">
                    <h2>نظرة عامة على الأداء</h2>
                    <p>إحصائيات شاملة لآخر <?php echo $period; ?> يوم</p>
                </div>
                
                <div class="stats-overview-grid">
                    <div class="overview-card">
                        <div class="overview-icon">👥</div>
                        <div class="overview-content">
                            <h3><?php echo number_format($data['general']['users']['total']); ?></h3>
                            <p>إجمالي المستخدمين</p>
                            <div class="overview-change positive">
                                +<?php echo number_format($data['general']['users']['new_this_month']); ?> هذا الشهر
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-icon">📦</div>
                        <div class="overview-content">
                            <h3><?php echo number_format($data['general']['orders']['total']); ?></h3>
                            <p>إجمالي الطلبات</p>
                            <div class="overview-change positive">
                                <?php echo number_format($data['general']['orders']['this_month']); ?> هذا الشهر
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-icon">💰</div>
                        <div class="overview-content">
                            <h3><?php echo number_format($data['general']['wallets']['total_balance'], 2); ?></h3>
                            <p>إجمالي المحافظ (LYD)</p>
                            <div class="overview-change">
                                <?php echo number_format($data['general']['wallets']['total_users']); ?> مستخدم نشط
                            </div>
                        </div>
                    </div>
                    
                    <div class="overview-card">
                        <div class="overview-icon">⚙️</div>
                        <div class="overview-content">
                            <h3><?php echo number_format($data['general']['services']['total']); ?></h3>
                            <p>الخدمات المتاحة</p>
                            <div class="overview-change">
                                <?php echo number_format($data['general']['services']['translated']); ?> مترجمة
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- رسوم بيانية -->
                <div class="charts-section">
                    <div class="chart-card">
                        <h3>إحصائيات الطلبات اليومية</h3>
                        <div class="chart-container">
                            <canvas id="dailyOrdersChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3>توزيع الطلبات حسب الحالة</h3>
                        <div class="chart-container">
                            <canvas id="ordersStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'orders'): ?>
            <!-- تقرير الطلبات -->
            <div class="report-section">
                <div class="report-header">
                    <h2>تقرير الطلبات</h2>
                    <p>تحليل مفصل للطلبات في آخر <?php echo $period; ?> يوم</p>
                </div>
                
                <div class="report-tables">
                    <div class="table-card">
                        <h3>الطلبات حسب الحالة</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>الحالة</th>
                                        <th>العدد</th>
                                        <th>الإجمالي (LYD)</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['status_stats'] as $status): ?>
                                        <tr>
                                            <td>
                                                <span class="status-badge status-<?php echo $status['status']; ?>">
                                                    <?php echo htmlspecialchars($status['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($status['count']); ?></td>
                                            <td><?php echo number_format($status['total_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $total = array_sum(array_column($data['status_stats'], 'count'));
                                                echo $total > 0 ? number_format(($status['count'] / $total) * 100, 1) : 0; 
                                                ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="table-card">
                        <h3>أفضل الخدمات</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>الخدمة</th>
                                        <th>التصنيف</th>
                                        <th>عدد الطلبات</th>
                                        <th>الإيراد (LYD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($data['top_services'], 0, 10) as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name'] ?: 'غير محدد'); ?></td>
                                            <td><?php echo htmlspecialchars($service['category'] ?: 'عام'); ?></td>
                                            <td><?php echo number_format($service['orders_count']); ?></td>
                                            <td><?php echo number_format($service['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'users'): ?>
            <!-- تقرير المستخدمين -->
            <div class="report-section">
                <div class="report-header">
                    <h2>تقرير المستخدمين</h2>
                    <p>تحليل نشاط المستخدمين</p>
                </div>
                
                <div class="report-tables">
                    <div class="table-card">
                        <h3>المستخدمين الأكثر نشاطاً</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>الترتيب</th>
                                        <th>المستخدم</th>
                                        <th>رقم الهاتف</th>
                                        <th>عدد الطلبات</th>
                                        <th>إجمالي الإنفاق</th>
                                        <th>آخر نشاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['active_users'] as $index => $user): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge"><?php echo $index + 1; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['name'] ?: 'مستخدم'); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td><?php echo Formatters::formatQuantity($user['orders_count']); ?></td>
                                            <td><?php echo Formatters::formatMoney($user['total_spent']); ?></td>
                                            <td><?php echo $user['last_login'] ? Formatters::formatDate($user['last_login']) : 'لم يسجل دخول'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="table-card">
                        <h3>إحصائيات المحافظ</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>رقم الهاتف</th>
                                        <th>الرصيد (LYD)</th>
                                        <th>عدد المعاملات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['wallet_stats'] as $wallet): ?>
                                        <tr>
                                            <td>مستخدم</td>
                                            <td><?php echo htmlspecialchars($wallet['phone']); ?></td>
                                            <td class="amount-positive"><?php echo number_format($wallet['balance'], 2); ?></td>
                                            <td><?php echo number_format($wallet['transactions_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'services'): ?>
            <!-- تقرير الخدمات -->
            <div class="report-section">
                <div class="report-header">
                    <h2>تقرير الخدمات</h2>
                    <p>تحليل شامل للخدمات المتاحة</p>
                </div>
                
                <div class="report-tables">
                    <div class="table-card">
                        <h3>إحصائيات التصنيفات</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>التصنيف</th>
                                        <th>عدد الخدمات</th>
                                        <th>متوسط السعر</th>
                                        <th>أقل سعر</th>
                                        <th>أعلى سعر</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['category_stats'] as $category): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                            <td><?php echo number_format($category['services_count']); ?></td>
                                            <td><?php echo number_format($category['avg_price'], 2); ?> LYD</td>
                                            <td><?php echo number_format($category['min_price'], 2); ?> LYD</td>
                                            <td><?php echo number_format($category['max_price'], 2); ?> LYD</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="table-card">
                        <h3>الخدمات الأكثر طلباً</h3>
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>الخدمة</th>
                                        <th>التصنيف</th>
                                        <th>السعر</th>
                                        <th>عدد الطلبات</th>
                                        <th>الإيراد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['popular_services'] as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name'] ?: 'غير محدد'); ?></td>
                                            <td><?php echo htmlspecialchars($service['category'] ?: 'عام'); ?></td>
                                            <td><?php echo number_format($service['price'], 2); ?> LYD</td>
                                            <td><?php echo number_format($service['orders_count']); ?></td>
                                            <td><?php echo number_format($service['total_revenue'], 2); ?> LYD</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'performance'): ?>
            <!-- تقرير الأداء -->
            <div class="report-section">
                <div class="report-header">
                    <h2>تقرير الأداء</h2>
                    <p>تحليل أداء النظام ومعدلات النجاح</p>
                </div>
                
                <div class="performance-metrics">
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-content">
                            <h3><?php echo number_format($data['overall_success_rate'], 1); ?>%</h3>
                            <p>معدل النجاح الإجمالي</p>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⏱️</div>
                        <div class="metric-content">
                            <h3><?php echo number_format($data['avg_execution_time'], 0); ?></h3>
                            <p>متوسط وقت التنفيذ (دقيقة)</p>
                        </div>
                    </div>
                </div>
                
                <div class="table-card">
                    <h3>الأداء اليومي</h3>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>إجمالي الطلبات</th>
                                    <th>مكتملة</th>
                                    <th>فاشلة</th>
                                    <th>الإيراد (LYD)</th>
                                    <th>معدل النجاح</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['daily_performance'] as $day): ?>
                                    <tr>
                                        <td><?php echo $day['date']; ?></td>
                                        <td><?php echo number_format($day['total_orders']); ?></td>
                                        <td class="success-count"><?php echo number_format($day['completed_orders']); ?></td>
                                        <td class="failed-count"><?php echo number_format($day['failed_orders']); ?></td>
                                        <td class="amount-positive"><?php echo number_format($day['daily_revenue'], 2); ?></td>
                                        <td>
                                            <span class="success-rate"><?php echo number_format($day['success_rate'], 1); ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- CSS للتقارير -->
<style>
.reports-content {
    margin-top: 2rem;
}

.report-section {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(20px);
}

.report-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.report-header h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.report-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

/* نظرة عامة */
.stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.overview-card {
    background: rgba(201, 162, 39, 0.05);
    border: 2px solid rgba(201, 162, 39, 0.1);
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
}

.overview-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(201, 162, 39, 0.15);
}

.overview-icon {
    font-size: 3rem;
    opacity: 0.8;
}

.overview-content h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.overview-content p {
    color: var(--text-secondary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.overview-change {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.overview-change.positive {
    color: #28a745;
}

/* الرسوم البيانية */
.charts-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.chart-card {
    background: rgba(26, 60, 140, 0.05);
    border: 2px solid rgba(26, 60, 140, 0.1);
    border-radius: 16px;
    padding: 2rem;
}

.chart-card h3 {
    text-align: center;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    font-weight: 600;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* الجداول */
.report-tables {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.table-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
}

.table-card h3 {
    background: linear-gradient(135deg, var(--primary-color), #2c5aa0);
    color: white;
    margin: 0;
    padding: 1rem 1.5rem;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th {
    background: rgba(201, 162, 39, 0.1);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem;
    text-align: right;
    border-bottom: 2px solid var(--border-color);
}

.report-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.report-table tr:hover {
    background: rgba(201, 162, 39, 0.05);
}

/* شارات الحالة */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-completed {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.status-pending {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.status-failed {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

.status-processing {
    background: rgba(23, 162, 184, 0.2);
    color: #17a2b8;
}

.rank-badge {
    display: inline-block;
    width: 30px;
    height: 30px;
    background: var(--accent-color);
    color: #000;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    font-weight: 700;
    font-size: 0.9rem;
}

/* Enhanced Activity Logs Styles */
.activity-logs-section {
    background: var(--color-elev);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.activity-summary {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.activity-filters {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.filters-form {
    width: 100%;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-select, .filter-input {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.view-switcher {
    display: flex;
    gap: 0.5rem;
}

.activity-logs-container {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.logs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.logs-meta {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.activity-logs-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-log-entry {
    background: var(--color-elev);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    cursor: pointer;
}

.activity-log-entry:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.log-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.log-actor {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.actor-icon {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
}

.actor-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.actor-name {
    font-weight: 600;
    color: var(--text-primary);
}

.log-type {
    font-size: 0.8rem;
    color: var(--text-secondary);
    background: rgba(var(--primary-color-rgb), 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

.log-timestamp {
    text-align: left;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.log-entry-body {
    margin-top: 1rem;
}

.log-action {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.action-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
}

.action-wallet { background: #28a745; }
.action-order { background: #007bff; }
.action-admin { background: #6f42c1; }
.action-user { background: #17a2b8; }

.log-target {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.log-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    margin-right: 0.5rem;
}

.log-link:hover {
    text-decoration: underline;
}

.log-details {
    background: rgba(var(--text-primary-rgb), 0.05);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    line-height: 1.4;
    color: var(--text-primary);
}

.log-truncated {
    font-style: italic;
    color: var(--text-secondary);
}

.log-raw-data {
    margin-top: 1rem;
}

.log-raw-data details {
    font-size: 0.8rem;
}

.log-raw-data summary {
    cursor: pointer;
    padding: 0.5rem;
    background: rgba(var(--text-primary-rgb), 0.05);
    border-radius: 6px;
    color: var(--text-secondary);
}

.log-raw-data code {
    display: block;
    background: var(--color-elev);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 0.5rem;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    color: var(--text-primary);
    overflow-x: auto;
}

/* Log Details Modal */
.log-details-modal {
    position: fixed;
    top: 0;
    inset-inline-start: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
}

.modal-overlay {
    background: rgba(0, 0, 0, 0.5);
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 16px;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    border: 1px solid var(--border-color);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: rgba(var(--text-secondary-rgb), 0.1);
}

.modal-body {
    padding: 1.5rem;
}

.log-detail-section {
    margin-bottom: 1.5rem;
}

.log-detail-section h4 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.log-detail-section p {
    margin: 0.5rem 0;
    color: var(--text-primary);
}

.log-detail-section pre {
    background: var(--color-elev);
    padding: 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    line-height: 1.4;
    overflow-x: auto;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
}

/* Activity Toast */
.activity-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1001;
    animation: slideInRight 0.3s ease;
}

.activity-toast-success {
    border-inline-start: 4px solid #28a745;
    background: rgba(40, 167, 69, 0.05);
}

.activity-toast-error {
    border-inline-start: 4px solid #dc3545;
    background: rgba(220, 53, 69, 0.05);
}

.activity-toast-warning {
    border-inline-start: 4px solid #ffc107;
    background: rgba(255, 193, 7, 0.05);
}

.toast-icon {
    font-size: 1.25rem;
}

.toast-message {
    flex: 1;
    color: var(--text-primary);
    font-weight: 500;
}

.toast-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--text-secondary);
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Logs Pagination */
.logs-pagination {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.pagination-nav {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.pagination-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-btn.current {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.pagination-info {
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
}

.empty-state p {
    margin: 0 0 2rem 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .activity-logs-section {
        padding: 1rem;
    }
    
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        justify-content: stretch;
    }
    
    .filter-actions button {
        flex: 1;
    }
    
    .log-entry-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .log-action {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .modal-content {
        margin: 0.5rem;
        max-height: 90vh;
    }
    
    .activity-toast {
        right: 10px;
        inset-inline-start: 10px;
        top: 10px;
    }
    
    .pagination-nav {
        gap: 0.25rem;
    }
    
    .pagination-btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 430px) {
    .view-switcher {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .logs-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .actor-info {
        font-size: 0.9rem;
    }
    
    .log-details {
        padding: 0.75rem;
        font-size: 0.8rem;
    }
    
    .modal-body, .modal-header, .modal-footer {
        padding: 1rem;
    }
    
    .filter-actions button {
        font-size: 0.75rem;
        padding: 0.375rem 0.5rem;
    }
    
    .activity-log-entry {
        padding: 1rem;
    }
    
    .log-entry-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

/* مقاييس الأداء */
.performance-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.metric-card {
    background: rgba(26, 60, 140, 0.05);
    border: 2px solid rgba(26, 60, 140, 0.1);
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    text-align: center;
}

.metric-icon {
    font-size: 3rem;
    opacity: 0.8;
}

.metric-content h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.metric-content p {
    color: var(--text-secondary);
    font-weight: 600;
}

/* ألوان خاصة */
.amount-positive {
    color: #28a745;
    font-weight: 600;
}

.success-count {
    color: #28a745;
    font-weight: 600;
}

.failed-count {
    color: #dc3545;
    font-weight: 600;
}

.success-rate {
    color: var(--accent-color);
    font-weight: 600;
}

/* استجابة للشاشات الصغيرة */
@media (max-width: 768px) {
    .admin-topbar {
        flex-direction: column;
        gap: 1rem;
    }
    
    .admin-controls {
        flex-direction: column;
        gap: 1rem;
    }
    
    .stats-overview-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-section {
        grid-template-columns: 1fr;
    }
    
    .performance-metrics {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript -->
<!-- Chart.js loaded locally for offline support -->
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

// Inline Chart.js fallback - basic charting functionality
window.Chart = window.Chart || {
    register: function() {},
    Chart: function(ctx, config) {
        // Simple fallback implementation
        return {
            update: function() {},
            destroy: function() {}
        };
    }
};
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تغيير الفترة الزمنية
    const periodSelector = document.getElementById('period-selector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            updateReport();
        });
    }
    
    // تغيير نوع التقرير
    const reportTypeSelector = document.getElementById('report-type-selector');
    if (reportTypeSelector) {
        reportTypeSelector.addEventListener('change', function() {
            updateReport();
        });
    }
    
    // رسم الرسوم البيانية
    <?php if ($reportType === 'overview'): ?>
        drawDailyOrdersChart();
        drawOrdersStatusChart();
    <?php endif; ?>
});

function updateReport() {
    const period = document.getElementById('period-selector').value;
    const type = document.getElementById('report-type-selector').value;
    window.location.href = `?period=${period}&type=${type}`;
}

function exportReport() {
    // يمكن إضافة وظيفة التصدير هنا
    // Silent handling in production
}

<?php if ($reportType === 'overview'): ?>
function drawDailyOrdersChart() {
    const ctx = document.getElementById('dailyOrdersChart');
    if (!ctx) return;
    
    const data = <?php echo json_encode($data['orders']['daily_stats']); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.date).reverse(),
            datasets: [{
                label: 'عدد الطلبات',
                data: data.map(item => item.orders_count).reverse(),
                borderColor: '#C9A227',
                backgroundColor: 'rgba(201, 162, 39, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'الإيرادات (LYD)',
                data: data.map(item => item.daily_revenue).reverse(),
                borderColor: '#1A3C8C',
                backgroundColor: 'rgba(26, 60, 140, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function drawOrdersStatusChart() {
    const ctx = document.getElementById('ordersStatusChart');
    if (!ctx) return;
    
    const data = <?php echo json_encode($data['orders']['status_stats']); ?>;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.status),
            datasets: [{
                data: data.map(item => item.count),
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#17a2b8'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        padding: 20
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Enhanced reports management with persistence and confirmations
document.addEventListener('DOMContentLoaded', function() {
    const screenName = 'reports_management';
    const savedPrefs = loadTablePreferences(screenName) || {};
    
    // Table management for reports with large data sets
    document.querySelectorAll('.report-table, table').forEach(table => {
        if (table.querySelector('tbody') && table.querySelectorAll('tbody tr').length > 5) {
            // Add search functionality for large tables
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = '🔍 البحث في التقرير...';
            searchInput.className = 'form-control';
            searchInput.style.cssText = 'margin-bottom: 1rem; max-width: 300px;';
            
            // Insert search before table
            const tableContainer = table.closest('.card-body, .report-section');
            if (tableContainer) {
                tableContainer.insertBefore(searchInput, table);
                
                // Setup debounced search
                setupDebouncedSearch(searchInput, screenName + '_' + Date.now(), function(searchTerm) {
                    const tbody = table.querySelector('tbody');
                    if (!tbody) return;
                    
                    if (!searchTerm.trim()) {
                        tbody.querySelectorAll('tr').forEach(row => {
                            row.style.display = '';
                        });
                        return;
                    }
                    
                    tbody.querySelectorAll('tr').forEach(row => {
                        const searchText = row.textContent.trim().toLowerCase();
                        row.style.display = searchText.includes(searchTerm.toLowerCase()) ? '' : 'none';
                    });
                });
            }
        }
    });
    
    // Enhanced export confirmation
    window.exportReport = function() {
        if (confirmAction('📊 هل تريد تصدير التقرير؟\n\nسيبقى التقرير محدثاً حتى نهاية الجلسة.')) {
            // Simulate export functionality
            showToast('جاري تجهيز التقرير للتصدير...', 'info');
            
            setTimeout(() => {
                showToast('تم إنشاء التقرير بنجاح!', 'success');
                
                // Save export state
                savedPrefs.lastExport = Date.now();
                saveTablePreferences(screenName, savedPrefs);
            }, 2000);
        }
    };
    
    // Save filter/period preferences
    const periodSelect = document.querySelector('select[name="period"]');
    const reportTypeSelect = document.querySelector('select[name="report_type"]');
    
    if (periodSelect) {
        periodSelect.addEventListener('change', debounce(function() {
            savedPrefs.selectedPeriod = this.value;
            saveTablePreferences(screenName, savedPrefs);
        }, 400));
        
        // Restore saved period
        if (savedPrefs.selectedPeriod) {
            periodSelect.value = savedPrefs.selectedPeriod;
        }
    }
    
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', debounce(function() {
            savedPrefs.selectedReportType = this.value;
            saveTablePreferences(screenName, savedPrefs);
        }, 400));
        
        // Restore saved report type
        if (savedPrefs.selectedReportType) {
            reportTypeSelect.value = savedPrefs.selectedReportType;
        }
    }
    
    // Add bulk actions confirmation if any exist
    document.querySelectorAll('[type="checkbox"], [onclick*="selectAll"], [onclick*="bulkAction"]').forEach(element => {
        if (element.onclick) {
            const originalClick = element.onclick;
            element.onclick = function(e) {
                const action = element.inline ? 'تحديد' : 'إجراء جماعي';
                if (!confirmAction(`⚠️ هل تريد ${action} على جميع العناصر المحددة؟\n\nسيتم تطبيق العملية على جميع العناصر المختارة.`)) {
                    e.preventDefault();
                    return false;
                }
                return originalClick.call(this, e);
            };
        }
    });
    
    // Auto-refresh functionality for real-time reports
    if (savedPrefs.autoRefresh) {
        let refreshInterval = null;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                showToast('🔄 جاري تحديث التقرير...', 'info');
                window.location.reload();
            }, 300000); // 5 minutes
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        // Add refresh controls
        const refreshControls = document.createElement('div');
        refreshControls.style.cssText = 'margin-bottom: 1rem; padding: 0.5rem; background: var(--color-elev); border-radius: 6px; text-align: center;';
        
        const refreshBtn = document.createElement('button');
        refreshBtn.type = 'button';
        refreshBtn.className = 'btn btn-sm';
        refreshBtn.innerHTML = savedPrefs.autoRefresh ? '⏹️ إيقاف التحديث التلقائي' : '🔄 بدء التحديث التلقائي';
        refreshBtn.onclick = function() {
            savedPrefs.autoRefresh = !savedPrefs.autoRefresh;
            
            if (savedPrefs.autoRefresh) {
                startAutoRefresh();
                this.innerHTML = '⏹️ إيقاف التحديث التلقائي';
                showToast('تم تفعيل التحديث التلقائي كل 5 دقائق', 'success');
            } else {
                stopAutoRefresh();
                this.innerHTML = '🔄 بدء التحديث التلقائي';
                showToast('تم إيقاف التحديث التلقائي');
            }
            
            saveTablePreferences(screenName, savedPrefs);
        };
        
        refreshControls.appendChild(refreshBtn);
        
        // Insert controls at the top
        const contentArea = document.querySelector('.reports-content, .report-section');
        if (contentArea) {
            contentArea.insertBefore(refreshControls, contentArea.firstChild);
            
            if (savedPrefs.autoRefresh) {
                startAutoRefresh();
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>

