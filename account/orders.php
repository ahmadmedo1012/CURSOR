<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$user = Auth::requireLogin();

$pageTitle = 'طلباتي';

// معالجة البحث والفلترة
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// بناء استعلام البحث
$whereConditions = ['o.user_id = ?'];
$params = [$user['id']];

if (!empty($statusFilter)) {
    $whereConditions[] = 'o.status = ?';
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereConditions[] = '(s.name LIKE ? OR o.link LIKE ? OR o.username LIKE ?)';
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    $orders = Database::fetchAll(
        "SELECT o.*, s.name as service_name, s.category 
         FROM orders o 
         LEFT JOIN services_cache s ON o.service_id = s.id 
         WHERE {$whereClause}
         ORDER BY o.created_at DESC",
        $params
    );
    
    // إحصائيات الطلبات
    $stats = Database::fetchAll(
        "SELECT status, COUNT(*) as count 
         FROM orders 
         WHERE user_id = ? 
         GROUP BY status",
        [$user['id']]
    );
    
    $statusCounts = [];
    foreach ($stats as $stat) {
        $statusCounts[$stat['status']] = $stat['count'];
    }
    
} catch (Exception $e) {
    $orders = [];
    $statusCounts = [];
    $errorMessage = "خطأ في جلب الطلبات: " . $e->getMessage();
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav" style="margin-bottom: 1rem;">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
            <li class="breadcrumb-item"><a href="/account/">حسابي</a></li>
            <li class="breadcrumb-item active" aria-current="page">الطلبات</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">الطلبات</h1>
            <p class="card-subtitle">جميع طلباتك ومراحل تنفيذها</p>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <!-- إحصائيات سريعة -->
        <?php if (!empty($statusCounts)): ?>
            <div class="grid grid-4" style="margin-bottom: 2rem;">
                <div style="text-align: center;">
                    <h3 style="color: var(--primary-color);"><?php echo $statusCounts['pending'] ?? 0; ?></h3>
                    <p style="color: var(--text-secondary);">في الانتظار</p>
                </div>
                <div style="text-align: center;">
                    <h3 style="color: var(--warning-color);"><?php echo $statusCounts['processing'] ?? 0; ?></h3>
                    <p style="color: var(--text-secondary);">قيد التنفيذ</p>
                </div>
                <div style="text-align: center;">
                    <h3 style="color: var(--success-color);"><?php echo $statusCounts['completed'] ?? 0; ?></h3>
                    <p style="color: var(--text-secondary);">مكتملة</p>
                </div>
                <div style="text-align: center;">
                    <h3 style="color: var(--error-color);"><?php echo $statusCounts['cancelled'] ?? 0; ?></h3>
                    <p style="color: var(--text-secondary);">ملغية</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- فلاتر البحث -->
        <form method="GET" style="margin-bottom: 2rem;" id="orders-filter-form">
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="search" class="form-label">البحث</label>
                    <input type="text" 
                           id="orders-search" 
                           name="search" 
                           class="form-control" 
                           placeholder="ابحث في الخدمات أو الروابط..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status" class="form-label">فلترة حسب الحالة</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">جميع الحالات</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>في الانتظار</option>
                        <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn">بحث</button>
                <button type="button" class="btn btn-primary" onclick="clearOrdersFilters()">مسح الفلاتر</button>
            </div>
        </form>
        
        <!-- جدول الطلبات -->
        <?php if (!empty($orders)): ?>
            <div class="orders-table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>الخدمة</th>
                            <th>الكمية</th>
                            <th>الهدف</th>
                            <th>السعر</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div>
                                    <strong>#<?php echo $order['id']; ?></strong>
                                    <?php if ($order['external_order_id']): ?>
                                        <br><small style="color: var(--text-secondary);">
                                            خارجي: #<?php echo htmlspecialchars($order['external_order_id']); ?>
                                        </small>
                                    <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn-copy-id" onclick="copyToClipboard('<?php echo $order['id']; ?>', 'تم نسخ رقم الطلب')" title="نسخ رقم الطلب">
                                            📋
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['service_name'] ?: 'غير محدد'); ?></strong>
                                    <?php if ($order['category']): ?>
                                        <br><small style="color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($order['category']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Formatters::formatQuantity($order['quantity']); ?></td>
                                <td>
                                    <?php if ($order['link']): ?>
                                        <a href="<?php echo htmlspecialchars($order['link']); ?>" target="_blank" style="color: var(--accent-color);">
                                            رابط
                                        </a>
                                    <?php elseif ($order['username']): ?>
                                        @<?php echo htmlspecialchars($order['username']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--accent-color); font-weight: bold;">
                                    <?php echo Formatters::formatMoney($order['price_lyd']); ?>
                                </td>
                                <td data-status="<?php echo htmlspecialchars($order['status']); ?>">
                                    <?php
                                    $statusClass = 'status-warning';
                                    $statusText = $order['status'];
                                    $statusIcon = '⏳';
                                    
                                    switch ($order['status']) {
                                        case 'completed':
                                            $statusClass = 'status-success';
                                            $statusText = 'مكتمل';
                                            $statusIcon = '✅';
                                            break;
                                        case 'processing':
                                            $statusClass = 'status-primary';
                                            $statusText = 'قيد التنفيذ';
                                            $statusIcon = '🔄';
                                            break;
                                        case 'partial':
                                            $statusClass = 'status-info';
                                            $statusText = 'جزئي';
                                            $statusIcon = '⚠️';
                                            break;
                                        case 'cancelled':
                                        case 'failed':
                                            $statusClass = 'status-error';
                                            $statusText = 'ملغي';
                                            $statusIcon = '❌';
                                            break;
                                        default:
                                            $statusText = 'في الانتظار';
                                            $statusIcon = '⏳';
                                    }
                                    ?>
                                    <span class="order-status-badge <?php echo $statusClass; ?>">
                                        <span class="status-icon"><?php echo $statusIcon; ?></span>
                                        <span class="status-text"><?php echo $statusText; ?></span>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d', strtotime($order['created_at'])); ?>
                                    <br><small style="color: var(--text-secondary);">
                                        <?php echo date('H:i', strtotime($order['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <!-- زر التتبع -->
                                    <?php if ($order['external_order_id']): ?>
                                        <a href="/track.php?order=<?php echo urlencode($order['external_order_id']); ?>" 
                                               class="btn btn-sm btn-primary" 
                                           title="تتبع الطلب">
                                                <span class="action-icon">📍</span>
                                                <span class="action-text">تتبع</span>
                                            </a>
                                            
                                            <!-- أزرار النسخ -->
                                            <div class="copy-buttons">
                                                <button type="button" class="copy-btn" onclick="copyOrderId(<?php echo $order['id']; ?>)" title="نسخ رقم الطلب">
                                                    📋 رقم الطلب
                                                </button>
                                                <button type="button" class="copy-btn" onclick="copyExternalId('<?php echo htmlspecialchars($order['external_order_id']); ?>')" title="نسخ معرّف المزود">
                                                    🏷️ المزود
                                                </button>
                                                <button type="button" class="copy-btn" onclick="copyTrackingUrl('<?php echo urlencode($order['external_order_id']); ?>')" title="نسخ رابط التتبع">
                                                    🔗 رابط التتبع
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <!-- أزرار النسخ للطلبات الداخلية -->
                                            <div class="copy-buttons">
                                                <button type="button" class="copy-btn" onclick="copyOrderId(<?php echo $order['id']; ?>)" title="نسخ رقم الطلب">
                                                    📋 رقم الطلب
                                                </button>
                                                <span class="no-external-id">-</span>
                                            </div>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p style="color: var(--text-secondary);">
                    عرض <?php echo count($orders); ?> طلب
                    <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                        (مفلتر)
                    <?php endif; ?>
                </p>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 3rem 2rem;">
                <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;">📋</div>
                <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">
                    <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                        لا توجد طلبات تطابق البحث
                    <?php else: ?>
                        لا توجد طلبات بعد
                    <?php endif; ?>
                </h3>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                    <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                        جرب تغيير معايير البحث أو مسح الفلاتر
                    <?php else: ?>
                        ابدأ بطلب أول خدمة لك واحصل على أفضل الخدمات
                    <?php endif; ?>
                </p>
                <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                    <a href="/account/orders.php" class="btn" style="margin-left: 1rem;">مسح الفلاتر</a>
                <?php endif; ?>
                <a href="/catalog.php" class="btn btn-lg">تصفح الخدمات</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize filter persistence for orders page
document.addEventListener('DOMContentLoaded', function() {
    // Setup filter persistence
    setupFilterPersistence('#orders-filter-form', 'orders');
    
    // Setup debounced search
    setupDebouncedSearch('#orders-search', 'orders', function(searchTerm) {
        const form = document.getElementById('orders-filter-form');
        if (form && searchTerm !== form.querySelector('[name="search"]').value) {
            form.querySelector('[name="search"]').value = searchTerm;
            showLoading(document.querySelector('.table-wrapper'), 'جاري البحث...');
            form.submit();
        }
    });
});

// Clear filters function
function clearOrdersFilters() {
    FilterPersistence.clear('orders');
    window.location.href = '/account/orders.php';
}

// Order status polling
let pollingInterval = null;
let pollingEnabled = true;
let isPolling = false;

function startOrderPolling() {
    // Don't start if already polling or disabled
    if (isPolling || !pollingEnabled) return;
    
    isPolling = true;
    pollingInterval = setInterval(pollOrderStatuses, 18000); // Poll every 18 seconds
    
    // Show sync indicator
    updateSyncIndicator(true);
}

function stopOrderPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    isPolling = false;
    updateSyncIndicator(false);
}

function updateSyncIndicator(show) {
    let indicator = document.getElementById('sync-indicator');
    if (!indicator && show) {
        // Create sync indicator
        indicator = document.createElement('div');
        indicator.id = 'sync-indicator';
        indicator.innerHTML = `
            <div style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: var(--color-text-muted);">
                <span class="spinner-mini"></span>
                <span>مزامنة...</span>
            </div>
        `;
        
        // Add to page header
        const header = document.querySelector('.card-header h1');
        if (header) {
            header.appendChild(indicator);
        }
    }
    
    if (indicator) {
        indicator.style.display = show ? 'inline-flex' : 'none';
    }
}

async function pollOrderStatuses() {
    try {
        // Get active order IDs (non-terminal states)
        const activeOrders = Array.from(document.querySelectorAll('tr[data-order-id]'))
            .filter(row => {
                const statusCell = row.querySelector('[data-status]');
                if (!statusCell) return false;
                const status = statusCell.dataset.status;
                return !['completed', 'cancelled', 'refunded'].includes(status);
            })
            .map(row => row.dataset.orderId);
        
        if (activeOrders.length === 0) {
            stopOrderPolling();
            return;
        }
        
        const data = await fetch(`/api/order_status.php?ids=${activeOrders.join(',')}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin',
            timeout: 10000,
            silentError: true // Silent fail for polling
        });
        
        if (data.success && data.results) {
            let hasChanges = false;
            
            for (const [orderId, result] of Object.entries(data.results)) {
                if (result.changed && result.status !== result.previous_status) {
                    updateOrderStatus(orderId, result.status);
                    hasChanges = true;
                    
                    // Show toast notification
                    if (typeof showToast === 'function') {
                        showToast(`تم تحديث حالة الطلب #${orderId} إلى ${getStatusText(result.status)}`, 'success');
                    }
                }
            }
            
            // If no active orders remain, stop polling
            const remainingActive = Object.values(data.results)
                .filter(result => !result.terminal && !result.error)
                .length;
            
            if (remainingActive === 0) {
                stopOrderPolling();
            }
        }
        
    } catch (error) {
        // Show error toast but don't stop polling immediately
        if (typeof showToast === 'function') {
            showToast('خطأ في مزامنة حالة الطلبات', 'error');
        }
        
        // Stop polling after repeated failures
        setTimeout(stopOrderPolling, 5000);
    }
}

function updateOrderStatus(orderId, newStatus) {
    const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
    if (!row) return;
    
    const statusCell = row.querySelector('[data-status]');
    if (!statusCell) return;
    
    // Update data attribute
    statusCell.dataset.status = newStatus;
    
    // Update visual status
    const statusText = getStatusText(newStatus);
    const statusClass = getStatusClass(newStatus);
    
    const statusIcon = getStatusIcon(newStatus);
    
    statusCell.innerHTML = `
        <span class="order-status-badge ${statusClass}">
            <span class="status-icon">${statusIcon}</span>
            <span class="status-text">${statusText}</span>
        </span>
    `;
}

function getStatusText(status) {
    switch (status) {
        case 'completed': return 'مكتمل';
        case 'processing': return 'قيد التنفيذ';
        case 'cancelled':
        case 'failed': return 'ملغي';
        default: return 'في الانتظار';
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'completed': return 'status-success';
        case 'processing': return 'status-primary';
        case 'partial': return 'status-info';
        case 'cancelled':
        case 'failed': return 'status-error';
        default: return 'status-warning';
    }
}

function getStatusText(newStatus) {
    switch (newStatus) {
        case 'completed': return 'مكتمل';
        case 'processing': return 'قيد التنفيذ';
        case 'partial': return 'جزئي';
        case 'cancelled':
        case 'failed': return 'ملغي';
        default: return 'في الانتظار';
    }
}

function getStatusIcon(newStatus) {
    switch (newStatus) {
        case 'completed': return '✅';
        case 'processing': return '🔄';
        case 'partial': return '⚠️';
        case 'cancelled':
        case 'failed': return '❌';
        default: return '⏳';
    }
}

// Copy functions
function copyOrderId(orderId) {
    copyToClipboard(orderId.toString(), 'تم نسخ رقم الطلب: ' + orderId);
}

function copyExternalId(externalId) {
    copyToClipboard(externalId, 'تم نسخ معرّف المزود: ' + externalId);
}

function copyTrackingUrl(externalId) {
    const url = window.location.protocol + '//' + window.location.host + '/track.php?order=' + encodeURIComponent(externalId);
    copyToClipboard(url, 'تم نسخ رابط التتبع');
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
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add data attributes to order rows
    document.querySelectorAll('tbody tr').forEach((row, index) => {
        const orderId = row.cells[0]?.textContent?.trim();
        const statusCell = row.cells[4]; // Status column
        
        if (orderId && statusCell) {
            // Extract order ID number
            const idMatch = orderId.match(/#(\d+)/);
            if (idMatch) {
                row.dataset.orderId = idMatch[1];
                
                // Add data-status to status cell
                statusCell.setAttribute('data-status', row.textContent.toLowerCase());
            }
        }
    });
    
    // Start polling after a short delay
    setTimeout(startOrderPolling, 3000);
});

// Stop polling when page becomes hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopOrderPolling();
    } else {
        startOrderPolling();
    }
});
</script>

<style>
/* Order Status Badges */
.order-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
}

.status-icon {
    font-size: 1rem;
    line-height: 1;
}

.status-text {
    font-size: 0.75rem;
}

.status-success {
    background: var(--success-color);
    color: white;
    border: 1px solid #1e7e34;
}

.status-primary {
    background: var(--primary-color);
    color: white;
    border: 1px solid var(--color-primary-600);
}

.status-warning {
    background: var(--warning-color);
    color: var(--dark-bg);
    border: 1px solid #ffb300;
}

.status-error {
    background: var(--error-color);
    color: white;
    border: 1px solid #bd2130;
}

.status-info {
    background: #17a2b8;
    color: white;
    border: 1px solid #117a8b;
}

/* Order Actions */
.order-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
}

.copy-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    align-items: center;
}

.copy-btn {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.copy-btn:hover {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
    transform: translateY(-1px);
}

.no-external-id {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-style: italic;
}

.action-icon {
    font-size: 0.875rem;
}

.action-text {
    font-size: 0.875rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Sticky Table Header */
@media (max-width: 768px) {
    .orders-table-container {
        max-height: 70vh;
        overflow-y: auto;
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
    }
    
    .orders-table-container table {
        margin-bottom: 0;
    }
    
    .orders-table-container thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--color-card);
        border-bottom: 2px solid var(--color-border);
    }
    
    .orders-table-container tbody td {
        padding: 0.75rem 0.5rem;
    }
    
    .copy-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    
    .copy-btn {
        justify-content: center;
        padding: 0.375rem 0.5rem;
        font-size: 0.6875rem;
    }
}

/* Toast Animations */
@keyframes slideInOut {
    0% {
        transform: translateX(100%);
        opacity: 0;
    }
    10%, 90% {
        transform: translateX(0);
        opacity: 1;
    }
    100% {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* RTL Support */
[dir="rtl"] .order-status-badge {
    direction: rtl;
}

[dir="rtl"] .order-actions {
    align-items: flex-end;
}
</style>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
