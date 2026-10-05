<?php

declare(strict_types=1);

return [
    'title' => 'Centre WhatsApp',
    'subtitle' => 'État du canal, son interrupteur d’arrêt, ses modèles, son journal et une simulation d’envoi.',

    'tabs' => [
        'status' => 'État et contrôle',
        'templates' => 'Modèles',
        'compose' => 'Envoyer et simuler',
        'campaigns' => 'Campagnes de numéros',
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

    'campaigns' => [
        'title' => 'Campagnes de numéros',
        'hint' => 'Envoi vers une liste de numéros sans compte sur la plateforme — les inscrits d\'un cours ou des personnes intéressées. Les messages partent un à un, avec un délai entre eux, jamais tous en même temps.',
        'new' => 'Nouvelle campagne',
        'created' => 'Campagne enregistrée en brouillon avec :count numéros exploitables. Vérifiez-la, puis lancez l\'envoi.',
        'started' => 'L\'envoi a commencé. Les messages partent l\'un après l\'autre selon le délai choisi.',
        'stopped' => 'Campagne arrêtée. :count destinataires ont été annulés avant tout envoi.',
        'confirm_start' => 'L\'envoi réel vers les numéros de la liste va commencer. Confirmer ?',
        'confirm_stop' => 'Ce qui n\'est pas encore parti s\'arrêtera. Les messages déjà envoyés ne peuvent pas être rappelés. Confirmer ?',
        'start' => 'Lancer l\'envoi',
        'stop' => 'Arrêter la campagne',
        'refresh' => 'Actualiser l\'état',

        'fields' => [
            'name' => 'Nom de la campagne',
            'name_hint' => 'Pour votre suivi seulement — les destinataires ne le voient pas.',
            'body' => 'Texte du message',
            'reason' => 'Motif de l\'envoi',
            'reason_hint' => 'Conservé dans le dossier de la campagne.',
            'recipients_text' => 'Numéros saisis',
            'recipients_text_hint' => 'Un numéro par ligne ; le nom peut précéder, séparé par une virgule : Ahmed, +201012345678',
            'recipients_file' => 'Fichier de numéros',
            'recipients_file_hint' => 'Excel ou CSV à deux colonnes : nom et numéro. La ligne d\'en-tête est ignorée automatiquement.',
            'delay_min' => 'Délai minimal (secondes)',
            'delay_max' => 'Délai maximal (secondes)',
            'delay_hint' => 'Un délai aléatoire entre les deux est pris avant chaque message — un rythme parfaitement régulier est la marque la plus visible d\'une machine.',
            'media' => 'Pièces jointes',
            'media_hint' => 'Images, vidéos ou fichiers qui arrivent avant le texte. Supprimées du serveur :days jours après la fin de la campagne.',
        ],

        'placeholders' => [
            'title' => 'Le nom du destinataire dans le texte',
            'hint' => 'Écrivez :tokens dans le message : il est remplacé par le nom de chaque destinataire tel qu\'il figure dans la liste. Sans nom, le message part sans le jeton.',
        ],

        'preview' => [
            'run' => 'Vérifier la liste',
            'accepted' => 'Numéros exploitables',
            'rejected' => 'Numéros à revoir',
            'duplicates' => 'Doublons retirés',
            'sample' => 'Le message tel que le premier destinataire le lira',
            'empty' => 'La liste n\'a pas encore été vérifiée.',
            'failed' => 'La liste n\'a pas pu être vérifiée. Réessayez.',
        ],

        'status' => [
            'draft' => 'Brouillon',
            'running' => 'En cours',
            'completed' => 'Terminée',
            'stopped' => 'Arrêtée',
        ],

        'counts' => [
            'pending' => 'En attente',
            'sent' => 'Remis au fournisseur',
            'failed' => 'Échecs',
            'cancelled' => 'Annulés',
            'invalid' => 'Numéros rejetés',
            'progress' => ':sent sur :total',
        ],

        'problems' => [
            'title' => 'Numéros qui n\'ont rien reçu',
            'hint' => 'Corrigez le numéro chez vous et ajoutez-le à une nouvelle campagne — la liste d\'une campagne enregistrée ne se modifie pas.',
            'name' => 'Nom',
            'input' => 'Tel que saisi',
            'reason' => 'Motif',
            'empty' => 'Tous les numéros sont corrects.',
        ],

        'reasons' => [
            'phone_empty' => 'Aucun numéro',
            'phone_invalid_characters' => 'Le numéro contient des lettres ou des symboles illisibles',
            'phone_missing_country_code' => 'Numéro local sans indicatif — écrivez-le au format international, avec + ou 00',
            'phone_invalid_format' => 'La longueur ou la forme du numéro est invalide',
            'channel_disabled' => 'Le canal WhatsApp a été coupé avant son tour',
            'interrupted' => 'L\'envoi s\'est interrompu avant d\'en connaître l\'issue — vérifiez avant de renvoyer',
            'whatsapp_media_missing' => 'La pièce jointe n\'est plus sur le serveur',
            'whatsapp_media_unreadable' => 'La pièce jointe n\'a pas pu être lue',
            'whatsapp_network_error' => 'Le fournisseur est injoignable',
            'whatsapp_provider_error' => 'Le fournisseur a rejeté le message',
            'whatsapp_provider_response_invalid' => 'Réponse illisible du fournisseur',
            'whatsapp_configuration_invalid' => 'Les paramètres de connexion au fournisseur sont incomplets',
            'whatsapp_body_empty' => 'Le texte du message est vide une fois le nom inséré',
        ],

        'errors' => [
            'recipients_required' => 'Saisissez des numéros ou déposez un fichier.',
        ],

        'empty' => 'Aucune campagne pour l\'instant.',
        'channel_off_warning' => 'Le canal WhatsApp est arrêté — vous pouvez préparer une campagne, mais l\'envoi ne démarrera pas tant qu\'il n\'est pas réactivé.',
        'media_expires' => 'Les pièces jointes seront supprimées le :date',
    ],

    'other_entries' => [
        'title' => 'Autres points d’entrée WhatsApp',
        'hint' => 'Cette section rassemble et gouverne ; elle ne supprime rien. Le bouton de messagerie sur un profil d’élève ou d’enseignant et la page des messages continuent de fonctionner, passent par le même moteur et apparaissent dans ce journal.',
        'messages_link' => 'Centre de messages (envoi groupé, planifié et modèles de tous les canaux)',
    ],
];
