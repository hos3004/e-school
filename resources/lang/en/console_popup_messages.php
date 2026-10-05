<?php

declare(strict_types=1);

/*
 * Console "Pop Messages" page copy.
 */
return [
    'title' => 'Pop Messages',
    'subtitle' => 'In-app popup campaigns: bottom banner or fullscreen, for a chosen audience and window.',

    'list' => [
        'empty' => 'No campaigns yet. Create the first one from the button above.',
        'columns' => [
            'internal_name' => 'Internal name',
            'type' => 'Type',
            'status' => 'Status',
            'audiences' => 'Audience',
            'display_mode' => 'Display mode',
            'priority' => 'Priority',
            'window' => 'Display window',
            'media' => 'Attachments',
            'actions' => 'Actions',
        ],
        'audiences_more' => '+:count more',
        'no_media' => 'No attachments',
    ],

    'actions' => [
        'create' => 'New campaign',
        'edit' => 'Edit',
        'view' => 'View',
        'publish' => 'Publish',
        'pause' => 'Pause',
        'archive' => 'Archive',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'save_and_publish' => 'Save',
        'add_link' => 'Add link',
        'remove_link' => 'Remove',
        'upload' => 'Upload',
    ],

    'confirm' => [
        'publish' => 'This campaign will start showing to its audience immediately, within its display window. Confirm?',
        'pause' => 'This campaign will stop showing until you publish it again. Confirm?',
        'archive' => 'Archiving is final — this campaign cannot be published again. Confirm?',
    ],

    'reason' => [
        'label' => 'Reason',
        'placeholder' => 'Write the reason for this creation, edit, or status change — stored in the audit log.',
    ],

    'fields' => [
        'internal_name' => 'Internal name (admin only)',
        'internal_name_help' => 'Not shown to users — only used to identify the campaign in this list.',
        'type' => 'Campaign type',
        'display_mode' => 'Display mode',
        'display_mode_bottom_banner' => 'Bottom banner (non-blocking)',
        'display_mode_fullscreen' => 'Fullscreen',
        'title_locale' => 'Title',
        'body_locale' => 'Body',
        'title_ar' => 'Title (Arabic)',
        'title_en' => 'Title (English, optional)',
        'title_fr' => 'Title (French, optional)',
        'body_ar' => 'Body (Arabic)',
        'body_en' => 'Body (English, optional)',
        'body_fr' => 'Body (French, optional)',
        'audiences' => 'Target audience',
        'excluded_audiences' => 'Exclude',
        'excluded_audiences_help' => 'Exclusion always wins: an excluded audience never sees the campaign, even if it also matches the target audience.',
        'is_dismissible' => 'Manually dismissible',
        'requires_acknowledgement' => 'Requires user acknowledgement',
        'acknowledgement_label' => 'Acknowledgement button text',
        'auto_dismiss_seconds' => 'Auto-dismiss after (seconds)',
        'auto_dismiss_help' => 'The campaign disappears on its own after this duration — useful when you don\'t want a close button or acknowledgement.',
        'auto_dismiss_disabled_hint' => 'Not available while acknowledgement is required — acknowledgement alone is a safe exit.',
        'safe_exit_hint' => 'A campaign must have at least one safe exit: manual dismiss, acknowledgement, or auto-dismiss.',
        'priority' => 'Priority',
        'priority_help' => 'Higher priority shows first when more than one campaign is eligible for the same user.',
        'starts_at' => 'Display start',
        'ends_at' => 'Display end (optional)',
        'placement' => 'Placement',
        'page_key' => 'Specific page',
        'frequency' => 'Frequency',
        'action_type' => 'Action button (optional)',
        'action_type_none' => 'No action button',
        'action_type_internal_page' => 'A page in the platform',
        'action_type_external_url' => 'External link',
        'action_target_internal' => 'Page',
        'action_target_external' => 'URL (must start with https://)',
        'action_label' => 'Action button text',
        'reason' => 'Reason',
    ],

    'media' => [
        'title' => 'Attachments',
        'hint' => 'Image, video, audio, or a downloadable file — each kind has its own size limit and allowed file types.',
        'kind' => 'File kind',
        'kind_image' => 'Image',
        'kind_video' => 'Video',
        'kind_audio' => 'Audio',
        'kind_file' => 'Downloadable file',
        'has_sound' => 'Has sound',
        'uploading' => 'Uploading…',
        'uploaded' => 'Uploaded.',
        'upload_failed' => 'Upload failed — check the allowed kind and size.',
        'save_before_upload' => 'Save the campaign as a draft first before uploading attachments.',
        'empty' => 'No attachments yet.',
    ],

    'links' => [
        'title' => 'Clickable words in the text',
        'hint' => 'Make a word or phrase from the text clickable: enter it exactly as it appears above, then choose its target — an external link or a file uploaded to this campaign.',
        'text' => 'Word or phrase',
        'text_placeholder' => 'Must appear exactly as written in the text above',
        'target_type' => 'Target',
        'target_external' => 'External link (https://)',
        'target_media' => 'File uploaded to this campaign',
        'target_media_empty' => 'No downloadable file has been uploaded yet — upload one from the attachments section first.',
        'external_url' => 'URL',
        'empty' => 'No links in the text.',
    ],

    'status_hint' => [
        'locked_while_published' => 'This campaign is currently published — pause it first to edit its content.',
        'cannot_transition' => 'No status change is available from here.',
    ],

    'messages' => [
        'created' => 'Campaign created as a draft.',
        'updated' => 'Campaign changes saved.',
        'status_changed' => 'Campaign status changed.',
    ],
];
