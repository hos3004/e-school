<?php

declare(strict_types=1);

return [
    'title' => 'Messaging',
    'open' => 'Send a message',
    'send' => 'Send',
    'cancel' => 'Cancel',
    'preview' => 'Preview',

    'already_sent' => 'This request was already processed under the same id; no new message was queued.',
    'queued' => ':count message(s) queued for :recipients recipient(s).',
    'scheduled' => ':count message(s) scheduled for :recipients recipient(s) at the chosen time.',

    'channels' => [
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'in_app' => 'In-app',
        'sms' => 'SMS',
        'push' => 'Push',
    ],

    'audiences' => [
        'students' => 'Students only',
        'teacher' => 'Teacher only',
        'all' => 'Everyone on the class',
    ],

    'targets' => [
        'group' => 'Group',
        'course' => 'Course',
        'schedule' => 'Schedule',
    ],

    'bulk_title' => 'Message the class',
    'bulk_hint' => 'Each recipient receives a separate individual message.',
    'choose_target' => 'Choose a target',
    'no_targets' => 'No targets available for this type.',
    'template_none' => 'No template',

    'scheduled_title' => 'Scheduled messages not yet due',
    'templates_title' => 'Message templates',
    'templates_hint' => 'A global template is a shared reference: read-only here. Create an organization copy to customise it.',
    'template_new' => 'New template',
    'template_edit' => 'Edit',
    'template_delete' => 'Delete',
    'template_override' => 'Create an organization copy',
    'template_duplicate' => 'Your organization already has a template for this event, channel and language. Edit it instead of creating a second copy.',
    'template_saved' => 'Template saved.',
    'template_deleted' => 'Template deleted.',
    'template_audit_reason' => 'Message template management from the console.',

    'template_fields' => [
        'event_key' => 'Event key',
        'locale' => 'Language',
        'provider_template_name' => 'Provider template name',
        'is_active' => 'Active',
        'active' => 'Active',
        'inactive' => 'Disabled',
        'detected' => 'Variables detected in the text',
        'no_variables' => 'No variables',
    ],

    'kinds' => [
        'credentials' => 'Account details',
        'schedule' => 'Schedule',
        'free_text' => 'Free text message',
    ],

    'fields' => [
        'kind' => 'Message type',
        'channel' => 'Channel',
        'reason' => 'Reason',
        'scheduled_for' => 'Send at',
        'fields' => 'Included fields',
        'subject' => 'Subject',
        'body' => 'Message',
        'recipient_type' => 'Target type',
        'target' => 'Target',
        'audience' => 'Audience',
        'template' => 'Template',
    ],

    'hints' => [
        'reason' => 'Recorded in the audit log with the message.',
        'scheduled_for' => 'Leave empty to send immediately.',
        'variables' => 'Available variables: :variables',
        'password' => 'Including the password issues a new temporary one and invalidates the current password immediately; the account holder must change it on first sign-in.',
        'individual' => 'Messages are sent individually; no recipient sees another recipient number.',
    ],

    'credentials' => [
        'subject' => 'Your account details',
        'intro' => 'Your sign-in details:',
        'password_notice' => 'This password is temporary and you will be asked to change it on first sign-in.',
        'login_url' => 'Sign-in link: :url',
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'username' => 'Username',
            'password' => 'Password',
        ],
    ],

    'schedule' => [
        'subject' => 'Your schedule',
        'intro' => 'These are your upcoming sessions:',
        'empty' => 'There are no upcoming sessions on your account right now.',
        'timezone_notice' => 'Times shown in: :timezone',
    ],
];
