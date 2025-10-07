<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Services/PeakerrClient.php';

$pageTitle = 'تتبع الطلب';

// معالجة طلب البحث
$orderId = '';
$orderStatus = null;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['order'])) {
    $orderId = isset($_POST['order']) ? trim($_POST['order']) : (isset($_GET['order']) ? trim($_GET['order']) : '');
    
    if (empty($orderId)) {
        $errorMessage = "يرجى إدخال رقم الطلب";
    } else {
        try {
            // جلب بيانات الطلب من قاعدة البيانات أولاً
            $orderData = Database::fetchOne(
                "SELECT o.*, s.name_ar, s.name, s.category_ar, s.category 
                 FROM orders o 
                 LEFT JOIN services_cache s ON o.service_id = s.id 
                 WHERE o.external_order_id = ?",
                [$orderId]
            );
            
            $peakerr = new PeakerrClient();
            $orderStatus = $peakerr->getOrderStatus($orderId);
            
            if (is_array($orderStatus)) {
                // استبدال السعر بسعرنا من قاعدة البيانات
                if ($orderData && isset($orderData['price_lyd'])) {
                    $orderStatus['charge'] = $orderData['price_lyd'];
                    $orderStatus['our_price'] = true; // علامة أن هذا سعرنا
                }
                
                $successMessage = "✅ تم جلب حالة الطلب بنجاح";
            }
            
        } catch (Exception $e) {
            $errorMessage = "❌ خطأ في جلب حالة الطلب: " . $e->getMessage();
        }
    }
}

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">تتبع حالة الطلب</h1>
            <p class="card-subtitle">أدخل رقم طلبك لمتابعة حالة التنفيذ</p>
        </div>
        
        <!-- نموذج البحث -->
        <form method="POST" class="mb-3">
            <div class="form-group">
                <label for="order" class="form-label">رقم الطلب</label>
                <input type="text" 
                       id="order" 
                       name="order" 
                       class="form-control" 
                       placeholder="أدخل رقم الطلب الخارجي..." 
                       value="<?php echo htmlspecialchars($orderId); ?>"
                       required>
                <small style="color: var(--text-secondary);">
                    يمكنك العثور على رقم الطلب في رسالة تأكيد الطلب أو في البريد الإلكتروني
                </small>
            </div>
            <button type="submit" class="btn btn-block">تتبع الطلب</button>
        </form>
        
        <!-- رسائل النجاح والخطأ -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <!-- عرض حالة الطلب -->
        <?php if ($orderStatus): ?>
            <div class="order-status">
                <h3 style="color: var(--accent-color); margin-bottom: 1.5rem;">حالة الطلب #<?php echo htmlspecialchars($orderId); ?></h3>
                
                <?php if (is_array($orderStatus)): ?>
                    <div class="grid grid-2">
                        <div>
                            <h4 style="color: var(--primary-color); margin-bottom: 1rem;">معلومات الطلب</h4>
                            <table style="width: 100%; border-collapse: collapse;">
                                <?php foreach ($orderStatus as $key => $value): ?>
                                    <?php if (!is_array($value) && !is_object($value)): ?>
                                        <tr>
                                            <td style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color); width: 40%;">
                                                <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong>
                                            </td>
                                            <td style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                                <?php 
                                                if ($key === 'status') {
                                                    $statusClass = 'warning';
                                                    if (in_array(strtolower($value), ['completed', 'finished', 'done'])) {
                                                        $statusClass = 'success';
                                                    } elseif (in_array(strtolower($value), ['cancelled', 'failed', 'error'])) {
                                                        $statusClass = 'error';
                                                    }
                                                    echo '<span style="background: var(--' . $statusClass . '-color); color: ' . ($statusClass === 'warning' ? 'var(--dark-bg)' : 'white') . '; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: bold;">' . htmlspecialchars($value) . '</span>';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        
                        <div>
                            <h4 style="color: var(--primary-color); margin-bottom: 1rem;">الحالة الحالية</h4>
                            <div style="background: rgba(26, 60, 140, 0.2); padding: 1.5rem; border-radius: 10px; border: 1px solid var(--border-color);">
                                <?php 
                                $status = $orderStatus['status'] ?? 'غير محدد';
                                $statusText = '';
                                $statusIcon = '⏳';
                                
                                switch (strtolower($status)) {
                                    case 'pending':
                                        $statusText = '⏳ في انتظار المعالجة';
                                        $statusIcon = '⏳';
                                        break;
                                    case 'processing':
                                    case 'in_progress':
                                        $statusText = '⚙️ قيد التنفيذ';
                                        $statusIcon = '🔄';
                                        break;
                                    case 'completed':
                                    case 'finished':
                                    case 'done':
                                        $statusText = '✅ مكتمل';
                                        $statusIcon = '✅';
                                        break;
                                    case 'cancelled':
                                    case 'failed':
                                    case 'error':
                                        $statusText = '❌ فشل أو تم الإلغاء';
                                        $statusIcon = '❌';
                                        break;
                                    default:
                                        $statusText = htmlspecialchars($status);
                                        $statusIcon = '📋';
                                }
                                ?>
                                
                                <div style="text-align: center;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;"><?php echo $statusIcon; ?></div>
                                    <h3 style="color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo $statusText; ?></h3>
                                    <p style="color: var(--text-secondary);">
                                        <?php if (strtolower($status) === 'completed'): ?>
                                            🎉 تم تنفيذ طلبك بنجاح! شكراً لاختيارك خدماتنا.
                                        <?php elseif (strtolower($status) === 'processing'): ?>
                                            🔄 طلبك قيد التنفيذ حالياً. سيتم إشعارك عند اكتماله.
                                        <?php elseif (strtolower($status) === 'pending'): ?>
                                            ⏳ طلبك في قائمة الانتظار وسيتم البدء في تنفيذه قريباً.
                                        <?php else: ?>
                                            📞 لمزيد من التفاصيل، يرجى التواصل معنا عبر واتساب.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- معلومات إضافية -->
                    <?php if (isset($orderStatus['start_count']) || isset($orderStatus['remains'])): ?>
                        <div class="alert" style="background: rgba(201, 162, 39, 0.2); border-color: var(--accent-color); color: #fff3cd; margin-top: 2rem;">
                            <h4 style="color: var(--accent-color); margin-bottom: 1rem;">تفاصيل التنفيذ</h4>
                            <div class="grid grid-2">
                                <?php if (isset($orderStatus['start_count'])): ?>
                                    <p><strong>العدد المبدئي:</strong> <?php echo Formatters::formatQuantity($orderStatus['start_count']); ?></p>
                                <?php endif; ?>
                                <?php if (isset($orderStatus['remains'])): ?>
                                    <p><strong>المتبقي:</strong> <?php echo Formatters::formatQuantity($orderStatus['remains']); ?></p>
                                <?php endif; ?>
                                <?php if (isset($orderStatus['charge'])): ?>
                                    <p><strong>التكلفة:</strong> 
                                        <?php echo Formatters::formatMoney($orderStatus['charge']); ?>
                                        <?php if (isset($orderStatus['our_price']) && $orderStatus['our_price']): ?>
                                            <span style="color: var(--success-color); font-size: 0.8rem;">(سعرنا)</span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (isset($orderStatus['date'])): ?>
                                    <p><strong>تاريخ الطلب:</strong> <?php echo htmlspecialchars($orderStatus['date']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- استجابة نصية -->
                    <div class="alert alert-warning">
                        <h4>استجابة المزود</h4>
                        <pre style="background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 5px; overflow-x: auto; margin-top: 1rem;"><?php echo htmlspecialchars($orderStatus['raw_response'] ?? $orderStatus); ?></pre>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="/track.php" class="btn" style="margin-left: 1rem;">تتبع طلب آخر</a>
                    <a href="/catalog.php" class="btn btn-primary" style="margin-left: 1rem;">طلب خدمة جديدة</a>
                    <a href="https://wa.me/218912345678?text=مرحباً، لدي استفسار حول طلبي رقم <?php echo htmlspecialchars($orderId); ?>" 
                       target="_blank" class="btn btn-success">تواصل عبر واتساب</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- مساعدة -->
        <div class="help-section" style="margin-top: 3rem;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">هل تحتاج مساعدة؟</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-3">
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">📞</div>
                            <h4>تواصل معنا</h4>
                            <p>نحن متاحون 24/7 لمساعدتك</p>
                            <a href="https://wa.me/218912345678" target="_blank" class="btn">واتساب</a>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                            <h4>طلباتي</h4>
                            <p>احتفظ برقم طلبك للرجوع إليه</p>
                            <a href="/catalog.php" class="btn btn-primary">طلب جديد</a>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">❓</div>
                            <h4>الأسئلة الشائعة</h4>
                            <p>إجابات على الأسئلة الأكثر شيوعاً</p>
                            <a href="https://wa.me/218912345678" target="_blank" class="btn btn-success">اسأل الآن</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Order status polling for track page
let trackingPollingInterval = null;
let trackingPollingEnabled = true;
let isTrackingPolling = false;
const currentOrderId = <?php echo !empty($orderId) ? "'" . addslashes($orderId) . "'" : 'null'; ?>;

function startTrackingPolling() {
    // Don't start if no order ID or already polling or disabled
    if (!currentOrderId || isTrackingPolling || !trackingPollingEnabled) return;
    
    // Check if order is in terminal state
    const storedStatus = <?php echo isset($orderStatus) && is_array($orderStatus) && isset($orderStatus['status']) ? "'" . addslashes(strtolower($orderStatus['status'])) . "'" : 'null'; ?>;
    if (storedStatus && ['completed', 'cancelled', 'refunded'].includes(storedStatus)) {
        return; // Don't poll terminal states
    }
    
    isTrackingPolling = true;
    trackingPollingInterval = setInterval(pollTrackingOrderStatus, 15000); // Poll every 15 seconds
    
    // Show sync indicator
    updateTrackingSyncIndicator(true);
}

function stopTrackingPolling() {
    if (trackingPollingInterval) {
        clearInterval(trackingPollingInterval);
        trackingPollingInterval = null;
    }
    isTrackingPolling = false;
    updateTrackingSyncIndicator(false);
}

function updateTrackingSyncIndicator(show) {
    let indicator = document.getElementById('tracking-sync-indicator');
    if (!indicator && show) {
        // Create sync indicator
        indicator = document.createElement('div');
        indicator.id = 'tracking-sync-indicator';
        indicator.innerHTML = `
            <div style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: var(--color-text-muted); margin-left: 1rem;">
                <span class="spinner-mini"></span>
                <span>مزامنة...</span>
            </div>
        `;
        
        // Add to page header
        const header = document.querySelector('.card-header');
        if (header) {
            header.appendChild(indicator);
        }
    }
    
    if (indicator) {
        indicator.style.display = show ? 'inline-flex' : 'none';
    }
}

async function pollTrackingOrderStatus() {
    if (!currentOrderId) {
        stopTrackingPolling();
        return;
    }
    
    try {
        const data = await fetch(`/api/order_status.php?id=${encodeURIComponent(currentOrderId)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            timeout: 10000
        });
        
        if (data.success && data.results && data.results[currentOrderId]) {
            const result = data.results[currentOrderId];
            
            if (result.changed && result.status !== result.previous_status) {
                // Reload page to show updated status
                if (typeof showToast === 'function') {
                    showToast(`تم تحديث حالة الطلب إلى ${getTrackingStatusText(result.status)}`, 'success');
                }
                
                // Reload page after a short delay to show changes
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
                stopTrackingPolling();
            } else if (result.terminal) {
                // Order is in terminal state, stop polling
                stopTrackingPolling();
                if (typeof showToast === 'function') {
                    showToast('الطلب في حالة نهائية - تم إيقاف المزامنة التلقائية', 'info');
                }
            }
        }
        
    } catch (error) {
        // Show error toast but don't stop polling immediately
        if (typeof showToast === 'function') {
            showToast('خطأ في مزامنة حالة الطلب', 'error');
        }
        
        // Stop polling after repeated failures
        setTimeout(stopTrackingPolling, 10000);
    }
}

function getTrackingStatusText(status) {
    switch (status) {
        case 'completed': return 'مكتمل';
        case 'processing': return 'قيد التنفيذ';
        case 'cancelled':
        case 'failed': return 'ملغي';
        default: return 'في الانتظار';
    }
}

// Start polling when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Only start polling if we have an order ID and a valid status was found
    if (currentOrderId && <?php echo isset($orderStatus) && !empty($orderStatus) ? 'true' : 'false'; ?>) {
        // Start polling after a short delay
        setTimeout(startTrackingPolling, 3000);
    }
});

// Stop polling when page becomes hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopTrackingPolling();
    } else {
        startTrackingPolling();
    }
});

// Add manual refresh button
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($orderId) && isset($orderStatus)): ?>
    const refreshBtn = document.createElement('button');
    refreshBtn.className = 'btn btn-outline';
    refreshBtn.innerHTML = '🔄 تحديث يدوي';
    refreshBtn.style.marginLeft = '1rem';
    refreshBtn.onclick = function() {
        this.disabled = true;
        this.innerHTML = '⏳ جاري التحديث...';
        
        fetch(`/api/order_status.php?id=${encodeURIComponent(currentOrderId)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            timeout: 10000,
            silentError: true
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                throw new Error(data.error || 'خطأ في التحديث');
            }
        })
        .catch(error => {
            if (typeof showToast === 'function') {
                showToast('خطأ في تحديث الحالة', 'error');
            }
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '🔄 تحديث يدوي';
        });
    };
    
    // Add refresh button to the buttons area
    const buttonsArea = document.querySelector('.card-body > div[style*="text-align: center"]');
    if (buttonsArea) {
        buttonsArea.insertBefore(refreshBtn, buttonsArea.firstChild);
    }
    <?php endif; ?>
});
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>
