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

    'preference_updated' => 'Notification preference updated successfully.',

    'seed_provider_error' => 'Delivery provider was unreachable.',

    'dispatch_due_done' => 'Dispatched :count due notifications to sender jobs.',

    'retry_failed_done' => 'Rescheduled :count failed notifications.',
];
