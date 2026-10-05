<?php

declare(strict_types=1);

return [
    'password_confirmation' => 'Confirm password',
    'role' => 'Operational role',
    'role_scope' => 'Role scope',
    'creation_reason_help' => 'Managed accounts require an explicit role and reason. Use the student, teacher, and guardian onboarding flows for those people.',
    'overview' => 'Account overview',
    'hub' => 'Account hub',
    'roles_tab' => 'Roles and access',
    'devices_tab' => 'Devices and sessions',
    'change_status' => 'Change account status',
    'status_changed' => 'The account status was updated and the reason was audited.',
    'global_role' => 'Global system role',
    'organization_role' => 'Organization role',
    'device_active' => 'Active',
    'device_revoked' => 'Revoked',
    'last_used_at' => 'Last used',
    'last_login_ip' => 'Last login IP',
    'empty' => 'No data is available in this section yet.',
    'optional_access' => 'Optional access',
    'optional_access_manage' => 'Edit optional access',
    'optional_access_help' => 'Sensitive permissions granted to the account itself rather than its role. A supervisor always reads and exports, but sees finance, contact details, or recordings only when the matching switch is turned on here.',
    'optional_access_saved' => 'Optional access was updated and the reason was audited.',
    'optional_access_unchanged' => 'No permission changed; nothing was recorded.',
    'optional_access_granted' => 'Enabled',
    'optional_access_withheld' => 'Withheld',
    'optional_access_permission' => 'Permission',
    'optional_access_state' => 'State',
    // Keys are permission names with dots replaced by underscores; the translator
    // treats a dot as nesting. An untranslated name falls back to the raw string.
    'optional_access_labels' => [
        'payroll_view' => 'Finance and teacher dues',
        'contact_pii_view' => 'Personal contact details (phone and email)',
        'recording_view' => 'Session recordings',
    ],
    'roles' => [
        'platform_admin' => 'Platform administrator',
        'academic_supervisor' => 'Academic supervisor',
        'finance_supervisor' => 'Finance supervisor',
        'registrar' => 'Registrar',
        'communications_officer' => 'Communications officer',
        'teacher' => 'Teacher',
        'student' => 'Student',
        'guardian' => 'Guardian',
        'supervisor' => 'Quality & follow-up supervisor',
        'auditor' => 'Auditor',
    ],
];
