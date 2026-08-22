<?php

declare(strict_types=1);

/*
| Payroll entry statuses as in Modules\Payroll\Domain\Enums\PayrollEntryStatus.
| Consumed via PayrollEntryStatus::label() i.e. __('payroll::entry_status.{value}').
*/

return [
    'recorded' => 'Recorded — final',
    'deferred' => 'Deferred until the makeup session',
    'released' => 'Released — now due',
];
