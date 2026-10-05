<?php

declare(strict_types=1);

/*
| Session statuses as in Modules\Sessions\Domain\Enums\SessionStatus.
| Consumed via SessionStatus::label() i.e. __('sessions::status.{value}').
*/

return [
    'draft' => 'Draft',
    'scheduled' => 'Scheduled',
    'confirmed' => 'Confirmed',
    'in_progress' => 'In progress',
    'awaiting_review' => 'Awaiting teacher review',
    'completed' => 'Completed & locked',
    'cancelled_by_student' => 'Cancelled by student (in time)',
    'cancelled_by_teacher' => 'Cancelled by teacher',
    'cancelled_by_school' => 'Cancelled by school',
    'no_show' => 'Student no-show',
    'excused' => 'Excused absence',
    'postponed' => 'Postponed — makeup pending',
    'superseded' => 'Superseded by schedule change',
];
