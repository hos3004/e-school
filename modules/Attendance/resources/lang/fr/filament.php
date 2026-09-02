<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Journée scolaire',
    'attendance' => ['label' => 'Présence', 'plural' => 'Présences'],
    'pages' => [
        'list_title' => 'Registre de présence',
        'view_title' => 'Détail de la présence',
    ],
    'actions' => [
        'confirm' => 'Confirmer',
        'confirm_description' => 'Le statut calculé sera validé et utilisé dans les rapports. Continuer ?',
        'override' => 'Modifier le statut',
        'reason_helper' => 'Le motif, votre identité et l’heure seront consignés dans le journal d’audit.',
    ],
    'messages' => [
        'confirmed' => 'Présence confirmée et consignée dans le journal d’audit.',
        'overridden' => 'Statut de présence modifié avec le motif indiqué.',
    ],
    'hub' => [
        'title' => 'Centre de présence',
        'attendance_summary' => 'Résumé de la présence',
        'participant' => 'Élève et séance',
        'audit' => 'Journal d’audit',
        'empty' => 'Aucun enregistrement.',
    ],
];
