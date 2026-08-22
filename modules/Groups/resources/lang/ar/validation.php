<?php

declare(strict_types=1);

/*
| رسائل التحقق المخصصة لموديول Groups.
| تُستهلك عبر __('groups::validation.key').
*/

return [
    'code_taken' => 'رمز المجموعة مستخدم بالفعل.',
    'capacity_too_large' => 'السعة القصوى للمجموعة هي 25 طالبًا.',
    'ends_before_starts' => 'تاريخ الانتهاء يجب أن يكون في تاريخ البدء أو بعده.',
    'reason_required' => 'كتابة السبب إلزامية لهذه العملية.',
];
