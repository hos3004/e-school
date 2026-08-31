<?php

declare(strict_types=1);

return [
    'metric_total' => 'Total des classes', 'metric_pending' => 'En attente', 'metric_provisioned' => 'Prêtes',
    'metric_running' => 'En cours', 'metric_ended' => 'Terminées', 'metric_failed' => 'Échecs',
    'navigation_group' => 'Système', 'navigation_label' => 'Connexion des classes virtuelles',
    'title' => 'Paramètres BigBlueButton', 'connection_heading' => 'État de la connexion',
    'connection_description' => 'Cette page affiche l’état sans exposer ni enregistrer le secret.',
    'provider' => 'Fournisseur actif', 'base_url' => 'URL du serveur BBB',
    'api_secret' => 'Secret API BBB', 'webhook_secret' => 'Secret de vérification webhook',
    'configured' => 'Configuré', 'not_configured' => 'Non configuré', 'run_check' => 'Tester la connexion',
    'check_success' => 'Connexion BBB vérifiée', 'check_failed' => 'Échec du contrôle BBB',
    'health_healthy' => 'Opérationnel', 'health_degraded' => 'À surveiller',
    'health_down' => 'Indisponible', 'health_unknown' => 'Inconnu',
    'webhook_heading' => 'Webhooks de classe et d’enregistrement',
    'webhook_description' => 'Cette URL doit être publique, en HTTPS et sans redirection.',
    'callback_missing' => 'BBB_WEBHOOK_CALLBACK_URL n’est pas configurée.',
    'webhook_supported' => 'Le contrôle vérifiera l’inscription du webhook auprès de BBB.',
    'webhook_unsupported' => 'Le fournisseur ne prend pas en charge l’inscription automatique.',
    'webhook_registered' => 'Un webhook correspondant est inscrit.',
    'webhook_not_registered' => 'Aucun webhook ne correspond à cette URL.',
    'preparation_heading' => 'Préparation du test réel',
    'preparation_description' => 'Exigences externes pour tester enseignant, élève et enregistrement.',
    'preparation_url' => 'Un serveur BigBlueButton accessible en HTTPS avec une API valide.',
    'preparation_secret' => 'Définissez BBB_BASE_URL et BBB_SECRET uniquement dans l’environnement du serveur.',
    'preparation_webhook' => 'Installez bbb-webhooks et inscrivez l’URL de rappel affichée.',
    'preparation_recording' => 'Activez l’enregistrement et terminez une courte séance de test.',
    'env_hint' => 'Ne placez jamais le secret dans une capture ou un message. Lancez ensuite le contrôle et inscrivez le webhook.',
];
