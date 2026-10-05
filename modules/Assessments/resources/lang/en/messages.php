<?php

declare(strict_types=1);

/*
| General messages of the Assessments module.
| Consumed via __('assessments::messages.key') — no user-facing text outside translation files.
*/

return [
    'created' => 'The assessment was created as a draft. Complete its question bank before opening it.',
    'updated' => 'Assessment settings were saved.',
    'archived' => 'The assessment was archived while preserving its record.',
    'question_added' => 'The question was added and score allocation was updated.',
    'question_removed' => 'The question was removed before attempts started.',
    'graded' => 'The result was approved and recorded on the attempt.',
    'not_available' => 'Not available',
    'not_applicable' => 'Not linked to a course',
    'not_answered' => 'Not answered',
    'system_actor' => 'System',
    'yes' => 'Yes',
    'no' => 'No',
    'attempt_started_reason' => 'The student started the assessment attempt from their account.',
    'attempt_submitted_reason' => 'The student submitted the attempt from their account.',
];
