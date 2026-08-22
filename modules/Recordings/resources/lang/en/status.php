<?php

declare(strict_types=1);

/*
| Recording lifecycle statuses of the Recordings module.
| Consumed via __('recordings::status.key').
*/

return [
    'processing' => 'Processing',
    'ready' => 'Ready',
    'archived' => 'Archived',
    'failed' => 'Failed',
    'expired' => 'Expired',
];
