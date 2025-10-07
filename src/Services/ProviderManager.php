<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Services/PeakerrClient.php';
require_once BASE_PATH . '/src/Services/NewProviderClient.php';

class ProviderManager {
    private $providers = [];
    
    public function __construct() {
        // تسجيل المزودين
        $this->providers = [
            'peakerr' => new PeakerrClient(),
            'newprovider' => new NewProviderClient()
        ];
    }
    
    /**
     * جلب الخدمات من جميع المزودين
     */
    public function getAllServices() {
        $allServices = [];
        
        foreach ($this->providers as $providerName => $provider) {
            try {
                $services = $provider->getServices();
                if ($services['ok']) {
                    // إضافة معرف المزود لكل خدمة
                    foreach ($services as $key => $service) {
                        if (is_array($service) && isset($service['id'])) {
                            $service['provider'] = $providerName;
                            $allServices[] = $service;
                        }
                    }
                }
            } catch (Exception $e) {
                // تسجيل الخطأ والمتابعة
                error_log("خطأ في مزود $providerName: " . $e->getMessage());
            }
        }
        
        return $allServices;
    }
    
    /**
     * إنشاء طلب من أفضل مزود متاح
     */
    public function createOrder($serviceId, $quantity, $link = '', $username = '') {
        // محاولة مع كل مزود حتى النجاح
        foreach ($this->providers as $providerName => $provider) {
            try {
                $result = $provider->createOrder($serviceId, $quantity, $link, $username);
                if ($result['ok']) {
                    $result['provider'] = $providerName;
                    return $result;
                }
            } catch (Exception $e) {
                continue; // جرب المزود التالي
            }
        }
        
        return ['ok' => false, 'error' => 'جميع المزودين غير متاحين'];
    }
    
    /**
     * فحص رصيد جميع المزودين
     */
    public function getAllBalances() {
        $balances = [];
        
        foreach ($this->providers as $providerName => $provider) {
            try {
                $balance = $provider->getBalance();
                $balances[$providerName] = $balance;
            } catch (Exception $e) {
                $balances[$providerName] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        
        return $balances;
    }
    
    /**
     * جلب مزود محدد
     */
    public function getProvider($name) {
        return $this->providers[$name] ?? null;
    }
    
    /**
     * قائمة المزودين المتاحين
     */
    public function getAvailableProviders() {
        return array_keys($this->providers);
    }
}
?>

