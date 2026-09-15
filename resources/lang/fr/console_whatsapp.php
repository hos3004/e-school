<?php

declare(strict_types=1);

return [
    'title' => 'Centre WhatsApp',
    'subtitle' => 'État du canal, son interrupteur d’arrêt, ses modèles, son journal et une simulation d’envoi.',

    'tabs' => [
        'status' => 'État et contrôle',
        'templates' => 'Modèles',
        'compose' => 'Envoyer et simuler',
        'log' => 'Journal',
        'settings' => 'Paramètres de connexion',
    ],

    'status' => [
        'channel_on' => 'Canal actif',
        'channel_off' => 'Canal arrêté',
        'channel_on_hint' => 'Les messages automatiques et manuels partent par WhatsApp en ce moment.',
        'channel_off_hint' => 'Aucun message WhatsApp ne part. L’e-mail et les notifications internes fonctionnent normalement.',
        'instance' => 'Identifiant d’instance',
        'api_url' => 'URL du fournisseur',
        'provider_state' => 'État du fournisseur',
        'authorized' => 'Téléphone lié',
        'not_authorized' => 'Téléphone non lié — ouvrez la console Green API et scannez le code QR.',
        'webhook_on' => 'Réception des réponses activée',
        'webhook_off' => 'Réception des réponses non enregistrée',
        'token_set' => 'Jeton enregistré',
        'token_missing' => 'Aucun jeton enregistré',
    ],

    'stats' => [
        'title' => 'Dernières 24 heures',
        'sent' => 'Envoyés',
        'queued' => 'En attente',
        'sending' => 'En cours',
        'failed' => 'Échoués',
        'cancelled' => 'Annulés',
        'suppressed' => 'Supprimés comme doublons',
    ],

    'toggle' => [
        'pause' => 'Arrêter WhatsApp immédiatement',
        'resume' => 'Démarrer WhatsApp',
        'reason' => 'Motif',
        'reason_placeholder' => 'Pourquoi vous l’arrêtez ou le démarrez — conservé dans le journal d’audit.',
        'confirm_pause' => 'L’envoi WhatsApp s’arrête immédiatement et les messages en attente sont annulés. Confirmer ?',
        'paused' => 'WhatsApp arrêté. :count messages en attente ont été annulés.',
        'resumed' => 'WhatsApp démarré.',
        'blocked' => 'L’état actuel de la connexion ne permet pas ce basculement — vérifiez les paramètres de connexion.',
    ],

    'automatic' => [
        'badge' => 'Automatique',
        'manual_badge' => 'Manuel',
        'notice_title' => 'Marque des messages automatiques',
        'notice_hint' => 'Chaque message généré par le système arrive avec cette ligne, afin que le destinataire sache qu’il ne s’agit pas d’une personne qui suit sa conversation. Les messages écrits par un membre du personnel ne portent pas de marque.',
    ],

    'preview' => [
        'title' => 'Simuler avant l’envoi',
        'hint' => 'Voyez le texte final et la liste réelle des destinataires avant tout envoi.',
        'run' => 'Lancer la simulation',
        'recipients' => 'Destinataires',
        'reachable' => 'Recevront',
        'unreachable' => 'Ne recevront pas',
        'no_phone' => 'Le compte n’a pas de numéro de téléphone',
        'message_preview' => 'Texte du message tel qu’il arrivera',
        'channel_off_warning' => 'Le canal est arrêté — la simulation fonctionne, mais rien ne partira tant que vous ne l’aurez pas démarré.',
        'empty' => 'La simulation n’a pas encore été lancée.',
        'failed' => 'La simulation n’a pas pu s’exécuter. Réessayez.',
        'stale' => 'Vous avez modifié les données après la simulation — relancez-la avant d’envoyer.',
        'count_summary' => 'Atteint :reachable sur :total.',
    ],

    'templates' => [
        'title' => 'Modèles de messages WhatsApp',
        'hint' => 'Ce sont les textes que le système envoie automatiquement. Reformulez-les comme vous le souhaitez — un modèle global est une référence partagée qui se lit mais ne se modifie pas, et votre modification crée une copie propre à votre organisation.',
        'event' => 'Événement',
        'locale' => 'Langue',
        'body' => 'Texte',
        'global' => 'Modèle global',
        'empty' => 'Aucun modèle WhatsApp.',
        'open_editor' => 'Ouvrir l’éditeur de modèles',
    ],

    'log' => [
        'title' => 'Journal des messages WhatsApp',
        'hint' => 'Ce qui est récemment passé par le canal, et si chaque message était automatique ou écrit par un membre du personnel.',
        'event' => 'Événement',
        'status' => 'État',
        'created' => 'Créé',
        'sent' => 'Envoyé',
        'reason' => 'Motif de l’échec',
        'empty' => 'Aucun message pour le moment.',
    ],

    'settings' => [
        'title' => 'Connexion Green API',
        'hint' => 'Changez le jeton ou l’identifiant d’instance quand vous voulez. Le jeton est vérifié auprès du fournisseur avant enregistrement et n’est plus jamais affiché ensuite.',
        'open' => 'Ouvrir les paramètres de connexion',
        'token_hint' => 'Laissez vide pour conserver le jeton actuel.',
    ],

    'other_entries' => [
        'title' => 'Autres points d’entrée WhatsApp',
        'hint' => 'Cette section rassemble et gouverne ; elle ne supprime rien. Le bouton de messagerie sur un profil d’élève ou d’enseignant et la page des messages continuent de fonctionner, passent par le même moteur et apparaissent dans ce journal.',
        'messages_link' => 'Centre de messages (envoi groupé, planifié et modèles de tous les canaux)',
    ],
];
