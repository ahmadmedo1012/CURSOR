<?php
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Services/PeakerrClient.php';

// حماية بالـsession (مثل باقي admin)
Auth::startSession();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

// 1. معلومات cURL و SSL
echo "1. معلومات البيئة:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "cURL Version: " . curl_version()['version'] . "\n";
echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n";
echo "User Agent: " . curl_version()['ssl_version'] . "\n\n";

// 2. اختبار Ping خدمات
echo "2. اختبار جلب الخدمات:\n";
try {
    $client = new PeakerrClient();
    $services = $client->getServices();
    
    if (empty($services)) {
        echo "❌ فشل: لا توجد خدمات\n";
    } elseif (isset($services['raw_response'])) {
        echo "⚠️ استجابة نصية: " . substr($services['raw_response'], 0, 200) . "...\n";
    } elseif (is_array($services)) {
        echo "✅ نجح: تم جلب " . count($services) . " خدمة\n";
        echo "أمثلة: " . ($services[0]['name'] ?? 'غير متاح') . "\n";
    } else {
        echo "⚠️ استجابة غير متوقعة: " . json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. اختبار add وهمي (service غير موجود)
echo "3. اختبار إنشاء طلب وهمي (service=99999):\n";
try {
    $client = new PeakerrClient();
    $result = $client->createOrder(99999, 1, '', 'test_user');
    
    if (isset($result['error'])) {
        echo "✅ نجح الاتصال: " . $result['error'] . "\n";
    } elseif (isset($result['message'])) {
        echo "✅ نجح الاتصال: " . $result['message'] . "\n";
    } elseif (isset($result['raw'])) {
        echo "⚠️ استجابة نصية: " . substr($result['raw'], 0, 200) . "...\n";
    } else {
        echo "⚠️ استجابة غير متوقعة: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. فحص ملفات السجلات
echo "4. فحص ملفات السجلات:\n";
$logFile = __DIR__ . '/../logs/api.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    echo "✅ ملف api.log موجود (" . number_format($size) . " bytes)\n";
    if ($size > 0) {
        $lines = file($logFile);
        echo "آخر 3 أسطر:\n";
        foreach (array_slice($lines, -3) as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "❌ ملف api.log غير موجود\n";
}

?>
