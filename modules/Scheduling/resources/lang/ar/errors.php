<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Scheduling.
| تُستهلك عبر __('scheduling::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'postponement_invalid_transition' => 'لا يمكن نقل طلب التأجيل من :from إلى :to.',
    'rejection_reason_required' => 'سبب الرفض مطلوب.',
];
