<?php

declare(strict_types=1);

/*
| Assessments module error messages.
| Consumed via __('assessments::errors.key') — keys describe meaning, not wording.
*/

return [
    'passing_score_above_total' => 'The passing score cannot exceed the assessment total score.',
    'invalid_availability_window' => 'The availability start date must precede the availability end date.',
    'invalid_max_attempts' => 'The allowed number of attempts must be at least one.',
    'outside_availability_window' => 'This assessment is not available right now.',
    'max_attempts_exhausted' => 'All allowed attempts for this assessment have been used (:max_attempts attempts).',
    'attempt_already_submitted' => 'This attempt has already been submitted and its answers are locked.',
    'submission_deadline_passed' => 'The submission deadline for this attempt has passed.',
    'grade_before_submission' => 'An attempt cannot be graded before it is submitted.',
    'attempt_already_graded' => 'This attempt has already been graded and its result is locked.',
    'score_out_of_range' => 'The score must be between zero and the assessment total score (:total_score).',
    'archive_with_attempts' => 'An assessment with recorded attempts cannot be archived.',
    'edit_after_attempts' => 'Assessment questions cannot be edited once attempts have been recorded.',
    'question_score_invalid' => 'The question score must be a positive number.',
    'questions_score_exceeds_total' => 'The sum of question scores exceeds the assessment total score.',
    'question_sort_order_taken' => 'Sort order :sort_order is already used by another question in this assessment.',
];
