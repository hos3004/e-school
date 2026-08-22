<?php

declare(strict_types=1);

/*
| رسائل أخطاء قواعد العمل — موديول Audit — عربي.
| تُستهلك عبر BusinessRuleViolation::make('code', 'audit::errors.key').
*/

return [

    'reason_required' => 'الفعل «:action» حسّاس ويشترط سببًا مكتوبًا في القيدة.',

    'action_required' => 'لا يمكن تسجيل قيدة تدقيق بدون فعل محدد.',
];
