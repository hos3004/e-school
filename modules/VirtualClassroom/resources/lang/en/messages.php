<?php

declare(strict_types=1);

/*
| General messages of the VirtualClassroom module.
| Consumed via __('virtualclassroom::messages.key') — no user-facing text outside translation files.
*/

return [
    'smoke_config' => 'Provider: :provider | URL: :base_url | Secret fingerprint: :fingerprint',
    'smoke_reason' => 'Failure reason: :reason',
    'smoke_health_ok' => 'The classroom-provider health check passed.',
    'smoke_health_failed' => 'The classroom-provider health check failed: :reason',
    'smoke_created' => 'Test classroom :meeting was created.',
    'smoke_ended' => 'Test classroom :meeting was ended.',
    'smoke_running' => 'The classroom is running.',
    'smoke_not_running' => 'The classroom is not running.',
    'smoke_participants' => 'Current participants: :count.',
    'smoke_recordings' => 'Available recordings: :count.',
    'smoke_join_moderator' => 'Moderator join URL:',
    'smoke_join_viewer' => 'Viewer join URL:',
    'smoke_default_title' => 'Platform smoke-test classroom',
    'smoke_default_name' => 'Smoke-test user',
    'smoke_meeting_required' => 'The --meeting option is required for this action.',
    'smoke_unknown_action' => 'Unknown smoke-test action: :action.',
    'webhook_unsupported' => 'Provider :provider does not support programmatic webhook registration.',
    'webhook_count' => 'Webhook subscriptions: :count.',
    'webhook_row' => ':hook | :callback | Meeting scope: :meeting',
    'webhook_scope_global' => 'all meetings',
    'webhook_registered' => 'Webhook :hook was registered at :callback.',
    'webhook_removed' => 'Webhook subscription :hook was removed.',
    'webhook_hook_required' => 'The --hook option is required to remove a subscription.',
    'default_participant_name' => 'Participant',
    'default_classroom_title' => 'Live session',
    'recordings_synced' => ':count ready recordings synchronized.',
    'provision_reason' => 'Provision the live classroom before the session starts.',
    'portal_provision_reason' => 'The portal verified classroom readiness when join was requested.',
    'webhook_started_reason' => 'The provider reported that the classroom started.',
    'webhook_ended_reason' => 'The provider reported that the classroom ended.',
    'scheduled_provision_reason' => 'Automatically provision the classroom before the session starts.',
    'provisioning_summary' => 'Classrooms ready: :provisioned; failed: :failed.',
];
