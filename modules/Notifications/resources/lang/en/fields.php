<?php

declare(strict_types=1);

/*
| Field labels of the Notifications module — consumed in forms, tables and requests.
*/

return [

    'id' => 'ID',
    'organization_id' => 'Organization',
    'user_id' => 'User',
    'category' => 'Category',
    'channel' => 'Channel',
    'locale' => 'Message locale',
    'event_name' => 'Event name',
    'event_id' => 'Event ID',
    'correlation_id' => 'Correlation ID',
    'subject' => 'Subject',
    'body' => 'Body',
    'payload' => 'Payload',
    'idempotency_key' => 'Idempotency key',
    'scheduled_for' => 'Scheduled for',
    'status' => 'Status',
    'attempts' => 'Attempts',
    'last_error' => 'Last error',
    'sent_at' => 'Sent at',
    'external_message_id' => 'Provider message ID',
    'provider_status' => 'Provider status',
    'failure_reason' => 'Failure reason',
    'read_at' => 'Read at',
    'read_status' => 'Read status',
    'last_manual_retry_by' => 'Last resent by',
    'last_manual_retry_at' => 'Last manual resend at',
    'created_at' => 'Created at',
    'updated_at' => 'Updated at',
    'enabled' => 'Enabled',
    'reason' => 'Reason',
    'attempt_number' => 'Attempt number',
    'attempted_at' => 'Attempted at',
    'provider_response' => 'Provider response',
    'error' => 'Error',
    'succeeded' => 'Succeeded',

    // Outbox form sections
    'routing' => 'Routing',
    'content' => 'Content',
    'dispatching' => 'Delivery',

    // Template fields
    'is_active' => 'Active',
    'parameters' => 'Parameters',
    'provider_template_name' => 'Provider template name',
    'scope' => 'Scope',
    'recipient' => 'Recipient',
    'retry_reason' => 'Resend reason',
    'cancel_reason' => 'Cancellation reason',
    'attempts_history' => 'Delivery attempts history',
    'result' => 'Result',
    'audit_history' => 'Decision and audit history',
    'action' => 'Action',
    'actor' => 'Performed by',
];
