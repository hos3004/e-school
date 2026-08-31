<?php

declare(strict_types=1);

/*
| Status labels of the Groups module: group status, membership status, teacher role.
| Consumed via __('groups::status.group.active') and so on.
*/

return [
    'group' => [
        'planning' => 'Planning',
        'active' => 'Active',
        'completed' => 'Completed',
    ],
    'membership' => [
        'pending' => 'Pending',
        'active' => 'Enrolled',
        'left' => 'Left',
    ],
    'teacher_role' => [
        'lead' => 'Lead teacher',
        'assistant' => 'Assistant teacher',
        'substitute' => 'Substitute teacher',
    ],
];
