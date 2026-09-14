<?php

declare(strict_types=1);

return [
    'green_api' => [
        'title' => 'Connexion WhatsApp via Green API',
        'description' => 'Saisissez l’URL API, l’identifiant et le jeton. L’activation vérifie la connexion avant l’enregistrement.',
        'api_url' => 'URL API',
        'instance_id' => 'Identifiant de l’instance',
        'token' => 'Jeton de l’instance',
        'token_keep' => 'Laissez vide pour conserver le jeton enregistré.',
        'token_new' => 'Collez apiTokenInstance depuis Green API.',
        'enabled' => 'Activer après vérification',
        'reason' => 'Motif de modification',
        'status' => 'État de connexion',
        'authorized' => 'Activé',
        'state_attention' => 'Instance à vérifier',
        'disabled' => 'Désactivé',
        'webhook_ready' => 'Réponses entrantes configurées',
        'webhook_pending' => 'Réponses entrantes non configurées',
        'token_required' => 'Saisissez un jeton avant l’activation.',
        'connection_failed' => 'Instance non vérifiée ou non autorisée.',
        'save' => 'Tester et enregistrer',
        'register_webhook' => 'Activer les réponses et statuts entrants',
        'webhook_failed' => 'Impossible de configurer les webhooks Green API.',
        'webhook_saved' => 'Webhooks configurés. Le redémarrage peut prendre quelques minutes.',
        'reason_required' => 'Saisissez un motif.',
        'webhook_notice' => 'L’activation redémarre l’instance Green API pendant quelques minutes.',
        'saved' => 'Connexion WhatsApp enregistrée après vérification.',
    ],
];
