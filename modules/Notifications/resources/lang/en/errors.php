<?php

declare(strict_types=1);

/*
| Business-rule error messages of the Notifications module.
| Consumed via __('notifications::errors.key') — keys describe meaning, not wording.
*/

return [

    'channel_disabled' => 'The ":channel" channel is not enabled in the platform settings.',

    'not_cancellable' => 'A notification in status ":status" cannot be cancelled — cancellation is only available before sending.',

    'not_retryable' => 'A notification in status ":status" cannot be retried — retry is only available for failed notifications.',

    'not_dispatchable' => 'Sending cannot start for a notification in status ":status" — dispatch starts from the queued status only.',

    'already_claimed' => 'The notification is already claimed by another sender.',

    'attempt_not_recordable' => 'A delivery attempt cannot be recorded for a notification in status ":status".',

    'invalid_status_transition' => 'Status transition from ":from" to ":to" is not allowed.',

    'category_unknown' => 'The notification category ":category" is unknown in the platform settings.',

    'event_id_required' => 'A source event ID is required to queue a notification safely.',

    'gateway_unconfigured' => 'The gateway for the ":channel" channel is not configured — no implementation is bound for this channel.',

    'gateway_channel_mismatch' => 'This gateway cannot deliver ":actual" messages; it expects the ":expected" channel.',
];
