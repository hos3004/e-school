<?php

declare(strict_types=1);

/*
| Module enumerations: conversation types and participant roles.
*/

return [
    'conversation_type' => [
        'direct' => 'Direct',
        'group' => 'Group',
        'class' => 'Class',
    ],
    'participant_role' => [
        'owner' => 'Owner',
        'moderator' => 'Moderator',
        'member' => 'Member',
    ],
];
