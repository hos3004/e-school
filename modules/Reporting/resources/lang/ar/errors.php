<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Reporting.
| تُستهلك عبر __('reporting::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [

    'missing_projection_key' => 'بيانات الإسقاط ناقصة: الحقل «:field» مطلوب لتحديث اللوحة.',

    'unknown_student_metric' => 'مقياس غير معروف للوحة الطالب: «:metric». راجع خريطة config/reporting.php.',

    'unknown_teacher_metric' => 'مقياس غير معروف للوحة المعلم: «:metric». راجع خريطة config/reporting.php.',

    'negative_payout_delta' => 'قيمة مستحق سالبة (:amount_minor) — لا يجوز إضافتها للوحة المعلم.',

    'negative_counter_value' => 'قيمة العدّاد يجب أن تكون صفرًا أو أكبر (القيمة المرسلة: :value).',

    'correction_reason_required' => 'التصحيح اليدوي يتطلب سببًا مكتوبًا.',

    'correction_reason_length' => 'سبب التصحيح يجب أن يكون بين :min و :max حرفًا حتى يكون التوثيق كافيًا.',

    'unknown_correction_column' => 'العمود «:column» ليس من الأعمدة القابلة للتصحيح.',

    'dashboard_not_found' => 'لا توجد لوحة للتسجيل «:enrollment_id» — قد تكون لم تُبنَ بعد من الأحداث.',

    'snapshot_exists' => 'لقطة اليوم مسجَّلة بالفعل وسيتم تحديثها بدل تكرارها.',

];
