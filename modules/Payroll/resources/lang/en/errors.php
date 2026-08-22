<?php

declare(strict_types=1);

/*
| Error messages of the Payroll module.
| Consumed via __('payroll::errors.key') — keys describe meaning, not wording.
*/

return [
    'period_not_found' => 'The payroll period was not found.',
    'entry_not_found' => 'The payroll entry was not found.',
    'adjustment_not_found' => 'The adjustment was not found.',
    'ledger_immutable' => 'The payroll ledger is immutable — corrections are made with a new adjustment entry.',
    'period_locked' => 'The period is permanently locked after payment.',
];
