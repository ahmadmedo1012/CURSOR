<?php
require_once '../config/config.php';
require_once '../src/Utils/db.php';
require_once '../src/Services/PeakerrClient.php';

// إعداد نوع المحتوى كنص عادي
header('Content-Type: text/plain; charset=utf-8');

echo "بدء عملية مزامنة الخدمات...\n\n";

try {
    // تشغيل سكريبت إنشاء الجداول
    echo "1. تشغيل سكريبت إنشاء الجداول...\n";
    $sql = file_get_contents(__DIR__ . '/../database/install.sql');
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            Database::query($query);
        }
    }
    echo "✅ تم إنشاء/تحديث الجداول بنجاح\n\n";
    
    // جلب الخدمات من API
    echo "2. جلب الخدمات من Peakerr API...\n";
    $peakerr = new PeakerrClient();
    $services = $peakerr->getServices();
    
    if (!is_array($services)) {
        throw new Exception("استجابة API غير صالحة");
    }
    
    echo "✅ تم جلب " . count($services) . " خدمة من API\n\n";
    
    // مسح البيانات الموجودة
    echo "3. مسح البيانات القديمة...\n";
    $deletedCount = Database::query("DELETE FROM services_cache")->affected_rows;
    echo "✅ تم حذف {$deletedCount} خدمة قديمة\n\n";
    
    // إدخال الخدمات الجديدة
    echo "4. إدخال الخدمات الجديدة...\n";
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($services as $service) {
        try {
                // Mapping مرن للمفاتيح
                $externalId = $service['service'] ?? $service['id'] ?? uniqid();
                $name = $service['name'] ?? $service['service_name'] ?? 'خدمة غير محددة';
                $category = $service['category'] ?? $service['type'] ?? 'عام';
                $rateUsd = floatval($service['rate'] ?? $service['price_per_1k'] ?? $service['rate_per_1k'] ?? 0);
                $rateLyd = round($rateUsd * EXCHANGE_USD_TO_LYD, 4);
                $min = intval($service['min'] ?? $service['minimum'] ?? 0);
                $max = intval($service['max'] ?? $service['maximum'] ?? 0);
                $type = $service['type'] ?? $service['service_type'] ?? 'عام';
                $description = $service['description'] ?? $service['desc'] ?? '';
                
                // تحديد مجموعة الخدمة تلقائياً
                $groupSlug = determineServiceGroup($name, $category);
                
                // التعريب التلقائي
                $nameAr = generateArabicTranslation($name, 'name');
                $categoryAr = generateArabicTranslation($category, 'category');
            
            // التحقق من صحة البيانات الأساسية
            if (empty($name) || $name === 'خدمة غير محددة') {
                $skippedCount++;
                continue;
            }
            
            Database::query(
                "INSERT INTO services_cache (external_id, name, name_ar, category, category_ar, rate_per_1k, rate_per_1k_usd, rate_per_1k_lyd, min, max, type, description, group_slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$externalId, $name, $nameAr, $category, $categoryAr, $rateUsd, $rateUsd, $rateLyd, $min, $max, $type, $description, $groupSlug]
            );
            
            $addedCount++;
            
            // طباعة تفاصيل الخدمة المضافة
            echo "   ✓ {$name} - {$category} - " . number_format($rateLyd, 4) . " LYD ({$groupSlug})\n";
            
        } catch (Exception $e) {
            $skippedCount++;
            echo "   ✗ خطأ في إضافة خدمة: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ تم إضافة {$addedCount} خدمة بنجاح\n";
    if ($skippedCount > 0) {
        echo "⚠️  تم تخطي {$skippedCount} خدمة بسبب أخطاء في البيانات\n";
    }
    
    // إحصائيات نهائية
    echo "\n5. الإحصائيات النهائية:\n";
    $totalServices = Database::fetchOne("SELECT COUNT(*) as count FROM services_cache")['count'];
    $categories = Database::fetchAll("SELECT category, COUNT(*) as count FROM services_cache GROUP BY category ORDER BY count DESC");
    
    echo "   - إجمالي الخدمات: {$totalServices}\n";
    echo "   - عدد الفئات: " . count($categories) . "\n";
    
    if (!empty($categories)) {
        echo "   - الفئات:\n";
        foreach ($categories as $cat) {
            echo "     * {$cat['category']}: {$cat['count']} خدمة\n";
        }
    }
    
    echo "\n🎉 تمت عملية المزامنة بنجاح!\n";
    echo "يمكنك الآن زيارة /catalog.php لعرض الخدمات\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ في عملية المزامنة:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "يرجى التحقق من:\n";
    echo "1. إعدادات قاعدة البيانات في config/config.php\n";
    echo "2. إعدادات API في config/config.php\n";
    echo "3. الاتصال بالإنترنت\n";
    echo "4. صلاحيات قاعدة البيانات\n";
}

echo "\n--- انتهت عملية المزامنة ---\n";

// دالة تحديد مجموعة الخدمة المحسنة
function determineServiceGroup($name, $category) {
    $name = strtolower($name);
    $category = strtolower($category);
    $text = $name . ' ' . $category;
    
    // TikTok
    if (strpos($text, 'tiktok') !== false) {
        return 'tiktok';
    }
    
    // Instagram
    if (strpos($text, 'instagram') !== false || strpos($text, 'insta') !== false || 
        strpos($text, 'ig') !== false) {
        return 'instagram';
    }
    
    // Facebook
    if (strpos($text, 'facebook') !== false || strpos($text, 'fb') !== false || 
        strpos($text, 'meta') !== false) {
        return 'facebook';
    }
    
    // YouTube
    if (strpos($text, 'youtube') !== false || strpos($text, 'yt') !== false || 
        strpos($text, 'youtu') !== false) {
        return 'youtube';
    }
    
    // Twitter/X
    if (strpos($text, 'twitter') !== false || strpos($text, 'x.com') !== false || 
        strpos($text, 'tweet') !== false) {
        return 'twitter';
    }
    
    // Snapchat
    if (strpos($text, 'snapchat') !== false || strpos($text, 'snap') !== false) {
        return 'snapchat';
    }
    
    // Telegram
    if (strpos($text, 'telegram') !== false || strpos($text, 'tg') !== false || 
        strpos($text, 't.me') !== false) {
        return 'telegram';
    }
    
    // Discord
    if (strpos($text, 'discord') !== false) {
        return 'discord';
    }
    
    // LinkedIn
    if (strpos($text, 'linkedin') !== false || strpos($text, 'linked') !== false) {
        return 'linkedin';
    }
    
    // Pinterest
    if (strpos($text, 'pinterest') !== false || strpos($text, 'pin') !== false) {
        return 'pinterest';
    }
    
    // Twitch
    if (strpos($text, 'twitch') !== false || strpos($text, 'stream') !== false) {
        return 'twitch';
    }
    
    // Spotify
    if (strpos($text, 'spotify') !== false || strpos($text, 'music') !== false) {
        return 'spotify';
    }
    
    // Reddit
    if (strpos($text, 'reddit') !== false) {
        return 'reddit';
    }
    
    // WhatsApp
    if (strpos($text, 'whatsapp') !== false || strpos($text, 'wa.me') !== false) {
        return 'whatsapp';
    }
    
    // VK
    if (strpos($text, 'vk.com') !== false || strpos($text, 'vkontakte') !== false) {
        return 'vk';
    }
    
    // Tumblr
    if (strpos($text, 'tumblr') !== false) {
        return 'tumblr';
    }
    
    // Quora
    if (strpos($text, 'quora') !== false) {
        return 'quora';
    }
    
    // ألعاب وتطبيقات
    if (strpos($text, 'game') !== false || strpos($text, 'mobile') !== false || 
        strpos($text, 'app') !== false || strpos($text, 'ios') !== false || 
        strpos($text, 'android') !== false) {
        return 'games';
    }
    
    // عام
    return 'general';
}

// دالة التعريب التلقائي
function generateArabicTranslation($text, $type = 'name') {
    $text = strtolower($text);
    
    // ترجمة المنصات
    $platforms = [
        'tiktok' => 'تيك توك',
        'instagram' => 'إنستغرام',
        'facebook' => 'فيسبوك',
        'youtube' => 'يوتيوب',
        'twitter' => 'تويتر',
        'snapchat' => 'سناب شات',
        'telegram' => 'تيليجرام',
        'discord' => 'ديسكورد',
        'linkedin' => 'لينكد إن',
        'pinterest' => 'بينتيريست',
        'twitch' => 'تويتش',
        'spotify' => 'سبوتيفاي'
    ];
    
    // ترجمة الكلمات المفتاحية
    $keywords = [
        'followers' => 'متابعين',
        'likes' => 'إعجابات',
        'views' => 'مشاهدات',
        'comments' => 'تعليقات',
        'shares' => 'مشاركات',
        'subscribers' => 'مشتركين',
        'plays' => 'تشغيلات',
        'downloads' => 'تحميلات',
        'streams' => 'مشاهدات',
        'reactions' => 'تفاعلات',
        'clicks' => 'نقرات',
        'impressions' => 'عرضات',
        'real' => 'حقيقي',
        'active' => 'نشط',
        'premium' => 'مميز',
        'fast' => 'سريع',
        'instant' => 'فوري',
        'guaranteed' => 'مضمون',
        'high quality' => 'جودة عالية',
        'cheap' => 'رخيص',
        'best' => 'أفضل',
        'top' => 'أعلى',
        'new' => 'جديد',
        'hot' => 'شائع'
    ];
    
    $arabicText = $text;
    
    // تطبيق ترجمات المنصات
    foreach ($platforms as $english => $arabic) {
        $arabicText = str_replace($english, $arabic, $arabicText);
    }
    
    // تطبيق ترجمات الكلمات المفتاحية
    foreach ($keywords as $english => $arabic) {
        $arabicText = str_replace($english, $arabic, $arabicText);
    }
    
    // تنظيف النص النهائي
    $arabicText = trim($arabicText);
    $arabicText = preg_replace('/\s+/', ' ', $arabicText);
    
    // إذا لم يتم ترجمة أي شيء، أعد نص فارغ
    if ($arabicText === $text || empty($arabicText)) {
        return '';
    }
    
    return $arabicText;
}
?>
