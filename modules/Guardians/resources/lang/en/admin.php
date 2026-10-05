<?php

declare(strict_types=1);

return [
    'common' => [
        'yes' => 'Yes',
        'no' => 'No',
        'not_available' => 'Not available',
    ],
    'fields' => [
        'name' => 'Name',
        'contact' => 'Contact details',
        'email' => 'Email',
        'phone' => 'Phone',
        'username' => 'Username',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'locale' => 'Language',
        'timezone' => 'Timezone',
        'student' => 'Student',
        'student_code' => 'Student code',
        'reason' => 'Action reason',
        'status' => 'Status',
    ],
    'onboarding' => [
        'create_action' => 'Add guardian',
        'account_mode' => 'Account setup mode',
        'new_account' => 'New person and account',
        'existing_account' => 'Link an existing account',
        'existing_user' => 'Existing account',
        'existing_user_help' => 'Search by name, username, email, or phone within the current organization only.',
        'student_optional' => 'Optional: link the first student now or later from the guardian hub.',
        'reason_help' => 'Provide a clear administrative reason for profile creation, linking, and role assignment.',
        'created' => 'The guardian account, profile, and initial link were created successfully.',
        'steps' => [
            'account' => 'Account',
            'account_description' => 'Create a completely new person or link a trusted account from this organization.',
            'profile' => 'Guardian profile',
            'profile_description' => 'Contact and identity data needed to support the family.',
            'student' => 'Student link',
            'student_description' => 'Choose the student, relationship, visibility, and delegation scope.',
        ],
    ],
    'hub' => [
        'title' => 'Guardian hub',
        'overview' => 'Guardian overview',
        'account' => 'Account and contact',
        'students' => 'Students and guardianship',
        'empty' => 'No data is available in this section yet.',
    ],
    'actions' => [
        'link_student' => 'Link student',
        'linked' => 'The student was linked and the action reason was audited.',
        'verify' => 'Verify link',
        'verified' => 'The guardianship link was verified and the reason was audited.',
        'set_primary' => 'Set as primary',
        'primary_set' => 'The primary guardian was updated and the reason was audited.',
        'unlink' => 'Unlink',
        'unlinked' => 'The guardianship link was archived and its history was retained.',
    ],
    'sections' => [
        'attendance' => 'Attendance',
        'schedule' => 'Schedule',
        'grades' => 'Grades',
        'billing' => 'Billing',
        'recordings' => 'Recordings',
    ],
];
