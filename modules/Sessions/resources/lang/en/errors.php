<?php

declare(strict_types=1);

return [
    'substitute_same_teacher' => 'The substitute is the teacher already assigned to this session.',
    'substitute_reason_required' => 'A reason for the substitution is required.',
    'substitute_not_qualified' => 'This teacher is not qualified for this course. An administrative override with a written reason is required.',
    'substitute_not_available' => 'This teacher is not available at this time: :conflicts overlapping session(s). An administrative override with a written reason is required.',
    'override_reason_required' => 'An administrative override requires a written reason.',
    'substitute_not_allowed_in_status' => 'A substitute cannot be assigned to a session in status :status.',
    'attendance_incomplete' => 'The session cannot be finalised until attendance is confirmed for every participant.',
    'invalid_transition' => 'Transition from :from to :to is not allowed.',
    'apology_reason_required' => 'A reason for the apology is required.',
    'apology_not_assigned_teacher' => 'You cannot apologise for a session that is not assigned to you.',
    'apology_already_pending' => 'You already have a pending apology for this session.',
    'apology_session_closed' => 'A session with status :status cannot be apologised for.',
    'apology_rejection_reason_required' => 'Rejecting an apology requires a written reason.',
    'apology_invalid_transition' => 'Apology transition from :from to :to is not allowed.',
    'apology_must_not_change_session' => 'Internal error: approving the apology changed the session status. An apology never cancels a session.',
];
