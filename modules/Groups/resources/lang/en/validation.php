<?php

declare(strict_types=1);

/*
| Custom validation messages of the Groups module.
| Consumed via __('groups::validation.key').
*/

return [
    'code_taken' => 'This group code is already in use.',
    'capacity_too_large' => 'The maximum group capacity is 25 students.',
    'ends_before_starts' => 'The end date must be on or after the start date.',
    'reason_required' => 'A written reason is required for this operation.',
];
