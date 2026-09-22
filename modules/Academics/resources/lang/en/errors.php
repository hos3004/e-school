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
    'program_already_closed' => 'Program :code is already closed.',
    'program_not_closed' => 'Program :code is not closed, so there is nothing to reopen.',
    'program_closure_blocked' => 'Program :code cannot be closed yet because it still holds :blockers. Finish or close what is under it first, then try again. Closing deletes nothing and can be undone by reopening.',
    'course_already_closed' => 'Course :code is already closed.',
    'course_not_closed' => 'Course :code is not closed, so there is nothing to reopen.',
    'course_closure_blocked' => 'Course :code cannot be closed yet because it still holds :blockers. Finish or cancel the remaining sessions, then try again. Closing deletes nothing and can be undone by reopening.',
    'closure_blocker_courses_active' => ':count active course(s)',
    'closure_blocker_enrollments_live' => ':count enrollment(s) still open',
    'closure_blocker_sessions_open' => ':count session(s) not yet in a final state',
    'level_already_closed' => 'Level :code is already closed.',
    'level_not_closed' => 'Level :code is not closed, so there is nothing to reopen.',
    'level_closure_blocked' => 'Level :code cannot be closed yet because it still holds :blockers. Finish or close its courses first, then try again. Closing deletes nothing and can be undone by reopening.',
    'level_closed_parent' => 'Level :code is archived, so it accepts no new course and no course moved into it. Reopen it from the archive first.',
    'program_closed_parent' => 'Program :code is archived, so it accepts no new level. Reopen it from the archive first.',
];
