<?php

declare(strict_types=1);

/*
| Postponement request statuses as in Modules\Scheduling\Domain\Enums\PostponementStatus.
| Consumed via PostponementStatus::label() i.e. __('scheduling::postponement.{value}').
*/

return [
    'requested' => 'Awaiting teacher response',
    'alternative_proposed' => 'Awaiting student approval of the proposed time',
    'scheduled' => 'New time agreed',
    'fulfilled' => 'Makeup session held',
    'rejected' => 'Rejected',
    'withdrawn' => 'Withdrawn by student',
    'expired' => 'Response window expired — needs admin decision',
];
