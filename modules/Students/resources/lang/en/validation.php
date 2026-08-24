<?php

declare(strict_types=1);

/*
| رسائل تحقق موديول Students — تُستهلك من FormRequests.
*/

return [
    'registration_offering_invalid' => 'The selected course is not available in this organization and program.',

    'user_already_student' => 'This user is already linked to a student profile.',
    'code_taken' => 'The student code is already taken — it must be unique.',
    'birth_before_today' => 'The date of birth must be in the past.',
    'minimum_self_registration_age' => 'Self-registration is available from age :age.',

    'reason_required' => 'The archive reason is required.',
    'country_invalid' => 'The selected country is not available.',
    'region_not_in_country' => 'The selected region does not belong to the selected country.',
    'full_name_required' => 'The full name is required.',
    'date_of_birth_required' => 'A valid date of birth is required.',
    'gender_invalid' => 'The selected gender is invalid.',
    'contact_required' => 'An email address or phone number is required.',
    'import_row_failed' => 'The row could not be imported; verify its account and unique fields.',

];
