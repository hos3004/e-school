<?php

declare(strict_types=1);

/*
| General messages of the Attendance module.
| Consumed via __('attendance::messages.key') — no user-facing text outside translation files.
*/

return [
    'pending_confirmation' => 'Awaiting confirmation',
    'record_reason' => 'Automatically recorded from classroom join and leave data.',
    'confirm_reason' => 'Confirmed after reviewing the derived attendance status.',
    'system_actor' => 'System',
    'not_available' => 'Not available',
    'demo_override_reason' => 'Administrative correction after reviewing the classroom recording.',
    'sheet_recorded' => 'Recorded from the session attendance sheet.',
    'sheet_saved' => 'Attendance sheet saved.',
    'seeder_no_participants' => 'No session participants exist yet — run the Sessions seeder first to generate demo attendance records.',
];
