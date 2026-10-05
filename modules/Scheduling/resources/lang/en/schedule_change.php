<?php

declare(strict_types=1);

/*
| Permanent schedule change request texts — statuses, student responses, slots.
| Consumed through __('scheduling::schedule_change.key').
*/

return [
    'pending' => 'Awaiting approval from every student',
    'applied' => 'New weekly time applied',
    'rejected' => 'Declined by a student',
    'withdrawn' => 'Withdrawn by the teacher',
    'expired' => 'Student response window elapsed',

    'approval_pending' => 'Awaiting your response',
    'approval_accepted' => 'Accepted the new time',
    'approval_rejected' => 'Declined the new time',

    'slot' => ':day at :time',
    'slot_separator' => ', ',
];
