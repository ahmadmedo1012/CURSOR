<?php
// ملف مزودي API - أضف كل مزود كسطر جديد في المصفوفة
// مثال:
// [ 'name' => 'Peakerr', 'url' => 'https://peakerr.com/api/v2', 'token' => 'xxxx' ]
return [
    [
        'name' => 'Peakerr',
        'url'  => defined('PEAKERR_API_URL') ? PEAKERR_API_URL : '',
        'token'=> defined('PEAKERR_API_KEY') ? ad651911f7d5d6409b2cd8d292c7353d : '',
    ],
    // أضف مزودين جدد هنا بسهولة:
    // [ 'name' => 'اسم المزود', 'url' => 'رابط الـ API', 'token' => 'التوكن' ],
];
