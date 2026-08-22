<?php

declare(strict_types=1);

/*
| حالات الحصة كما في Modules\Sessions\Domain\Enums\SessionStatus.
| تُستهلك عبر SessionStatus::label() أي __('sessions::status.{value}').
*/

return [
    'draft' => 'مسوّدة',
    'scheduled' => 'مجدولة',
    'confirmed' => 'مؤكَّدة',
    'in_progress' => 'جارية الآن',
    'awaiting_review' => 'بانتظار اعتماد المعلم',
    'completed' => 'مكتملة ومقفلة',
    'cancelled_by_student' => 'ألغاها الطالب في المهلة',
    'cancelled_by_teacher' => 'ألغاها المعلم',
    'cancelled_by_school' => 'ألغتها الإدارة',
    'no_show' => 'لم يحضر الطالب ولم يُخطر',
    'excused' => 'غياب بعذر مقبول',
    'postponed' => 'مؤجَّلة — لها حصة تلافي',
];
