<?php

declare(strict_types=1);

/*
| Error messages of the Academics module.
| Consumed via __('academics::errors.key') — keys describe meaning, not wording.
*/

return [
    'program_code_taken' => 'The program code ":code" is already in use.',
    'level_code_taken' => 'The level code ":code" is already in use within this program.',
    'course_code_taken' => 'The course code ":code" is already in use.',
    'program_not_found' => 'The requested program does not exist.',
    'level_not_found' => 'The requested level does not exist.',
    'rate_negative' => 'The default rate cannot be negative.',
    'total_sessions_invalid' => 'Course total sessions must be at least one.',
    'program_has_active_courses' => 'Program ":code" cannot be archived because it has active courses — archive them first.',
    'level_not_in_program' => 'One of the submitted levels does not belong to the given program.',
    'reason_required' => 'A change reason is required.',
    'fixed_program_dates_required' => 'A fixed-duration program requires a start and end date.',
    'ongoing_program_end_forbidden' => 'An ongoing program cannot have an end date; change its type first.',
    'program_end_before_start' => 'The program end date cannot be before its start date.',
    'age_range_invalid' => 'The maximum age cannot be less than the minimum age.',
    'category_code_taken' => 'Category code ":code" is already used in this organization.',
    'category_parent_invalid' => 'The parent category is invalid or belongs to another organization.',
    'category_outside_course_program' => 'A category does not belong to the course organization or program.',
    'organization_required' => 'An organization is required for this academic operation.',
];
