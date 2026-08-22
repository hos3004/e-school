<?php

declare(strict_types=1);

/*
| رسائل الإجراءات — تُستهلك عبر BusinessRuleViolation::make داخل Application\Actions.
| المفتاح يصف القاعدة لا النص.
*/

return [
    'record_entry' => [
        'unknown_outcome' => 'نتيجة الحصة «:outcome» غير معروفة في مصفوفة المستحقات.',
        'currency_mismatch' => 'عملة القيدة «:currency» لا تطابق عملة المنصّة.',
        'period_not_found' => 'فترة المستحقات المطلوبة غير موجودة.',
        'period_closed' => 'الفترة بحالة «:status» ولا تقبل قيودًا جديدة.',
        'duplicate' => 'توجد قيدة سابقة لنفس الحصة ونفس الموظف بهذا النوع.',
    ],
    'propose_adjustment' => [
        'unknown_type' => 'نوع التسوية «:type» غير مدرج في الأنواع المسموحة.',
        'reason_required' => 'التسوية تتطلب كتابة سبب واضح.',
        'invalid_amount' => 'قيمة التسوية غير صالحة: يجب أن تكون غير صفرية، والمكافأة موجبة والخصم سالب.',
        'period_not_found' => 'فترة المستحقات المطلوبة غير موجودة.',
        'period_frozen' => 'الفترة بحالة «:status» مقفلة ماليًا ولا تقبل تسويات.',
    ],
    'approve_adjustment' => [
        'already_decided' => 'هذه التسوية حُسمت من قبل ولا يمكن الاعتماد عليها مرة أخرى.',
        'period_frozen' => 'فترة هذه التسوية مقفلة ماليًا ولا تقبل اعتمادًا.',
        'self_approval' => 'من اقترح التسوية لا يعتمدها — الاعتماد لمشرف آخر.',
    ],
    'reject_adjustment' => [
        'already_decided' => 'هذه التسوية حُسمت من قبل ولا يمكن رفضها مرة أخرى.',
        'reason_required' => 'رفض التسوية يتطلب كتابة سبب واضح.',
        'period_frozen' => 'فترة هذه التسوية مقفلة ماليًا.',
        'self_approval' => 'من اقترح التسوية لا يرفضها — الرفض لمشرف آخر.',
    ],
    'release_deferred' => [
        'none' => 'لا توجد قيود مؤجَّلة مرتبطة بحصة التلافي «:makeup_session_id».',
        'invalid_transition' => 'القيدة :entry_id بحالة «:from» ولا يمكن تحريرها.',
    ],
];
