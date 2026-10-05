<?php

declare(strict_types=1);

return [
    'routing' => 'Routage de la catégorie',
    'routing_hint' => 'Choisissez les canaux, le caractère critique et le respect des heures calmes. Les changements s’appliquent aux nouvelles notifications.',
    'channels_hint' => 'Canaux utilisés par cette catégorie. Le canal interne reste actif. Un canal désactivé au niveau système ne sera pas utilisé.',
    'is_critical' => 'Critique',
    'is_critical_hint' => 'Une catégorie critique ignore les préférences et les heures calmes afin d’être envoyée immédiatement.',
    'respects_quiet_hours' => 'Respecte les heures calmes',
    'respects_quiet_hours_hint' => 'Les notifications non critiques sont reportées après les heures calmes du destinataire.',
];
