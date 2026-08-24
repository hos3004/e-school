<?php

declare(strict_types=1);

/*
| Strings for the notification categories & channels admin screen.
*/

return [

    'routing' => 'Category routing',
    'routing_hint' => 'Choose which channels this category sends on, whether it is critical, and whether it respects quiet hours. Applies immediately to new notifications.',
    'channels_hint' => 'Channels this category sends on. In-app stays always on. A channel disabled system-wide will not send even if selected here.',

    'is_critical' => 'Critical',
    'is_critical_hint' => 'A critical category overrides user preferences and quiet hours — nobody can turn it off and it sends immediately.',

    'respects_quiet_hours' => 'Respects quiet hours',
    'respects_quiet_hours_hint' => 'When enabled, non-critical notifications in this category are deferred past quiet hours in the recipient timezone.',
];
