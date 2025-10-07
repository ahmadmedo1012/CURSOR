<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class NewProviderClient {
    private $apiUrl;
    private $apiKey;
    
    public function __construct() {
        $this->apiUrl = 'https://newprovider.com/api/';
        $this->apiKey = 'YOUR_API_KEY_HERE';
    }
    
    /**
     * جلب الخدمات من المزود الجديد
     */
    public function getServices() {
        try {
            $response = $this->post(['action' => 'services']);
            
            if ($response['ok']) {
                return $response;
            } else {
                throw new Exception($response['error'] ?? 'خطأ في جلب الخدمات');
            }
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * إنشاء طلب جديد
     */
    public function createOrder($serviceId, $quantity, $link = '', $username = '') {
        try {
            $fields = [
                'action' => 'add',
                'service' => $serviceId,
                'quantity' => $quantity
            ];
            
            if ($link) {
                $fields['link'] = $link;
            } elseif ($username) {
                $fields['username'] = $username;
            }
            
            $response = $this->post($fields);
            return $response;
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * فحص رصيد المزود
     */
    public function getBalance() {
        try {
            $response = $this->post(['action' => 'balance']);
            return $response;
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * إرسال طلب POST
     */
    private function post(array $fields) {
        $payload = array_merge(['key' => $this->apiKey], $fields);
        
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GameBoxBot/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['ok' => false, 'error' => "cURL Error: $error"];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return array_merge(['ok' => true], $data);
        } else {
            return ['ok' => true, 'raw' => $response];
        }
    }
}
?>

