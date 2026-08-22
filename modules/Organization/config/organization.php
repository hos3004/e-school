<?php

declare(strict_types=1);

/*
| إعدادات موديول Organization — كل أرقام السياسة تعيش هنا لا في الكود.
*/

return [
    'rules' => [
        // أقصى عدد تقاويم أكاديمية نشطة في نفس اللحظة للمؤسسة الواحدة.
        'max_active_calendars' => 1,

        // أطول مدة ممكنة للعطلة الواحدة بالأيام (شاملة الطرفين).
        'max_holiday_days' => 90,

        // كم يومًا على الأقل قبل بداية التقويم يجوز إنشاء عطلة؟ (0 = بدون قيد)
        'min_days_before_calendar_start' => 0,
    ],

    'limits' => [
        // أقصى طول لمفتاح الإعداد — يطابق عمود key في organization_settings.
        'setting_key_max_length' => 128,
    ],
];
