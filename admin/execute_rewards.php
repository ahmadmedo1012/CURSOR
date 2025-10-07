<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Services/MonthlyRewardsService.php';

// تعيين نوع المحتوى
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول كمدير
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالوصول'
    ]);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || $input['action'] !== 'execute_rewards') {
        throw new Exception('إجراء غير صحيح');
    }
    
    // التحقق من إمكانية تنفيذ الجوائز
    if (!MonthlyRewardsService::canExecuteRewards()) {
        echo json_encode([
            'success' => false,
            'message' => 'تم تنفيذ جوائز الشهر الماضي مسبقاً'
        ]);
        exit;
    }
    
    // تنفيذ الجوائز
    $result = MonthlyRewardsService::processPreviousMonthRewards($_SESSION['admin_id'] ?? null);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

