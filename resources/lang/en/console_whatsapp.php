<?php

declare(strict_types=1);

return [
    'title' => 'WhatsApp centre',
    'subtitle' => 'Channel state, its kill switch, templates, log, and a send simulation.',

    'tabs' => [
        'status' => 'Status and control',
        'templates' => 'Templates',
        'compose' => 'Send and simulate',
        'log' => 'Log',
        'settings' => 'Connection settings',
    ],

    'status' => [
        'channel_on' => 'Channel is on',
        'channel_off' => 'Channel is off',
        'channel_on_hint' => 'Automatic and manual messages go out over WhatsApp right now.',
        'channel_off_hint' => 'No WhatsApp message goes out. Email and in-app keep working as usual.',
        'instance' => 'Instance id',
        'api_url' => 'Provider URL',
        'provider_state' => 'Provider state',
        'authorized' => 'Phone is linked',
        'not_authorized' => 'Phone is not linked — open the Green API console and scan the QR code.',
        'webhook_on' => 'Inbound replies enabled',
        'webhook_off' => 'Inbound replies not registered',
        'token_set' => 'Token stored',
        'token_missing' => 'No token stored',
    ],

    'stats' => [
        'title' => 'Last 24 hours',
        'sent' => 'Sent',
        'queued' => 'Queued',
        'sending' => 'Sending',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'suppressed' => 'Suppressed as duplicate',
    ],

    'toggle' => [
        'pause' => 'Stop WhatsApp now',
        'resume' => 'Start WhatsApp',
        'reason' => 'Reason',
        'reason_placeholder' => 'Why you are stopping or starting it — stored in the audit log.',
        'confirm_pause' => 'WhatsApp sending stops immediately and queued messages are cancelled. Confirm?',
        'paused' => 'WhatsApp stopped. :count queued messages were cancelled.',
        'resumed' => 'WhatsApp started.',
        'blocked' => 'The current connection state does not allow this switch — check the connection settings.',
    ],

    'automatic' => [
        'badge' => 'Automatic',
        'manual_badge' => 'Manual',
        'notice_title' => 'Automatic message tag',
        'notice_hint' => 'Every system-generated message arrives with this line, so the recipient knows it is not a person following their chat. Messages a staff member writes carry no tag.',
    ],

    'preview' => [
        'title' => 'Simulate before sending',
        'hint' => 'See the final text and the real recipient list before anything is sent.',
        'run' => 'Run simulation',
        'recipients' => 'Recipients',
        'reachable' => 'Will receive',
        'unreachable' => 'Will not receive',
        'no_phone' => 'The account has no phone number',
        'message_preview' => 'Message text as it will arrive',
        'channel_off_warning' => 'The channel is off — the simulation runs, but nothing will be sent until you start it.',
        'empty' => 'The simulation has not been run yet.',
        'failed' => 'The simulation could not run. Try again.',
        'stale' => 'You changed the inputs after simulating — run it again before sending.',
        'count_summary' => 'Reaches :reachable of :total.',
    ],

    'templates' => [
        'title' => 'WhatsApp message templates',
        'hint' => 'These are the texts the system sends automatically. Reword them as you like — a global template is a shared reference that is read but not edited, and your edit creates a copy owned by your organization.',
        'event' => 'Event',
        'locale' => 'Language',
        'body' => 'Text',
        'global' => 'Global template',
        'empty' => 'No WhatsApp templates.',
        'open_editor' => 'Open the template editor',
    ],

    'log' => [
        'title' => 'WhatsApp message log',
        'hint' => 'What recently went through the channel, and whether each message was automatic or written by a staff member.',
        'event' => 'Event',
        'status' => 'Status',
        'created' => 'Created',
        'sent' => 'Sent',
        'reason' => 'Failure reason',
        'empty' => 'No messages yet.',
    ],

    'settings' => [
        'title' => 'Green API connection',
        'hint' => 'Change the token or instance id whenever you want. The token is verified with the provider before saving and is never shown again afterwards.',
        'open' => 'Open connection settings',
        'token_hint' => 'Leave blank to keep the current token.',
    ],

    'other_entries' => [
        'title' => 'Other WhatsApp entry points',
        'hint' => 'This section gathers and governs; it removes nothing. The messaging button on a student or teacher profile and the messages page keep working, go through the same engine, and show up in this log.',
        'messages_link' => 'Messages centre (bulk, scheduled, and templates for every channel)',
    ],
];
