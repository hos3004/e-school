<?php

declare(strict_types=1);

/*
| Error messages of the Content module.
| Consumed via __('content::errors.key') — keys describe meaning, not wording.
*/

return [
    'extension_not_allowed' => 'Files with the ":extension" extension are not allowed.',
    'file_requires_storage' => 'File materials require a storage disk and file path.',
    'file_too_large' => 'The file must not exceed :max_mb MB.',
    'link_requires_url' => 'Link materials require a valid external URL.',
    'removal_reason_required' => 'Provide a reason for removing this course material.',
    'visibility_window_invalid' => 'The visibility end time must be on or after its start time.',
    'reason_required' => 'A clear written reason is required for this change.',
    'course_outside_organization' => 'The selected course is unavailable in this organization.',
    'invalid_status_transition' => 'Publishing status cannot move from ":from" to ":to".',
];
