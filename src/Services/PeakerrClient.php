<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}

class PeakerrClient {
    private $apiUrl;
    private $apiKey;
    
    public function __construct() {
        $this->apiUrl = PEAKERR_API_URL;
        $this->apiKey = PEAKERR_API_KEY;
    }
    
private function post(array $fields): array {
  $payload = array_merge(['key'=>$this->apiKey], $fields);
  $ch = curl_init($this->apiUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
    CURLOPT_TIMEOUT        => API_TIMEOUT,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: GameBoxBot/1.0'],
  ]);
  $res = curl_exec($ch);
  $errno = curl_errno($ch);
  $err   = curl_error($ch);
  $info  = curl_getinfo($ch);
  curl_close($ch);

  $out = ['ok'=>false, 'http'=>$info['http_code'] ?? 0];
  if($errno){ $out['error'] = "cURL($errno): $err"; }
  else{
    $j = json_decode($res, true);
    if(json_last_error()===JSON_ERROR_NONE) $out = array_merge(['ok'=>true], $j);
    else $out = ['ok'=>true, 'raw'=>$res];
  }

  if(defined('DEBUG_API') && DEBUG_API){
    @mkdir(__DIR__.'/../../logs', 0775, true);
    @file_put_contents(__DIR__.'/../../logs/api.log',
      date('c')."\nREQ=".json_encode($fields)."\nINFO=".json_encode($info)."\nRESP=".json_encode($out)."\n---\n",
      FILE_APPEND);
  }
  return $out;
}
    
    
    public function getServices() {
        try {
            // Check for development mode
            $devMode = defined('DEV_MODE') && constant('DEV_MODE');
            if ($devMode) {
                return [
                    [
                        'service' => '1',
                        'name' => 'Instagram Followers',
                        'category' => 'Instagram',
                        'rate' => '2.50',
                        'min' => '100',
                        'max' => '10000',
                        'type' => 'Followers'
                    ],
                    [
                        'service' => '2',
                        'name' => 'TikTok Views',
                        'category' => 'TikTok',
                        'rate' => '1.20',
                        'min' => '1000',
                        'max' => '50000',
                        'type' => 'Views'
                    ]
                ];
            }
            
            $response = $this->post(['action' => 'services']);
            
            // التحقق من نجاح الطلب
            if (!$response['ok']) {
                throw new Exception("خطأ في الاتصال: " . ($response['error'] ?? 'خطأ غير معروف'));
            }
            
            // التحقق من وجود خطأ في الاستجابة
            if (isset($response['error'])) {
                throw new Exception("خطأ من المزوّد: " . $response['error']);
            }
            
            // إرجاع البيانات (يمكن أن تكون مصفوفة أو نص خام)
            if (isset($response['raw'])) {
                return ['raw_response' => $response['raw']];
            }
            
            // إزالة 'ok' من الاستجابة وإرجاع باقي البيانات
            unset($response['ok']);
            return $response;
            
        } catch (Exception $e) {
            $devMode = defined('DEV_MODE') && constant('DEV_MODE');
            if ($devMode) {
                throw $e;
            }
            return [];
        }
    }
    
public function createOrder(int $service, int $quantity, string $link = '', string $username = ''): array {
  $fields = ['action'=>'add','service'=>$service,'quantity'=>$quantity];

  // أرسل أحدهما… وإن كان username فقط، ضعه أيضاً في link لتوافق أكبر:
  if($link && !$username)      { $fields['link'] = $link; }
  else if($username && !$link) { $fields['username'] = $username; $fields['link'] = $username; }
  else if($link && $username)  { $fields['link'] = $link; } // لا نرسل الاثنين كمدخلين أساسيين

  $resp = $this->post($fields);

  // إعادة محاولة خفيفة على حالات 5xx/شبكة
  if(!$resp['ok'] || (isset($resp['http']) && (int)$resp['http']>=500)){
    usleep(800000); // 0.8s
    $resp = $this->post($fields);
  }
  return $resp;
}
    
    public function getOrderStatus($orderId) {
        try {
            // Check for development mode
            $devMode = defined('DEV_MODE') && constant('DEV_MODE');
            if ($devMode) {
                $statuses = ['pending', 'processing', 'completed', 'cancelled'];
                return [
                    'order' => $orderId,
                    'status' => $statuses[array_rand($statuses)],
                    'dev_mock' => true
                ];
            }
            
            $response = $this->post(['action' => 'status', 'order' => $orderId]);
            
            // التحقق من نجاح الطلب
            if (!$response['ok']) {
                throw new Exception("خطأ في الاتصال: " . ($response['error'] ?? 'خطأ غير معروف'));
            }
            
            // التحقق من وجود خطأ في الاستجابة
            if (isset($response['error'])) {
                throw new Exception("خطأ من المزوّد: " . $response['error']);
            }
            
            // إرجاع البيانات (يمكن أن تكون مصفوفة أو نص خام)
            if (isset($response['raw'])) {
                return ['raw_response' => $response['raw']];
            }
            
            // إزالة 'ok' من الاستجابة وإرجاع باقي البيانات
            unset($response['ok']);
            return $response;
            
        } catch (Exception $e) {
            $devMode = defined('DEV_MODE') && constant('DEV_MODE');
            if ($devMode) {
                throw $e;
            }
            return null;
        }
    }
    
    public function getBalance() {
        try {
            // Check for development mode
            $devMode = defined('DEV_MODE') && constant('DEV_MODE');
            if ($devMode) {
                return [
                    'balance' => 25.50,
                    'dev_mock' => true
                ];
            }
            
            $response = $this->post(['action' => 'balance']);
            return $response;
            
        } catch (Exception $e) {
            if (defined('DEV_MODE') && constant('DEV_MODE')) {
                throw $e;
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
?>