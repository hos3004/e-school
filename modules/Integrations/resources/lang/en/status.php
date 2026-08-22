<?php

declare(strict_types=1);

/*
| Status labels of the Integrations module.
*/

return [

    'connection' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'error' => 'Error',
        'disabled' => 'Disabled',
        'expired' => 'Expired',
    ],

    'direction' => [
        'outbound' => 'Outbound',
        'inbound' => 'Inbound',
    ],

    'delivery' => [
        'pending' => 'Queued',
        'retrying' => 'Retrying',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
        'dead' => 'Dead',
    ],

];
