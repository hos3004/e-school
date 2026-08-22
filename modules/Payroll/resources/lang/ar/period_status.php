<?php

declare(strict_types=1);

/*
| حالات فترة المستحقات كما في Modules\Payroll\Domain\Enums\PayrollPeriodStatus.
| تُستهلك عبر PayrollPeriodStatus::label() أي __('payroll::period_status.{value}').
*/

return [
    'open' => 'فترة مفتوحة — تستقبل القيود',
    'calculating' => 'قيد الاحتساب',
    'under_review' => 'تحت مراجعة المشرف',
    'approved' => 'معتمدة ماليًا',
    'paid' => 'تم الصرف',
    'locked' => 'مقفلة نهائيًا',
];
