<?php

declare(strict_types=1);

/*
| Enrollment statuses as in Modules\Enrollments\Domain\Enums\EnrollmentStatus.
| Consumed via EnrollmentStatus::label() i.e. __('enrollments::status.{value}').
*/

return [
    'applied' => 'Application submitted',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'active' => 'Active',
    'paused' => 'Paused',
    'frozen' => 'Frozen (disciplinary)',
    'reactivation_requested' => 'Reactivation requested',
    'under_assessment' => 'Under assessment',
    'completed' => 'Programme completed',
    'withdrawn' => 'Withdrawn',
    'rejected' => 'Rejected',
];
