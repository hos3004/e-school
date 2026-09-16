<?php

declare(strict_types=1);

return [
    'digest_recipient' => [
        'invalid_type' => 'Type de destinataire invalide.',
        'user_required' => 'Un utilisateur doit être sélectionné pour « utilisateur existant ».',
        'user_email_invalid' => "L'e-mail de l'utilisateur sélectionné n'est pas valide (absent ou fictif).",
        'custom_email_invalid' => "L'adresse e-mail personnalisée n'est pas valide.",
        'concurrent_change' => 'Ce paramètre a été modifié ailleurs ; rechargez la page et réessayez.',
        'fields' => [
            'type' => 'Type de destinataire',
            'user' => 'Utilisateur',
            'email' => 'E-mail personnalisé',
            'reason' => 'Motif du changement',
        ],
    ],
];
