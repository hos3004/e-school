<?php

declare(strict_types=1);

/*
| حالات طلب التأجيل كما في Modules\Scheduling\Domain\Enums\PostponementStatus.
| تُستهلك عبر PostponementStatus::label() أي __('scheduling::postponement.{value}').
*/

return [
    'requested' => 'بانتظار رد المعلم',
    'alternative_proposed' => 'بانتظار موافقة الطالب على موعد بديل',
    'scheduled' => 'تم الاتفاق على الموعد الجديد',
    'fulfilled' => 'أُقيمت حصة التلافي',
    'rejected' => 'مرفوض',
    'withdrawn' => 'سحبه الطالب',
    'expired' => 'انقضت مهلة الرد — يحتاج تسيير الإدارة',
];
