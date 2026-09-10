<?php

declare(strict_types=1);

/*
| Textes de la demande de changement d'horaire permanent.
| Consommés via __('scheduling::schedule_change.key').
*/

return [
    'pending' => 'En attente de l’accord de tous les élèves',
    'applied' => 'Nouvel horaire appliqué',
    'rejected' => 'Refusé par un élève',
    'withdrawn' => 'Retiré par l’enseignant',
    'expired' => 'Délai de réponse des élèves expiré',

    'approval_pending' => 'En attente de votre réponse',
    'approval_accepted' => 'A accepté le nouvel horaire',
    'approval_rejected' => 'A refusé le nouvel horaire',

    'slot' => ':day à :time',
    'slot_separator' => ', ',
];
