<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Organization.
| تُستهلك عبر __('organization::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'unauthorized' => 'لا تملك صلاحية تنفيذ هذا الإجراء.',

    'slug_taken' => 'الرمز المختصر «:slug» مستخدم بالفعل لمؤسسة أخرى.',

    'calendar_range_invalid' => 'يجب أن يكون تاريخ نهاية التقويم الأكاديمي بعد تاريخ بدايته.',
    'calendar_overlaps_active' => 'يتقاطع النطاق «:range» مع تقويم أكاديمي نشط بالفعل.',

    'holiday_range_invalid' => 'يجب أن يكون تاريخ نهاية العطلة بعد تاريخ بدايتها أو مساويًا له.',
    'holiday_too_long' => 'لا يجوز أن تتجاوز مدة العطلة الواحدة :max_days يومًا.',
    'holiday_overlaps' => 'تتقاطع هذه العطلة مع عطلة قائمة في النطاق «:range».',

    'setting_key_too_long' => 'مفتاح الإعداد «:key…» أطول من الحد المسموح.',
];
