<?php

declare(strict_types=1);

/*
| Error messages of the AcademicReports module.
| Consumed via __('academicreports::errors.key') — keys describe meaning, not wording.
*/

return [
    'session_report_already_submitted' => 'A report has already been submitted for this session; only one report per session is allowed. (Session: :session_id)',
    'session_report_empty_students' => 'A session report cannot be submitted without evaluating at least one student.',
    'session_report_duplicate_student' => 'The student is evaluated more than once in the same report. (Student: :student_profile_id)',
    'session_report_score_out_of_range' => 'Scores must be between :min and :max for each evaluation axis.',
    'monthly_report_duplicate_period' => 'A monthly report for this student already exists for period :month/:year; duplicates are not allowed.',
    'monthly_report_invalid_transition' => 'The monthly report status cannot change from ":from" to ":to" — this transition is not allowed.',
];
