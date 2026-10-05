<?php

declare(strict_types=1);

/*
| إعدادات موديول Attendance.
|
| كل رقم سياسة يعيش هنا — لا أرقام سياسة داخل الكود. تُدمج تلقائيًا
| عبر BaseModuleServiceProvider وتُقرأ بـ config('attendance.*').
*/

return [

    'admin_hub' => [
        'max_history' => 25,
    ],

    'record' => [
        'reason_min_chars' => 5,
        'reason_max_chars' => 1000,
    ],

    'confirm' => [
        'reason_min_chars' => 5,
        'reason_max_chars' => 1000,
    ],

    // حدود التحقق من الدقائق المُدخلة عند الرصد.
    'limits' => [
        'max_attended_minutes' => 600,
        'max_offset_minutes' => 240,
    ],

    // قاعدة التدقيق: أي تجاوز لحالة الاستنباط يتطلب سببًا مكتوبًا كافيًا.
    'override' => [
        'reason_min_chars' => 5,
        'reason_max_chars' => 1000,
    ],

];
