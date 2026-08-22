<?php

declare(strict_types=1);

/*
| Error messages of the Recordings module.
| Consumed via __('recordings::errors.key') — keys describe meaning, not wording.
*/

return [
    'invalid_transition' => 'Cannot move the recording from ":from" to ":to".',
    'duplicate_external_id' => 'A recording with this external ID already exists on :provider.',
    'archive_driver_missing' => 'No archive driver is configured, so the recording cannot be archived.',
    'already_deleted' => 'This recording has already been deleted.',
    'delete_expired' => 'An expired recording (":status") can no longer be deleted.',
    'deleter_required' => 'The deleting user must be identified.',
    'deletion_reason_required' => 'A documented reason is required to delete a recording.',
    'not_watchable' => 'This recording is not watchable in its current status (":status").',
    'download_not_allowed' => 'Downloading recordings is not allowed by policy.',
];
