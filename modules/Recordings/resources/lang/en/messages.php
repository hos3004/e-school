<?php

declare(strict_types=1);

/*
| General messages of the Recordings module.
| Consumed via __('recordings::messages.key') — no user-facing text outside translation files.
*/

return [
    'duration_minutes' => ':minutes min',
    'seeder_no_organization' => 'Recordings seeder: no organization found, skipping.',
    'seeder_no_sessions' => 'Recordings seeder: no sessions found (owned by another module), skipping.',
    'unavailable' => 'Unavailable',
    'system_actor' => 'System',
    'provider_ingestion_reason' => 'Recording received from the virtual-classroom provider.',
    'processing_completed_reason' => 'The provider completed recording processing.',
    'retention_archive_reason' => 'Recording archived under the retention policy.',
    'retention_expiry_reason' => 'Recording retention period expired.',
    'retention_summary' => ':count recordings processed under the retention policy.',
    'access_granted' => 'Recording access granted.',
    'access_revoked' => 'Recording access revoked.',
    'archived' => 'Recording archived.',
    'deleted' => 'Recording suspended with a documented reason.',
    'size_megabytes' => ':size MB',
    'size_gigabytes' => ':size GB',
];
