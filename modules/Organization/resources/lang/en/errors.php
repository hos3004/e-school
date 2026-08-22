<?php

declare(strict_types=1);

/*
| Organization module error messages.
| Consumed via __('organization::errors.key') — keys describe meaning, not text.
*/

return [
    'unauthorized' => 'You are not authorized to perform this action.',

    'slug_taken' => 'The slug ":slug" is already used by another organization.',

    'calendar_range_invalid' => 'The academic calendar end date must come after its start date.',
    'calendar_overlaps_active' => 'The range ":range" overlaps an already active academic calendar.',

    'holiday_range_invalid' => 'The holiday end date must be on or after its start date.',
    'holiday_too_long' => 'A single holiday may not exceed :max_days days.',
    'holiday_overlaps' => 'This holiday overlaps an existing holiday in the range ":range".',

    'setting_key_too_long' => 'The setting key ":key…" exceeds the maximum allowed length.',
];
