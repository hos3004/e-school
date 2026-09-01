<?php

declare(strict_types=1);

/*
| General messages of the Notifications module.
| Consumed via __('notifications::messages.key') — no user-facing text outside translation files.
| Notification templates live in templates.php and are rendered in the recipient's locale.
*/

return [

    'opted_out' => 'The recipient opted out of this notification type; nothing was queued.',

    'already_queued' => 'This notification is already queued — the idempotency key prevented a duplicate.',

    'cancelled' => 'The notification was cancelled and will not be sent.',

    'retried' => 'The notification was rescheduled for delivery.',

    'marked_as_read' => 'The notification was marked as read.',

    'marked_all_as_read' => 'All your notifications were marked as read.',

    'marked_all_as_read_count' => ':count notifications were marked as read.',

    'manual_retry_queued' => 'The manual resend was recorded and a new delivery attempt was queued.',

    'preference_updated' => 'Notification preference updated successfully.',

    'seed_provider_error' => 'Delivery provider was unreachable.',

    'dispatch_due_done' => 'Dispatched :count due notifications to sender jobs.',

    'retry_failed_done' => 'Rescheduled :count failed notifications.',
    'not_available' => 'Not available',
    'no_audit_entries' => 'No manual decisions have been recorded for this notification.',
    'system_actor' => 'System',
    'manual_reason_help' => 'State the administrative reason. It is audited and is not shown to recipients.',
    'manual_preview_empty' => 'Enter a subject or message to see the preview.',
    'manual_preview_count' => 'The notification will be queued for :count recipients.',
    'manual_send_failed' => 'The notification could not be queued.',
    'manual_already_processed' => 'This send request was already processed; no duplicate messages were created.',
    'manual_queued' => ':queued notifications were queued for :recipients recipients.',
];
