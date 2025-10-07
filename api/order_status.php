<?php
require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Services/PeakerrClient.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session for authentication
Auth::startSession();
$currentUser = Auth::currentUser();

// Check authentication
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'مطلوب تسجيل الدخول']);
    exit;
}

// Handle rate limiting
$rateLimitKey = 'order_status_' . session_id();
$currentTime = time();
$timeWindow = 60; // 1 minute
$maxRequests = 20; // 20 requests per minute

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
if (count($attempts) >= $maxRequests) {
    http_response_code(429);
    echo json_encode(['error' => 'تم تجاوز معدل الطلبات المسموح به']);
    exit;
}

// Record this attempt
$_SESSION['rate_limits'][$rateLimitKey][] = $currentTime;

// Get order IDs from request
$orderIds = [];
if (isset($_GET['ids'])) {
    $orderIds = array_map('intval', explode(',', $_GET['ids']));
} elseif (isset($_POST['ids'])) {
    $orderIds = array_map('intval', $_POST['ids']);
} elseif (isset($_GET['id'])) {
    $orderIds = [intval($_GET['id'])];
}

if (empty($orderIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'رقم الطلب مطلوب']);
    exit;
}

try {
    $results = [];
    $peakerr = new PeakerrClient();
    
    foreach ($orderIds as $orderId) {
        // Verify order belongs to user
        $order = Database::fetchOne(
            "SELECT external_order_id, status, price_lyd FROM orders WHERE id = ? AND user_id = ?",
            [$orderId, $currentUser['id']]
        );
        
        if (!$order) {
            $results[$orderId] = ['error' => 'طلب غير موجود'];
            continue;
        }
        
        // Skip if already in terminal state
        if (in_array($order['status'], ['completed', 'cancelled', 'refunded'])) {
            $results[$orderId] = [
                'status' => $order['status'],
                'terminal' => true,
                'message' => 'الطلب في حالة نهائية'
            ];
            continue;
        }
        
        try {
            // Get external order status if available
            if (!empty($order['external_order_id'])) {
                $externalStatus = $peakerr->getOrderStatus($order['external_order_id']);
                
                if (is_array($externalStatus) && isset($externalStatus['status'])) {
                    $newStatus = $externalStatus['status'];
                    
                    // استبدال السعر بسعرنا من قاعدة البيانات
                    if (isset($order['price_lyd'])) {
                        $externalStatus['charge'] = $order['price_lyd'];
                        $externalStatus['our_price'] = true;
                    }
                    
                    // Update order status if changed
                    if ($newStatus !== $order['status']) {
                        Database::query(
                            "UPDATE orders SET status = ? WHERE id = ?",
                            [$newStatus, $orderId]
                        );
                        
                        $results[$orderId] = [
                            'status' => $newStatus,
                            'changed' => true,
                            'previous_status' => $order['status'],
                            'external_data' => $externalStatus
                        ];
                    } else {
                        $results[$orderId] = [
                            'status' => $newStatus,
                            'changed' => false,
                            'external_data' => $externalStatus
                        ];
                    }
                } else {
                    $results[$orderId] = [
                        'status' => $order['status'],
                        'error' => 'خطأ في جلب حالة الطلب من المزوّد'
                    ];
                }
            } else {
                // No external ID, just return current status
                $results[$orderId] = [
                    'status' => $order['status'],
                    'no_external_id' => true
                ];
            }
            
        } catch (Exception $e) {
            error_log("Order status API error for order {$orderId}: " . $e->getMessage());
            $results[$orderId] = [
                'status' => $order['status'],
                'error' => 'خطأ في التواصل مع المزوّد'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
?>
