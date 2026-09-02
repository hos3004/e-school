<?php

declare(strict_types=1);

/*
| Filament panel texts — English.
*/

return [
    'navigation_group' => 'People',

    'window_key' => 'Counting window',
    'is_countable' => 'Countable',
    'waived' => 'Waived',
    'attempt_number' => 'Attempt number',
    'submitted_at' => 'Submitted at',
    'reviewed_at' => 'Reviewed at',
    'not_reviewed' => 'Not reviewed yet',
    'threshold_reached' => 'Threshold reached',
    'is_automatic' => 'Automatic',
    'applied_at' => 'Applied at',

    'violations' => [
        'navigation_label' => 'Violations',
        'model_label' => 'Violation',
        'plural_model_label' => 'Violations',
        'waive' => 'Waive violation',
        'waived_notice' => 'Waived; it no longer counts toward escalation.',
    ],

    'actions' => [
        'navigation_label' => 'Discipline actions',
        'model_label' => 'Discipline action',
        'plural_model_label' => 'Discipline actions',
    ],

    'reactivations' => [
        'navigation_label' => 'Reactivation requests',
        'model_label' => 'Reactivation request',
        'plural_model_label' => 'Reactivation requests',
        'approve' => 'Approve request',
        'approved' => 'Approved; the enrollment is active again.',
        'reject' => 'Reject request',
        'rejected' => 'Request rejected.',
        'cancel' => 'Withdraw request',
        'cancelled' => 'Request withdrawn.',
        'assessment_hint' => 'Readiness assessment attempt id (26 chars) — required to approve per discipline config.',
    ],
];
