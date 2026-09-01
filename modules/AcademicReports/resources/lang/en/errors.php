<?php

declare(strict_types=1);

/*
| Error messages of the AcademicReports module.
| Consumed via __('academicreports::errors.key') — keys describe meaning, not wording.
*/

return [
    'session_report_session_not_found' => 'The session was not found in your organization.',
    'session_report_teacher_not_assigned' => 'You cannot submit a report for a session not assigned to you.',
    'session_report_invalid_session_state' => 'A report cannot be submitted in the current session state (:status).',
    'session_report_students_mismatch' => 'The report must include all and only the current session students.',
    'session_report_already_submitted' => 'A report has already been submitted for this session; only one report per session is allowed. (Session: :session_id)',
    'session_report_empty_students' => 'A session report cannot be submitted without evaluating at least one student.',
    'session_report_duplicate_student' => 'The student is evaluated more than once in the same report. (Student: :student_profile_id)',
    'session_report_score_out_of_range' => 'Scores must be between :min and :max for each evaluation axis.',
    'monthly_report_duplicate_period' => 'A monthly report for this student already exists for period :month/:year; duplicates are not allowed.',
    'monthly_report_invalid_transition' => 'The monthly report status cannot change from ":from" to ":to" — this transition is not allowed.',
    'monthly_report_invalid_student' => 'The student is missing or archived in your organization.',
    'monthly_report_invalid_enrollment' => 'The selected enrollment does not belong to this student in your organization.',
];
