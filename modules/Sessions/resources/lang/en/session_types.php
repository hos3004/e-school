<?php

declare(strict_types=1);

/*
| Session types as declared in config/academic.php -> session_types.
| Consumed via __('sessions::session_types.key').
*/

return [
    'individual' => 'Individual',
    'group' => 'Group',
    'webinar' => 'Webinar',
    'makeup' => 'Make-up session',
    'assessment' => 'Assessment session',
];
