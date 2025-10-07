<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';

// تعيين headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// السماح بالوصول من أي مصدر (CORS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// معالجة طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // تحديد الشهر المستهدف
    $targetMonth = $_GET['month'] ?? date('Y-m');
    
    // التحقق من صحة تنسيق الشهر
    if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        $targetMonth = date('Y-m');
    }
    
    // بناء نطاق الشهر
    $monthStart = date('Y-m-01 00:00:00', strtotime($targetMonth));
    $monthEnd = date('Y-m-01 00:00:00', strtotime("$targetMonth +1 month"));
    
    // جلب أكبر المستخدمين إنفاقاً
    $sql = "SELECT 
                wt.user_id,
                u.name as user_name,
                u.phone as user_phone,
                SUM(ABS(wt.amount)) as spent
            FROM wallet_transactions wt
            JOIN users u ON wt.user_id = u.id
            WHERE wt.type = 'deduct'
                AND wt.status = 'approved'
                AND wt.created_at >= ? 
                AND wt.created_at < ?
            GROUP BY wt.user_id
            ORDER BY spent DESC
            LIMIT 10";
    
    $results = Database::fetchAll($sql, [$monthStart, $monthEnd]);
    
    // تنسيق البيانات وإخفاء الأسماء
    $top = [];
    foreach ($results as $row) {
        $name = $row['user_name'];
        $phone = $row['user_phone'];
        
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
        
        $top[] = [
            'user_id' => (int)$row['user_id'],
            'name_masked' => $nameMasked,
            'phone_masked' => $phoneMasked,
            'spent' => (float)$row['spent']
        ];
    }
    
    // نظام الجوائز الثابت
    $prizes = [
        ['rank' => 1, 'amount_lyd' => 40],
        ['rank' => 2, 'amount_lyd' => 25],
        ['rank' => 3, 'amount_lyd' => 10],
        ['rank' => 4, 'amount_lyd' => 1],
        ['rank' => 5, 'amount_lyd' => 1],
        ['rank' => 6, 'amount_lyd' => 1],
        ['rank' => 7, 'amount_lyd' => 1]
    ];
    
    // الاستجابة النهائية
    $response = [
        'month' => $targetMonth,
        'month_name' => date('F Y', strtotime($targetMonth . '-01')),
        'top' => $top,
        'prizes' => $prizes,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // دائماً إرجاع 200 لتجنب retry loops
    echo json_encode([
        'month' => date('Y-m'),
        'month_name' => date('F Y'),
        'top' => [],
        'prizes' => [],
        'error' => 'لا توجد بيانات حالياً',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}
?>