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
];
