<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Discipline.
| تُستهلك عبر __('discipline::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'unknown_violation_type' => 'نوع المخالفة «:type» غير معروف في إعدادات الانضباط.',

    'already_waived' => 'هذه المخالفة عُفي عنها من قبل، ولا يمكن العفو عنها مرتين.',

    'reactivation_open_exists' => 'يوجد طلب إعادة تفعيل مفتوح لهذا التسجيل بالفعل؛ انتظر حسمه قبل التقديم مجددًا.',
    'reactivation_max_attempts' => 'استُنفدت المحاولات المسموحة لإعادة التفعيل (:max_attempts محاولات).',
    'reactivation_cooldown' => 'لم تنتهِ بعد فترة التهدئة بين محاولات إعادة التفعيل (:days يومًا منذ آخر قرار).',
    'reactivation_invalid_decision' => 'قرار غير صالح؛ الخياران المتاحان هما القبول أو الرفض.',
    'reactivation_invalid_transition' => 'لا يمكن الانتقال بحالة الطلب من «:from» إلى «:to».',
    'reactivation_assessment_required' => 'القبول يتطلب ربط نتيجة اختبار الجدية قبل اعتماد العودة.',
    'reactivation_cancellation_not_allowed' => 'لا يمكن سحب الطلب في حالته الحالية «:status».',
];
