<?php

declare(strict_types=1);

return [
    'sections' => ['audience' => 'Academic context', 'content' => 'Title and instructions', 'scoring' => 'Scoring and attempts policy', 'availability' => 'Availability window', 'overview' => 'Assessment overview', 'metrics' => 'Operational metrics', 'questions' => 'Question bank', 'attempts' => 'Student attempts', 'answers' => 'Attempt answers', 'audit' => 'Audit trail'],
    'actions' => ['edit' => 'Edit settings', 'archive' => 'Archive', 'add_question' => 'Add question', 'remove_question' => 'Remove question', 'grade' => 'Grade and approve result'],
    'helpers' => ['course_optional' => 'A course is required for quizzes and exams and optional for placement/reactivation assessments.', 'reason' => 'The reason is stored in the audit trail and is not shown to students.', 'question_options' => 'Use short unique keys such as a, b and c, then enter the correct key.', 'score_lock' => 'Question scores must equal the assessment total before it can open.'],
    'metrics' => ['questions' => 'Questions', 'allocated_score' => 'Allocated score', 'remaining_score' => 'Remaining score', 'attempts' => 'All attempts', 'awaiting_grading' => 'Awaiting grading', 'passed' => 'Passed', 'failed' => 'Failed'],
    'hub' => ['history' => 'Operations and history', 'question_snapshot' => 'Question bank snapshot', 'attempt_snapshot' => 'Attempts snapshot', 'audit' => 'Audit trail', 'no_audit' => 'No changes have been recorded yet.', 'action' => 'Action', 'actor' => 'Actor', 'changed_at' => 'Changed at'],
    'empty' => ['questions' => 'No questions have been added.', 'attempts' => 'No student has started an attempt.'],
];
