<?php

declare(strict_types=1);

/*
| Outbox status labels.
*/

return [

    'queued' => 'Queued',
    'sending' => 'Sending',
    'sent' => 'Sent',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
    'suppressed' => 'Suppressed (duplicate)',
];
