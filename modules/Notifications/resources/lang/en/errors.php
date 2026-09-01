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

    'failure_not_retryable' => 'This is a permanent failure and must not be retried automatically; an administrator may resend it after fixing the cause.',

    'manual_retry_actor_required' => 'The administrator requesting the manual resend must be identified.',

    'not_readable' => 'This record cannot be marked as read; it must be a delivered in-app notification owned by the current user.',

    'not_dispatchable' => 'Sending cannot start for a notification in status ":status" — dispatch starts from the queued status only.',

    'already_claimed' => 'The notification is already claimed by another sender.',

    'attempt_not_recordable' => 'A delivery attempt cannot be recorded for a notification in status ":status".',

    'invalid_status_transition' => 'Status transition from ":from" to ":to" is not allowed.',

    'category_unknown' => 'The notification category ":category" is unknown in the platform settings.',

    'event_id_required' => 'A source event ID is required to queue a notification safely.',

    'gateway_unconfigured' => 'The gateway for the ":channel" channel is not configured — no implementation is bound for this channel.',

    'gateway_channel_mismatch' => 'This gateway cannot deliver ":actual" messages; it expects the ":expected" channel.',

    'template_missing' => 'No active template exists for event ":event" on channel ":channel" in locale ":locale".',

    'template_parameter_missing' => 'Template parameter ":parameter" is missing from the payload for event ":event".',

    'email_recipient_invalid' => 'The recipient does not have a valid email address for delivery.',

    'mail_transport_failed' => 'The email transport is temporarily unavailable.',
    'manual_retry_reason_required' => 'A clear reason is required for a manual resend.',
    'cancel_reason_required' => 'A clear reason is required to cancel the notification.',
    'manual_recipient_not_found' => 'The selected recipient is missing or inactive in your organization.',
    'manual_empty_audience' => 'The selected group has no active recipients in the organization.',
    'manual_fields_required' => 'Subject, message, and send reason are required.',
    'manual_request_invalid' => 'The send request ID is invalid. Reopen the form and try again.',
];
