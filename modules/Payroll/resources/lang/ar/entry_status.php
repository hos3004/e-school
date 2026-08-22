<?php

declare(strict_types=1);

/*
| حالات قيدة المستحقات كما في Modules\Payroll\Domain\Enums\PayrollEntryStatus.
| تُستهلك عبر PayrollEntryStatus::label() أي __('payroll::entry_status.{value}').
*/

return [
    'recorded' => 'مسجّلة نهائيًا',
    'deferred' => 'مؤجّلة حتى حصة التلافي',
    'released' => 'محرّرة — صارت مستحقة',
];
