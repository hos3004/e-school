<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Students.
| تُستهلك عبر __('students::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [

    'already_registered' => 'This user is already registered as a student.',
    'code_taken' => 'The student code «:student_code» is already taken — choose another one.',
    'archived_read_only' => 'An archived student profile cannot be modified; restore it first.',
    'already_archived' => 'This student is already archived.',
    'archive_reason_required' => 'An archive reason is required by the audit policy.',
    'not_archived' => 'A student who is not archived cannot be restored.',
    'not_found' => 'The requested student profile was not found.',
    'registration_invalid_transition' => 'The registration application cannot move from “:from” to “:to”.',
    'registration_contact_required' => 'An email address or phone number is required.',
    'registration_required_field_missing' => 'The “:field” field is required before submission.',
    'registration_duplicate_blocked' => 'A previous registration application matches these details.',
    'registration_user_account_required' => 'The application must be linked to a user account before acceptance.',
    'registration_student_profile_exists' => 'A student profile is already linked to this account.',
    'registration_rejection_reason_required' => 'A rejection reason is required.',

];
