<?php

declare(strict_types=1);

/*
| حالات قيد الطالب كما في Modules\Enrollments\Domain\Enums\EnrollmentStatus.
| تُستهلك عبر EnrollmentStatus::label() أي __('enrollments::status.{value}').
*/

return [
    'applied' => 'طلب مُقدَّم',
    'under_review' => 'تحت المراجعة',
    'approved' => 'مقبول',
    'active' => 'نشط',
    'paused' => 'متوقف مؤقتًا',
    'frozen' => 'مجمَّد تأديبيًا',
    'reactivation_requested' => 'طلب فك تجميد',
    'under_assessment' => 'تحت التقييم',
    'completed' => 'أكمل البرنامج',
    'withdrawn' => 'منسحب',
    'rejected' => 'مرفوض',
];
