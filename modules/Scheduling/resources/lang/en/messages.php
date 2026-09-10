<?php

declare(strict_types=1);

/*
| General messages of the Scheduling module.
| Consumed via __('scheduling::messages.key') — no user-facing text outside translation files.
*/

return [
    'generated_from_schedule' => 'Automatically generated from a schedule template.',
    'postponement_requested' => 'The postponement request was sent for review.',
    'teacher_approved_postponement' => 'The teacher approved the proposed time.',
    'postponement_approved' => 'The postponement time was approved.',
    'postponement_alternative_proposed' => 'The alternative time was sent.',
    'student_approved_postponement' => 'The student approved the alternative time proposed by the teacher.',
    'postponement_rejected' => 'The postponement request was rejected.',
    'schedule_change_requested' => 'The permanent time change request was sent to the course students; it applies only once they all accept.',
    'schedule_change_accepted' => 'Your acceptance of the new time was recorded.',
    'schedule_change_declined' => 'Your decline was recorded; the current time stays in place.',
    'schedule_change_withdrawn' => 'The permanent time change request was withdrawn.',
];
