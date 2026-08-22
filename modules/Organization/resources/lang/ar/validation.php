<?php

declare(strict_types=1);

/*
| رسائل التحقق لموديول Organization — تُستهلك من FormRequests.
*/

return [
    'name_required' => 'اسم المؤسسة مطلوب.',
    'name_ar_required' => 'الاسم بالعربية مطلوب.',
    'slug_required' => 'الرمز المختصر (slug) مطلوب.',
    'slug_immutable' => 'لا يمكن تعديل الرمز المختصر بعد الإنشاء.',
    'invalid_timezone' => 'المنطقة الزمنية غير صحيحة.',
    'currency_size' => 'يجب أن يكون رمز العملة ثلاثة أحرف لاتينية.',
    'weekday_invalid' => 'أول يوم في الأسبوع غير صحيح.',

    'calendar_name_required' => 'اسم التقويم الأكاديمي مطلوب.',
    'date_required' => 'التاريخ مطلوب.',
    'calendar_not_found' => 'التقويم الأكاديمي المشار إليه غير موجود.',

    'holiday_name_required' => 'اسم العطلة مطلوب.',
    'holiday_end_before_start' => 'تاريخ نهاية العطلة يجب أن يكون بعد تاريخ بدايتها أو مساويًا له.',

    'setting_key_required' => 'مفتاح الإعداد مطلوب.',
    'setting_key_too_long' => 'مفتاح الإعداد يتجاوز الطول المسموح.',
    'setting_value_required' => 'قيمة الإعداد مطلوبة (استخدم null لإلغائها).',

    'use_settings_endpoint' => 'الإعدادات تُعدَّل عبر مسار الإعدادات المخصص.',
];
