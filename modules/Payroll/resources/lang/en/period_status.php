<?php

declare(strict_types=1);

/*
| Payroll period statuses as in Modules\Payroll\Domain\Enums\PayrollPeriodStatus.
| Consumed via PayrollPeriodStatus::label() i.e. __('payroll::period_status.{value}').
*/

return [
    'open' => 'Open — accepting entries',
    'calculating' => 'Calculating',
    'under_review' => 'Under supervisor review',
    'approved' => 'Financially approved',
    'paid' => 'Paid',
    'locked' => 'Locked',
];
