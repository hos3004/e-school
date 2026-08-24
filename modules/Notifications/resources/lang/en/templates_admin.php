<?php

declare(strict_types=1);

/*
| Strings for the notification templates admin screen.
*/

return [

    'routing_hint' => 'A template is keyed by event × channel × locale. Each organization may hold at most one template per key.',
    'subject_hint' => 'Message subject (used for email and in-app). WhatsApp does not use the subject.',
    'body_hint' => 'Message body. Use {{parameter_name}} to inject an event value; every one must be listed in the Parameters field.',
    'parameters_hint' => 'Names of the placeholders used in the body, in the order they appear. Each must exist in the event payload or delivery is rejected.',
    'provider_template_hint' => 'WhatsApp only: the approved Meta template name (lowercase letters, digits, underscores). Leave empty for other channels.',

    'scope_global' => 'Global default',
    'scope_organization' => 'Organization',
    'scope_all' => 'All',

    'clone_action' => 'Create organization copy',
    'clone_heading' => 'Customize this template for your organization',
    'clone_description' => 'Creates an organization-specific copy with the same content that overrides the global default at send time and can be edited freely.',
    'clone_done' => 'The organization template copy was created.',
    'clone_conflict' => 'An organization copy already exists for the same event, channel and locale.',

    'duplicate' => 'A template already exists for this organization with the same event, channel and locale. Edit the existing one instead of creating a duplicate.',

    'locale_ar' => 'Arabic',
    'locale_en' => 'English',
    'locale_fr' => 'French',
];
