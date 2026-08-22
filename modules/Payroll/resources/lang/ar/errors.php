<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Payroll.
| تُستهلك عبر __('payroll::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'period_not_found' => 'فترة المستحقات غير موجودة.',
    'entry_not_found' => 'قيدة المستحقات غير موجودة.',
    'adjustment_not_found' => 'التسوية غير موجودة.',
    'ledger_immutable' => 'دفتر المستحقات لا يُعدَّل — التصحيح بقيدة تسوية جديدة.',
    'period_locked' => 'الفترة مقفلة نهائيًا بعد الصرف.',
];
