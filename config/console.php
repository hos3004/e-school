<?php

declare(strict_types=1);

return [
    'enabled' => env('CONSOLE_ENABLED', false),
    'primary' => env('CONSOLE_PRIMARY', false),
    'directory_per_page' => 20,
    'directory_search_limit' => 500,
    'report_per_page' => 25,

    /*
     * عمق شاشة اعتماد الحصص بالأيام: كم يومًا للخلف تُعرض الحصص المنتهية
     * التي لم تصل إلى حالة نهائية. رقم سياسة، فلا يعيش داخل الكنترولر.
     */
    'session_review_window_days' => 120,

    /*
     * فحص السعر المسبق في شاشة الاعتماد يكلّف استعلامات لكل صف، فيُشغَّل فقط
     * حين يكون عدد الصفوف في حدود معقولة. فوق الحد لا يُعرض تخمين: يُترك
     * الحقل غير محدَّد بدل إظهار إشارة قد تكون خاطئة.
     */
    'session_review_rate_check_limit' => 60,
];
