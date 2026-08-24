<?php

declare(strict_types=1);

/*
| Error messages of the Scheduling module.
| Consumed via __('scheduling::errors.key') — keys describe meaning, not wording.
*/

return [
    'postponement_invalid_transition' => 'The postponement request cannot transition from :from to :to.',
    'rejection_reason_required' => 'A rejection reason is required.',
];
