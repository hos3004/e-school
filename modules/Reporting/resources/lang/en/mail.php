<?php

declare(strict_types=1);

return [
    'monthly_program_digest' => [
        'subject' => 'Monthly report for :program (:from to :to)',
        'period' => 'Period: :from to :to',
        'empty' => 'No submitted session reports for this program during this period.',
        'columns' => [
            'date' => 'Report date',
            'topics' => 'Topics',
            'homework' => 'Homework',
            'participation' => 'Participation',
            'performance' => 'Performance',
            'commitment' => 'Commitment',
            'note' => 'Note',
        ],
    ],
];
