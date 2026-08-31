<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Learning and assessment',
    'assignments' => ['navigation_label' => 'Assignments', 'model_label' => 'Assignment', 'plural_model_label' => 'Assignments'],
    'sections' => [
        'audience' => 'Academic audience',
        'audience_help' => 'Select a program and course, then choose a group or leave it empty for all active course students.',
        'content' => 'Content and instructions',
        'grading' => 'Timing and grading policy',
    ],
    'course_wide_help' => 'Leave the group empty to target all active course students.',
    'reason_help' => 'The reason is stored in the audit trail and is not shown to students.',
    'actions' => ['edit' => 'Edit', 'archive' => 'Archive', 'grade' => 'Grade and approve'],
    'metrics' => [
        'recipients' => 'Target students', 'pending' => 'Not submitted', 'submitted' => 'Submitted on time',
        'late' => 'Submitted late', 'graded' => 'Graded', 'awaiting_grading' => 'Awaiting grading',
    ],
    'hub' => [
        'overview' => 'Assignment overview', 'metrics' => 'Delivery metrics', 'history' => 'Submissions and history',
        'submission_snapshot' => 'Submission snapshot', 'audit' => 'Audit trail', 'no_audit' => 'No recorded changes yet.',
        'action' => 'Action', 'actor' => 'Actor', 'changed_at' => 'Changed at',
    ],
    'submissions' => [
        'title' => 'Student submissions', 'student' => 'Student', 'submitted_at' => 'Submitted at', 'is_late' => 'Late',
        'raw_score' => 'Score before penalty', 'penalty_points' => 'Penalty points', 'empty' => 'No students currently target this assignment.',
    ],
];
