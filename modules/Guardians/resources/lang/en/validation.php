<?php

declare(strict_types=1);

/*
| Validation messages of the Guardians module.
*/

return [
    'identifier_size' => 'The identifier must be a 26-character ULID.',
    'organization_id_required' => 'The organization identifier is required.',
    'user_id_required' => 'The user account identifier is required.',
    'student_profile_id_required' => 'The student profile identifier is required.',
    'national_id_last4_digits' => 'The last four digits of the national ID must be digits only.',
    'occupation_max' => 'The occupation may not exceed 120 characters.',
    'contact_channel_invalid' => 'The selected contact channel is unknown.',
    'relationship_required' => 'The relationship to the student is required.',
    'relationship_invalid' => 'The selected relationship is unknown.',
    'visible_section_invalid' => 'One of the requested visible sections is not allowed.',
    'reason_required' => 'A reason for this action is required for audit purposes.',
    'reason_min' => 'The reason is too short — please write a clear one.',
];
