<?php

declare(strict_types=1);

/*
| Validation messages — academicreports::validation.*
*/

return [
    'session_id_required' => 'The session identifier is required.',
    'staff_profile_id_required' => 'The teacher identifier is required.',
    'student_profile_id_required' => 'The student identifier is required.',
    'enrollment_id_required' => 'The enrollment identifier is required.',
    'period_year_required' => 'The period year is required.',
    'period_month_invalid' => 'The period month must be between 1 and 12.',
    'students_required' => 'Student evaluations are required.',
    'students_min' => 'At least one student must be evaluated in the report.',
    'students_distinct' => 'The same student cannot be evaluated more than once.',
    'score_range' => 'Each score must be an integer between 1 and 5.',
    'reason_required' => 'An approval reason is required for audit purposes.',
    'reason_min' => 'The approval reason is too short — please write a clear reason.',
];
