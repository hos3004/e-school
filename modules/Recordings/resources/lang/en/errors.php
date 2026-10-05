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
    'grant_target_invalid' => 'Choose exactly one recipient: a user or a group.',
    'grant_reason_required' => 'A documented reason is required to grant recording access.',
    'grant_expiry_invalid' => 'The recording access expiry must be in the future.',
    'grant_status_invalid' => 'Access cannot be granted while the recording is :status.',
    'granter_required' => 'The granter must be a valid user in the organization.',
    'grant_target_not_found' => 'The selected recipient does not exist in this organization.',
    'grant_duplicate' => 'An active grant already exists for this recipient.',
    'revocation_context_required' => 'Revoking a grant requires an actor and a documented reason.',
    'grant_not_found' => 'The access grant does not belong to this recording.',
    'grant_already_revoked' => 'The access grant has already been revoked.',
    'context_invalid' => 'The session or classroom does not belong to the selected organization.',
];
