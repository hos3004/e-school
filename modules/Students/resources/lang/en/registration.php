<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Registration applications',
    'model_label' => 'Registration application',
    'plural_model_label' => 'Registration applications',
    'status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'waiting_assignment' => 'Waiting assignment',
        'assigned' => 'Assigned',
    ],
    'actions' => [
        'submit' => 'Submit application',
        'review' => 'Start review',
        'accept' => 'Accept application',
        'reject' => 'Reject application',
        'reject_heading' => 'Reject registration application',
        'reject_description' => 'The rejection reason is stored with the review decision.',
    ],
    'messages' => [
        'submitted' => 'The registration application has been submitted.',
        'under_review' => 'The application is now under review.',
        'accepted' => 'The application was accepted and the student profile was created.',
        'rejected' => 'The registration application was rejected.',
    ],
    'filters' => [
        'status' => 'Status',
        'country' => 'Country',
        'region' => 'Region',
    ],
    'duplicate' => 'Potential duplicate',
    'duplicate_yes' => 'Yes',
    'duplicate_no' => 'No',
];
