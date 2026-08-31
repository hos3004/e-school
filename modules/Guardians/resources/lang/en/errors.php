<?php

declare(strict_types=1);

/*
| Business rule violation messages of the Guardians module.
| Consumed via Shared\Support\BusinessRuleViolation::make().
*/

return [
    'student_not_in_organization' => 'The selected student does not belong to the current organization.',
    'existing_account_not_found' => 'The selected account does not exist in the current organization.',
    'profile_already_exists' => 'This account already has a guardian profile; a second one cannot be created.',
    'nothing_to_update' => 'There are no valid fields to update in this request.',
    'link_already_exists' => 'This guardian is already linked to this student.',
    'max_links_per_student_reached' => 'The student has reached the maximum number of guardians (:max).',
    'max_students_per_guardian_reached' => 'The guardian has reached the maximum number of linked students (:max).',
    'link_already_verified' => 'This guardian link is already verified and needs no further verification.',
    'reason_required' => 'Linking or unlinking a guardian requires a written reason.',
    'guardian_not_found' => 'The requested guardian profile does not exist in the current organization.',
];
