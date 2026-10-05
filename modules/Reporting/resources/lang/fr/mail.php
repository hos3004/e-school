<?php

declare(strict_types=1);

return [
    'monthly_program_digest' => [
        'subject' => 'Rapport mensuel du programme :program (:from au :to)',
        'period' => 'Période : du :from au :to',
        'empty' => 'Aucun rapport de séance soumis pour ce programme durant cette période.',
        'columns' => [
            'date' => 'Date du rapport',
            'topics' => 'Sujets',
            'homework' => 'Devoirs',
            'participation' => 'Participation',
            'performance' => 'Performance',
            'commitment' => 'Engagement',
            'note' => 'Remarque',
        ],
    ],
];
