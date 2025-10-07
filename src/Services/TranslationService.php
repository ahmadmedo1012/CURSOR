<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class TranslationService {
    
    // قاموس ترجمة أساسي للكلمات الشائعة
    private static $commonTranslations = [
        // منصات التواصل
        'instagram' => 'انستغرام',
        'facebook' => 'فيسبوك',
        'tiktok' => 'تيك توك',
        'youtube' => 'يوتيوب',
        'twitter' => 'تويتر',
        'telegram' => 'تيليجرام',
        'snapchat' => 'سناب شات',
        'linkedin' => 'لينكد إن',
        'pinterest' => 'بينتيريست',
        
        // أنواع الخدمات
        'followers' => 'متابعين',
        'likes' => 'إعجابات',
        'views' => 'مشاهدات',
        'comments' => 'تعليقات',
        'shares' => 'مشاركات',
        'subscribers' => 'مشتركين',
        'plays' => 'تشغيلات',
        'downloads' => 'تحميلات',
        
        // مصطلحات تقنية
        'premium' => 'مميز',
        'high' => 'عالي',
        'quality' => 'جودة',
        'fast' => 'سريع',
        'real' => 'حقيقي',
        'guaranteed' => 'مضمون',
        'refill' => 'تجديد',
        'drop' => 'انخفاض',
        'protection' => 'حماية',
        
        // أرقام
        'k' => 'ألف',
        'm' => 'مليون',
        'b' => 'مليار'
    ];
    
    /**
     * ترجمة النص من الإنجليزية إلى العربية
     */
    public static function translate($text) {
        if (empty($text)) return '';
        
        $translated = $text;
        
        // تطبيق الترجمات الأساسية
        foreach (self::$commonTranslations as $en => $ar) {
            $translated = preg_replace('/\b' . preg_quote($en, '/') . '\b/i', $ar, $translated);
        }
        
        // ترجمة الأرقام مع الوحدات
        $translated = self::translateNumbers($translated);
        
        // ترجمة العبارات الشائعة
        $translated = self::translatePhrases($translated);
        
        return $translated;
    }
    
    /**
     * ترجمة الأرقام والوحدات
     */
    private static function translateNumbers($text) {
        // ترجمة الأرقام مع k, m, b
        $text = preg_replace('/(\d+)\s*k\b/i', '$1 ألف', $text);
        $text = preg_replace('/(\d+)\s*m\b/i', '$1 مليون', $text);
        $text = preg_replace('/(\d+)\s*b\b/i', '$1 مليار', $text);
        
        // ترجمة النسب المئوية
        $text = preg_replace('/(\d+)%/i', '$1%', $text);
        
        return $text;
    }
    
    /**
     * ترجمة العبارات الشائعة
     */
    private static function translatePhrases($text) {
        $phrases = [
            'high quality' => 'جودة عالية',
            'real followers' => 'متابعين حقيقيين',
            'fast delivery' => 'توصيل سريع',
            'guaranteed refill' => 'تجديد مضمون',
            'no drop' => 'بدون انخفاض',
            'instant start' => 'بداية فورية',
            'premium quality' => 'جودة مميزة',
            'auto refill' => 'تجديد تلقائي',
            'drop protection' => 'حماية من الانخفاض',
            'high retention' => 'احتفاظ عالي',
            'maximum speed' => 'سرعة قصوى',
            'start time' => 'وقت البداية',
            'speed' => 'السرعة',
            'min' => 'الحد الأدنى',
            'max' => 'الحد الأقصى',
            'per' => 'لكل',
            'for' => 'لـ',
            'with' => 'مع',
            'and' => 'و',
            'or' => 'أو',
            'the' => 'ال',
            'a' => '',
            'an' => '',
            'is' => 'هو',
            'are' => 'هي',
            'in' => 'في',
            'on' => 'على',
            'at' => 'في',
            'to' => 'إلى',
            'from' => 'من',
            'by' => 'بواسطة',
            'of' => 'من',
            'for' => 'لـ'
        ];
        
        foreach ($phrases as $en => $ar) {
            $text = preg_replace('/\b' . preg_quote($en, '/') . '\b/i', $ar, $text);
        }
        
        return $text;
    }
    
    /**
     * ترجمة اسم الخدمة
     */
    public static function translateServiceName($name) {
        return self::translate($name);
    }
    
    /**
     * ترجمة وصف الخدمة
     */
    public static function translateServiceDescription($description) {
        return self::translate($description);
    }
    
    /**
     * ترجمة تصنيف الخدمة
     */
    public static function translateCategory($category) {
        return self::translate($category);
    }
    
    /**
     * استخراج التصنيف الفرعي من اسم الخدمة
     */
    public static function extractSubcategory($name, $category) {
        $name = strtolower($name);
        $category = strtolower($category);
        
        // استخراج نوع الخدمة من الاسم
        if (strpos($name, 'followers') !== false) return 'متابعين';
        if (strpos($name, 'likes') !== false) return 'إعجابات';
        if (strpos($name, 'views') !== false) return 'مشاهدات';
        if (strpos($name, 'comments') !== false) return 'تعليقات';
        if (strpos($name, 'shares') !== false) return 'مشاركات';
        if (strpos($name, 'subscribers') !== false) return 'مشتركين';
        if (strpos($name, 'plays') !== false) return 'تشغيلات';
        
        // استخراج من التصنيف
        if (strpos($category, 'instagram') !== false) return 'انستغرام';
        if (strpos($category, 'facebook') !== false) return 'فيسبوك';
        if (strpos($category, 'tiktok') !== false) return 'تيك توك';
        if (strpos($category, 'youtube') !== false) return 'يوتيوب';
        if (strpos($category, 'twitter') !== false) return 'تويتر';
        
        return 'عام';
    }
    
    /**
     * ترجمة جميع الخدمات في قاعدة البيانات
     */
    public static function translateAllServices() {
        try {
            $services = Database::fetchAll("SELECT id, name, category, description FROM services_cache WHERE name_ar IS NULL OR name_ar = ''");
            
            foreach ($services as $service) {
                $nameAr = self::translateServiceName($service['name']);
                $categoryAr = self::translateCategory($service['category']);
                $descriptionAr = self::translateServiceDescription($service['description']);
                $subcategory = self::extractSubcategory($service['name'], $service['category']);
                
                Database::query(
                    "UPDATE services_cache SET name_ar = ?, category_ar = ?, description_ar = ?, subcategory = ? WHERE id = ?",
                    [$nameAr, $categoryAr, $descriptionAr, $subcategory, $service['id']]
                );
            }
            
            return count($services);
            
        } catch (Exception $e) {
            error_log("خطأ في ترجمة الخدمات: " . $e->getMessage());
            return 0;
        }
    }
}
?>

