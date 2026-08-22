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
];
