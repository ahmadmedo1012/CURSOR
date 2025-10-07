<?php

class PlatformIcons {
    
    // أيقونات المنصات الرسمية (SVG)
    private static $platformIcons = [
        'instagram' => [
            'name' => 'انستغرام',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="2" width="20" height="20" rx="6" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                <circle cx="18" cy="6" r="1" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #833ab4 0%, #fd1d1d 50%, #fcb045 100%)',
            'color' => '#E4405F'
        ],
        
        'facebook' => [
            'name' => 'فيسبوك',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" stroke="currentColor" stroke-width="2" fill="none"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #1877f2 0%, #42a5f5 100%)',
            'color' => '#1877F2'
        ],
        
        'tiktok' => [
            'name' => 'تيك توك',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #000000 0%, #ff0050 50%, #00f2ea 100%)',
            'color' => '#000000'
        ],
        
        'youtube' => [
            'name' => 'يوتيوب',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M23 12s0-6.627-5.373-11.5S6.5 0 1 4.5V12s5.5-4.5 11-4.5 11 4.5 11 4.5z" stroke="currentColor" stroke-width="2"/>
                <path d="M9 9l6 3-6 3V9z" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #ff0000 0%, #cc0000 100%)',
            'color' => '#FF0000'
        ],
        
        'twitter' => [
            'name' => 'تويتر',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z" stroke="currentColor" stroke-width="2" fill="none"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #1da1f2 0%, #0d8bd9 100%)',
            'color' => '#1DA1F2'
        ],
        
        'telegram' => [
            'name' => 'تيليجرام',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #0088cc 0%, #229ed9 100%)',
            'color' => '#0088CC'
        ],
        
        'snapchat' => [
            'name' => 'سناب شات',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.746-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001 12.017.001z" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #fffc00 0%, #ffd700 100%)',
            'color' => '#FFFC00'
        ],
        
        'linkedin' => [
            'name' => 'لينكد إن',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" stroke="currentColor" stroke-width="2" fill="none"/>
                <rect x="2" y="9" width="4" height="12" stroke="currentColor" stroke-width="2" fill="none"/>
                <circle cx="4" cy="4" r="2" stroke="currentColor" stroke-width="2" fill="none"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #0077b5 0%, #005885 100%)',
            'color' => '#0077B5'
        ],
        
        'pinterest' => [
            'name' => 'بينتيريست',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12c0 4.84 3.44 8.87 8 9.8-.11-.94-.2-2.4.04-3.43.22-.94 1.41-5.96 1.41-5.96s-.36-.72-.36-1.78c0-1.66.96-2.91 2.16-2.91 1.02 0 1.52.77 1.52 1.69 0 1.03-.65 2.57-.99 3.99-.28 1.19.6 2.17 1.78 2.17 2.13 0 3.77-2.25 3.77-5.49 0-2.86-2.06-4.87-5.01-4.87-3.41 0-5.41 2.56-5.41 5.2 0 1.03.39 2.14.89 2.74.1.12.11.23.08.35-.09.38-.29 1.2-.33 1.37-.05.23-.17.27-.4.17-1.5-.7-2.43-2.88-2.43-4.65 0-3.78 2.75-7.25 7.92-7.25 4.16 0 7.39 2.97 7.39 6.92 0 4.14-2.61 7.46-6.23 7.46-1.21 0-2.36-.63-2.75-1.38l-.75 2.85c-.27 1.04-1 2.35-1.49 3.15C9.57 23.81 10.76 24.01 12 24.01c6.62 0 11.99-5.37 11.99-11.99C24 6.48 18.62 2 12 2z" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #bd081c 0%, #e60023 100%)',
            'color' => '#BD081C'
        ],
        
        'whatsapp' => [
            'name' => 'واتساب',
            'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488" fill="currentColor"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #25d366 0%, #128c7e 100%)',
            'color' => '#25D366'
        ]
    ];
    
    /**
     * جلب أيقونة المنصة
     */
    public static function getPlatformIcon($platform, $size = '24', $class = '') {
        $platform = strtolower($platform);
        
        // البحث عن المنصة في القائمة
        foreach (self::$platformIcons as $key => $data) {
            if (strpos($platform, $key) !== false || strpos($key, $platform) !== false) {
                return [
                    'name' => $data['name'],
                    'icon' => str_replace('width="24"', "width=\"{$size}\"", str_replace('height="24"', "height=\"{$size}\"", $data['icon'])),
                    'gradient' => $data['gradient'],
                    'color' => $data['color'],
                    'class' => $class
                ];
            }
        }
        
        // أيقونة افتراضية
        return [
            'name' => ucfirst($platform),
            'icon' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <path d="M8 12l2 2 4-4" stroke="currentColor" stroke-width="2" fill="none"/>
            </svg>',
            'gradient' => 'linear-gradient(135deg, #6c757d 0%, #495057 100%)',
            'color' => '#6C757D',
            'class' => $class
        ];
    }
    
    /**
     * عرض أيقونة باستخدام SVG مُضمن مع fallback
     */
    public static function renderPlatformIconSVG($platform, $size = '', $class = '') {
        $platform = strtolower(trim($platform));
        
        // Mapping platform names to SVG files (exact lowercase)
        $platformMapping = [
            'tiktok' => 'tiktok.svg',
            'instagram' => 'instagram.svg',
            'facebook' => 'facebook.svg',
            'youtube' => 'youtube.svg',
            'x' => 'x.svg',
            'twitter' => 'x.svg',
            'snapchat' => 'snapchat.svg',
            'telegram' => 'telegram.svg',
            'whatsapp' => 'whatsapp.svg',
            'linkedin' => 'linkedin.svg',
            'pinterest' => 'pinterest.svg'
        ];
        
        // Check if SVG file exists
        $svgFile = $platformMapping[$platform] ?? null;
        $svgPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/svg/' . $svgFile;
        
        // Generate fallback letter
        $firstLetter = strtoupper(substr($platform, 0, 1));
        
        // Size classes
        $sizeClass = '';
        if ($size === 'large' || $size === 'lg') {
            $sizeClass = 'icon--lg';
        }
        
        // Try to render inline SVG
        if ($svgFile && is_readable($svgPath)) {
            $svg = file_get_contents($svgPath);
            
            // Strip UTF-8 BOM & xml decl & DOCTYPE & comments
            $svg = preg_replace('/^\xEF\xBB\xBF|<\?xml.*?\?>|<!DOCTYPE.*?>|<!--.*?-->/s', '', $svg);
            
            // Simple sanity check: must contain <svg
            if (strpos($svg, '<svg') !== false) {
                return '<span class="icon icon--brand ' . $sizeClass . ' ' . $class . '" aria-hidden="true">' . $svg . '</span>';
            }
        }
        
        // Fallback to letter badge
        return '<span class="icon icon--fallback ' . $sizeClass . ' ' . $class . '" data-platform="' . htmlspecialchars($platform) . '" aria-hidden="true">' .
               '<span class="icon__letter">' . htmlspecialchars($firstLetter) . '</span>' .
               '</span>';
    }
    
    /**
     * جلب HTML كامل للأيقونة مع التصميم (Legacy method)
     */
    public static function renderPlatformIcon($platform, $size = '24', $showName = false, $class = '') {
        $iconData = self::getPlatformIcon($platform, $size, $class);
        
        $html = '<div class="platform-icon ' . $class . '" style="--platform-color: ' . $iconData['color'] . '; --platform-gradient: ' . $iconData['gradient'] . ';">';
        $html .= $iconData['icon'];
        
        if ($showName) {
            $html .= '<span class="platform-name">' . $iconData['name'] . '</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * جلب CSS للأيقونات
     */
    public static function getPlatformIconsCSS() {
        return '
        .platform-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--platform-gradient, linear-gradient(135deg, #6c757d 0%, #495057 100%));
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .platform-icon::before {
            content: \'\';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .platform-icon:hover::before {
            opacity: 1;
        }
        
        .platform-icon:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .platform-icon svg {
            width: 20px;
            height: 20px;
            z-index: 1;
        }
        
        .platform-icon .platform-name {
            margin-right: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 1;
        }
        
        .platform-icon.large {
            width: 48px;
            height: 48px;
            border-radius: 12px;
        }
        
        .platform-icon.large svg {
            width: 28px;
            height: 28px;
        }
        
        .platform-icon.small {
            width: 24px;
            height: 24px;
            border-radius: 6px;
        }
        
        .platform-icon.small svg {
            width: 16px;
            height: 16px;
        }
        
        /* أيقونات خاصة للمنصات */
        .platform-icon.instagram {
            background: linear-gradient(135deg, #833ab4 0%, #fd1d1d 50%, #fcb045 100%);
        }
        
        .platform-icon.facebook {
            background: linear-gradient(135deg, #1877f2 0%, #42a5f5 100%);
        }
        
        .platform-icon.tiktok {
            background: linear-gradient(135deg, #000000 0%, #ff0050 50%, #00f2ea 100%);
        }
        
        .platform-icon.youtube {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
        }
        
        .platform-icon.twitter {
            background: linear-gradient(135deg, #1da1f2 0%, #0d8bd9 100%);
        }
        
        .platform-icon.telegram {
            background: linear-gradient(135deg, #0088cc 0%, #229ed9 100%);
        }
        
        .platform-icon.snapchat {
            background: linear-gradient(135deg, #fffc00 0%, #ffd700 100%);
            color: #000;
        }
        
        .platform-icon.linkedin {
            background: linear-gradient(135deg, #0077b5 0%, #005885 100%);
        }
        
        .platform-icon.pinterest {
            background: linear-gradient(135deg, #bd081c 0%, #e60023 100%);
        }
        
        .platform-icon.whatsapp {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
        }
        ';
    }
    
    /**
     * جلب جميع المنصات المتاحة
     */
    public static function getAllPlatforms() {
        return self::$platformIcons;
    }
    
    /**
     * البحث عن المنصة من النص
     */
    public static function detectPlatformFromText($text) {
        $text = strtolower($text);
        
        // Use the same mapping as renderPlatformIconSVG for consistency
        $platformMapping = [
            'tiktok' => 'تيك توك',
            'instagram' => 'انستجرام',
            'facebook' => 'فيسبوك',
            'youtube' => 'يوتيوب',
            'x' => 'تويتر',
            'twitter' => 'تويتر',
            'snapchat' => 'سناب شات',
            'telegram' => 'تيليجرام',
            'whatsapp' => 'واتساب'
        ];
        
        foreach ($platformMapping as $slug => $name) {
            if (strpos($text, $slug) !== false || strpos($text, $name) !== false) {
                return $slug;
            }
        }
        
        return null;
    }
}
?>

