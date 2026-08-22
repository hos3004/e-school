<?php

declare(strict_types=1);

/*
| Organization module validation messages — consumed by FormRequests.
*/

return [
    'name_required' => 'The organization name is required.',
    'name_ar_required' => 'The Arabic name is required.',
    'slug_required' => 'The slug is required.',
    'slug_immutable' => 'The slug cannot be changed after creation.',
    'invalid_timezone' => 'The timezone is invalid.',
    'currency_size' => 'The currency code must be exactly three letters.',
    'weekday_invalid' => 'The selected week start day is invalid.',

    'calendar_name_required' => 'The academic calendar name is required.',
    'date_required' => 'The date is required.',
    'calendar_not_found' => 'The referenced academic calendar does not exist.',

    'holiday_name_required' => 'The holiday name is required.',
    'holiday_end_before_start' => 'The holiday end date must be on or after the start date.',

    'setting_key_required' => 'The setting key is required.',
    'setting_key_too_long' => 'The setting key exceeds the maximum allowed length.',
    'setting_value_required' => 'The setting value is required (use null to clear it).',

    'use_settings_endpoint' => 'Settings must be modified through their dedicated endpoint.',
];
