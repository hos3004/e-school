<?php

declare(strict_types=1);

return [
    'title' => 'WhatsApp centre',
    'subtitle' => 'Channel state, its kill switch, templates, log, and a send simulation.',

    'tabs' => [
        'status' => 'Status and control',
        'templates' => 'Templates',
        'compose' => 'Send and simulate',
        'campaigns' => 'Number campaigns',
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

    'campaigns' => [
        'title' => 'Number campaigns',
        'hint' => 'Send to a list of numbers that hold no account here — a course list, or people who asked about one. Messages leave one after another with a gap between them, not all at once.',
        'new' => 'New campaign',
        'created' => 'Campaign saved as a draft with :count usable numbers. Review it, then start sending.',
        'started' => 'Sending started. Messages leave one by one with the gap you set.',
        'stopped' => 'Campaign stopped. :count recipients were cancelled before anything went out to them.',
        'confirm_start' => 'This starts real sending to the numbers on the list. Confirm?',
        'confirm_stop' => 'Anything not yet sent will stop. Messages already sent cannot be recalled. Confirm?',
        'start' => 'Start sending',
        'stop' => 'Stop campaign',
        'refresh' => 'Refresh status',

        'fields' => [
            'name' => 'Campaign name',
            'name_hint' => 'For your own records — recipients never see it.',
            'body' => 'Message text',
            'reason' => 'Reason for sending',
            'reason_hint' => 'Kept on the campaign record.',
            'recipients_text' => 'Typed numbers',
            'recipients_text_hint' => 'One number per line; a name may come first, separated by a comma: Ahmed, +201012345678',
            'recipients_file' => 'Numbers file',
            'recipients_file_hint' => 'Excel or CSV with two columns: name and number. A header row is skipped automatically.',
            'delay_min' => 'Shortest gap (seconds)',
            'delay_max' => 'Longest gap (seconds)',
            'delay_hint' => 'A random gap between the two is taken before each message — a perfectly steady rhythm is the clearest mark of a machine.',
            'media' => 'Attachments',
            'media_hint' => 'Images, video, or files that arrive before the message text. Deleted from the server :days days after the campaign ends.',
        ],

        'placeholders' => [
            'title' => 'The recipient name inside the text',
            'hint' => 'Write :tokens in the message and it is replaced by each recipient name from the list. Anyone without a name gets the message without the token.',
        ],

        'preview' => [
            'run' => 'Check the list',
            'accepted' => 'Usable numbers',
            'rejected' => 'Numbers needing review',
            'duplicates' => 'Duplicates removed',
            'sample' => 'The message as the first recipient will read it',
            'empty' => 'The list has not been checked yet.',
            'failed' => 'The list could not be checked. Try again.',
        ],

        'status' => [
            'draft' => 'Draft',
            'running' => 'Running',
            'completed' => 'Finished',
            'stopped' => 'Stopped',
        ],

        'counts' => [
            'pending' => 'Waiting',
            'sent' => 'Delivered to provider',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'invalid' => 'Rejected numbers',
            'progress' => ':sent of :total',
        ],

        'problems' => [
            'title' => 'Numbers that received nothing',
            'hint' => 'Fix the number on your side and add it to a new campaign — a saved campaign list cannot be edited.',
            'name' => 'Name',
            'input' => 'As typed',
            'reason' => 'Reason',
            'empty' => 'Every number is sound.',
        ],

        'reasons' => [
            'phone_empty' => 'No number given',
            'phone_invalid_characters' => 'The number holds letters or symbols that cannot be read',
            'phone_missing_country_code' => 'Local number with no country code — write it in international form starting with + or 00',
            'phone_invalid_format' => 'The length or shape of the number is not valid',
            'channel_disabled' => 'The WhatsApp channel was switched off before their turn came',
            'interrupted' => 'Sending broke off before the result was known — check before sending again',
            'whatsapp_media_missing' => 'The attachment is no longer on the server',
        ],

        'errors' => [
            'recipients_required' => 'Type some numbers or upload a file.',
        ],

        'empty' => 'No campaigns yet.',
        'channel_off_warning' => 'The WhatsApp channel is off — you can prepare a campaign, but sending will not start until you switch it on.',
        'media_expires' => 'Attachments are deleted on :date',
    ],

    'other_entries' => [
        'title' => 'Other WhatsApp entry points',
        'hint' => 'This section gathers and governs; it removes nothing. The messaging button on a student or teacher profile and the messages page keep working, go through the same engine, and show up in this log.',
        'messages_link' => 'Messages centre (bulk, scheduled, and templates for every channel)',
    ],
];
