# نظام الإشعارات العامة

## نظرة عامة
نظام إشعارات شامل ومتقدم يسمح بإدارة وعرض الإشعارات للعملاء مع إحصائيات مفصلة وتتبع المشاهدات.

## الميزات

### للإدارة:
- ✅ إنشاء وتعديل الإشعارات
- ✅ تحديد الجمهور المستهدف (جميع الزوار، المستخدمين المسجلين، الزوار غير المسجلين)
- ✅ جدولة الإشعارات (تاريخ البداية والنهاية)
- ✅ أنواع مختلفة (معلومات، نجاح، تحذير، خطأ، عرض ترويجي)
- ✅ مستويات أولوية (منخفضة، عادية، عالية، عاجلة)
- ✅ إحصائيات مفصلة (المشاهدات، الرفض، المشاهدات الفريدة)
- ✅ ألوان وأيقونات مخصصة
- ✅ تحديد صفحات العرض
- ✅ إجراءات النقر المخصصة
- ✅ الاختفاء التلقائي
- ✅ إمكانية الرفض

### للعملاء:
- ✅ عرض تلقائي للإشعارات
- ✅ تصميم متجاوب وجميل
- ✅ تأثيرات بصرية متقدمة
- ✅ إمكانية الرفض
- ✅ اختفاء تلقائي
- ✅ تتبع المشاهدات

## الهيكل

### قاعدة البيانات:
```sql
-- جدول الإشعارات الرئيسي
notifications (
    id, title, message, type, priority, target_audience,
    start_date, end_date, is_active, show_on_pages,
    dismissible, auto_dismiss_after, click_action,
    background_color, text_color, icon, created_by,
    created_at, updated_at
)

-- جدول تتبع المشاهدات
notification_views (
    id, notification_id, user_ip, user_agent,
    viewed_at, dismissed, dismissed_at
)

-- جدول الإحصائيات
notification_stats (
    id, notification_id, total_views, total_dismissals,
    unique_views, last_viewed_at, created_at, updated_at
)
```

### الملفات:
```
htdocs/
├── database/008_notifications_system.sql    # Migration
├── src/Services/NotificationManager.php     # منطق الإشعارات
├── src/Components/NotificationDisplay.php   # عرض الإشعارات
├── admin/setup_notifications.php           # إعداد النظام
├── admin/notifications.php                 # لوحة الإدارة
└── api/notifications.php                   # API للإشعارات
```

## الاستخدام

### 1. الإعداد الأولي:
```bash
# اذهب إلى لوحة الإدارة
/admin/setup_notifications.php

# اضغط "بدء الإعداد"
```

### 2. إدارة الإشعارات:
```bash
# اذهب إلى لوحة إدارة الإشعارات
/admin/notifications.php

# أنشئ إشعار جديد
# أو فعّل الإشعارات التجريبية
```

### 3. إنشاء إشعار:
```php
$data = [
    'title' => 'عرض خاص!',
    'message' => 'احصل على خصم 20% على جميع الخدمات',
    'type' => 'promotion',
    'priority' => 'high',
    'target_audience' => 'all',
    'is_active' => true,
    'dismissible' => true,
    'auto_dismiss_after' => 15,
    'background_color' => '#C9A227',
    'text_color' => '#000',
    'icon' => '🎉'
];

$notificationId = NotificationManager::createNotification($data);
```

### 4. عرض الإشعارات في الصفحات:
```php
// في header.php (تم إضافته تلقائياً)
$currentPage = basename($_SERVER['PHP_SELF']);
$targetAudience = Auth::isLoggedIn() ? 'logged_in' : 'guests';
echo NotificationDisplay::render($targetAudience, $currentPage);
```

## API

### جلب الإشعارات النشطة:
```javascript
GET /api/notifications.php?action=active&audience=all&page=index.php
```

### تسجيل عرض الإشعار:
```javascript
POST /api/notifications.php?action=view
{
    "notification_id": 1
}
```

### رفض الإشعار:
```javascript
POST /api/notifications.php?action=dismiss
{
    "notification_id": 1
}
```

## التخصيص

### ألوان مخصصة:
```css
.notification-custom {
    background: linear-gradient(135deg, #your-color 0%, #your-color-2 100%);
    color: #your-text-color;
}
```

### أيقونات مخصصة:
```php
'icon' => '🎉' // Emoji
'icon' => '⭐' // أيقونة أخرى
```

### صفحات محددة:
```php
'show_on_pages' => ['index.php', 'catalog.php', 'order.php']
```

## الأمان

- ✅ التحقق من صحة البيانات
- ✅ حماية من XSS
- ✅ تتبع عنوان IP
- ✅ حماية API
- ✅ تنظيف الإشعارات المنتهية

## الأداء

- ✅ فهرسة قاعدة البيانات
- ✅ تنظيف تلقائي للإشعارات المنتهية
- ✅ تحسين الاستعلامات
- ✅ تخزين مؤقت للبيانات
- ✅ تحميل غير متزامن

## الصيانة

### تنظيف الإشعارات المنتهية:
```php
NotificationManager::cleanupExpiredNotifications();
```

### إحصائيات مفصلة:
```php
$stats = NotificationManager::getNotificationStats($notificationId);
```

## الدعم

للمساعدة أو الإبلاغ عن مشاكل، يرجى التواصل مع فريق الدعم الفني.

---

**تم إنشاء هذا النظام بواسطة فريق تطوير GameBox | Ahmed Mobile Center**

