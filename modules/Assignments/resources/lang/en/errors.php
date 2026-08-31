<?php

declare(strict_types=1);

return [
    'due_before_assigned' => 'The due date must be after the assignment availability time.',
    'invalid_late_penalty' => 'The late penalty percentage is invalid.',
    'invalid_max_score' => 'The maximum score must be greater than zero.',
    'invalid_target' => 'The selected course or group does not belong to the organization academic path.',
    'teacher_not_eligible' => 'The teacher is not qualified for the course or assigned to the selected group.',
    'invalid_update' => 'The timing or grading data in this update is invalid.',
    'audience_locked' => 'The audience cannot change after student records are created. Create another assignment for a different audience.',
    'ungraded_submissions' => 'The assignment cannot be archived while submissions await grading.',
    'submission_not_pending' => 'This submission cannot be resubmitted in its current state.',
    'late_not_allowed' => 'The deadline has passed and late submissions are not allowed.',
    'grade_before_submission' => 'A grade cannot be recorded before the student submits.',
    'submission_already_graded' => 'This submission has already been graded.',
    'invalid_status_transition' => 'This submission status transition is not allowed.',
    'score_out_of_range' => 'The score must be between zero and the assignment maximum.',
];
