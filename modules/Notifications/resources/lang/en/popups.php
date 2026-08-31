<?php

declare(strict_types=1);

return [

    'status' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'paused' => 'Paused',
        'archived' => 'Archived',
    ],

    'effective_status' => [
        'scheduled' => 'Scheduled',
        'active' => 'Active now',
        'expired' => 'Expired',
    ],

    'type' => [
        'urgent_announcement' => 'Urgent announcement',
        'program_promotion' => 'Program promotion',
        'reminder' => 'Reminder',
        'administrative' => 'Administrative notice',
        'general' => 'General announcement',
    ],

    'audience' => [
        'student' => 'Students',
        'guardian' => 'Guardians',
        'teacher' => 'Teachers',
        'supervisor' => 'Supervisors',
        'administrator' => 'Administrators',
        'all_authenticated' => 'All signed-in users',
    ],

    'placement' => [
        'after_login' => 'After login',
        'dashboard' => 'User dashboard',
        'specific_page' => 'Specific page',
        'all_authenticated_pages' => 'First eligible page',
    ],

    'frequency' => [
        'once' => 'Once',
        'once_per_login' => 'Once per login',
        'once_per_day' => 'Once per day',
        'until_acknowledged' => 'Until acknowledged',
        'every_eligible_visit' => 'Every eligible visit (limited use)',
    ],

    'frequency_help' => [
        'once' => 'Shown once per user, ever',
        'once_per_login' => 'Shown once after each login',
        'once_per_day' => 'Once per day in UTC',
        'until_acknowledged' => 'Keeps appearing until the user acknowledges it',
        'every_eligible_visit' => 'Every visit — use with extreme care',
    ],

    'pages' => [
        'student_dashboard' => 'Student dashboard',
        'student_schedule' => 'Student schedule',
        'guardian_dashboard' => 'Guardian dashboard',
        'teacher_dashboard' => 'Teacher dashboard',
        'admin_dashboard' => 'Admin panel',
    ],

    'tabs' => [
        'content' => 'Content',
        'audience' => 'Audience',
        'display' => 'Display',
        'scheduling' => 'Scheduling',
        'review' => 'Review & preview',
    ],

    'fields' => [
        'internal_name' => 'Internal name',
        'type' => 'Popup type',
        'title_ar' => 'Title (Arabic)',
        'title_en' => 'Title (English)',
        'title_fr' => 'Title (French)',
        'body_ar' => 'Body (Arabic)',
        'body_en' => 'Body (English)',
        'body_fr' => 'Body (French)',
        'arabic_content' => 'Arabic content (mandatory)',
        'optional_translations' => 'Optional translations',
        'plain_text_help' => 'Plain text only — no HTML or code. Always rendered escaped.',
        'cta_section' => 'Action button (optional)',
        'action_type' => 'Action type',
        'internal_page' => 'Approved internal page',
        'external_url' => 'External URL (HTTPS only)',
        'external_url_help' => 'Opens safely in a new tab. Non-HTTPS links are rejected.',
        'action_label_ar' => 'Button label (Arabic)',
        'audiences' => 'Target audiences',
        'audiences_help' => 'Pick at least one audience. "Everyone" covers all authenticated users.',
        'placement' => 'Placement',
        'page_key' => 'Target page',
        'frequency' => 'Frequency rule',
        'is_dismissible' => 'User can dismiss it',
        'requires_acknowledgement' => 'Requires explicit acknowledgement',
        'acknowledgement_label' => 'Acknowledgement button label',
        'priority' => 'Priority (higher shows first)',
        'starts_at' => 'Show from (UTC)',
        'ends_at' => 'Show until (UTC) — optional',
        'reason' => 'Change reason',
        'reason_help' => 'A clear reason recorded in the audit log.',
    ],

    'options' => [
        'no_action' => 'No button',
    ],

    'actions' => [
        'create' => 'New campaign',
        'view' => 'View',
        'edit' => 'Edit',
        'publish' => 'Publish',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'duplicate' => 'Duplicate as draft',
        'archive' => 'Archive',
    ],

    'confirm' => [
        'publish_description' => 'The campaign becomes visible to the selected audience once its window opens, following the frequency and priority rules.',
        'archive_description' => 'Archiving is final: the campaign never appears again and cannot be edited. Duplicate-as-draft is the safe alternative for deep changes.',
    ],

    'messages' => [
        'status_changed' => 'Campaign status updated.',
        'duplicated' => 'A new draft copy was created.',
    ],

    'errors' => [
        'reason_required' => 'A change reason is required and is recorded in the audit log.',
        'invalid_transition' => 'This status transition is not allowed.',
        'arabic_content_required' => 'An Arabic title and body are mandatory before publishing.',
        'audience_required' => 'Select at least one audience.',
        'unsafe_exit' => 'A popup that can neither be dismissed nor acknowledged would trap the user.',
        'invalid_page_key' => 'The selected page is not in the approved registry.',
        'invalid_window' => 'The end of the display window must come after its start.',
        'locked_while_published' => 'Published campaigns are locked — pause first or duplicate as a draft.',
        'not_available' => 'This campaign is not available right now.',
        'not_dismissible' => 'This campaign cannot be dismissed.',
        'no_acknowledgement' => 'This campaign does not require acknowledgement.',
        'no_action' => 'This campaign has no action button.',
        'invalid_interaction' => 'Unknown interaction.',
    ],

    'filters' => [
        'active_now' => 'Active now',
    ],

    'view' => [
        'overview' => 'Overview',
        'analytics' => 'Analytics',
        'audit_note' => 'Origin & audit',
        'created_by' => 'Created by',
        'updated_by' => 'Last updated by',
        'published_by' => 'Published by',
        'published_at' => 'Published at',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'analytics' => [
        'seen_users' => 'Unique viewers',
        'impressions' => 'Impressions',
        'acknowledgements' => 'Acknowledgements',
        'dismissals' => 'Dismissals',
        'clicks' => 'CTA clicks',
        'ctr' => 'Click-through rate (CTR)',
    ],

    'preview' => [
        'action' => 'Preview',
        'banner' => 'Preview — this is not a live popup; no statistics are recorded',
        'no_tracking_note' => 'Previews never record impressions, clicks, or acknowledgements.',
        'unsafe_exit_warning' => 'Warning: this campaign would be rejected at publish time because it traps the user (no dismiss, no acknowledgement).',
    ],

    'frontend' => [
        'acknowledge_default' => 'Got it',
        'dismiss' => 'Dismiss',
    ],

    'duplicate_suffix' => 'copy',
];
