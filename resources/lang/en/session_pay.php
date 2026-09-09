<?php

declare(strict_types=1);

return [
    'title' => 'Lesson duration and teacher pay',
    'help' => 'Set teacher pay per lesson type and duration in EGP. New rates apply from saving; historical rates and ledger entries remain unchanged. These rates apply when no teacher-specific rate exists, with an active contract required. Children attend group lessons and adults individual lessons. Removing an option stops offering it for new bookings while retaining its financial history.',
    'name' => 'Lesson name',
    'type' => 'Lesson type',
    'individual' => 'Individual — adults',
    'group' => 'Group — children',
    'duration' => 'Duration in minutes',
    'price' => 'Teacher pay per lesson',
    'new' => 'New lesson',
    'add' => 'Add duration and rate',
    'remove' => 'Remove option',
    'reason' => 'Reason for rate change',
    'save' => 'Save durations and pay',
    'saved' => 'Lesson durations and teacher pay saved.',
    'duplicate' => 'Only one rate is allowed per type and duration.',
];
