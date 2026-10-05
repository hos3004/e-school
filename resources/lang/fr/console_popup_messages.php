<?php

declare(strict_types=1);

/*
 * Textes de la page « Messages contextuels » (Pop Messages) de la console.
 */
return [
    'title' => 'Messages contextuels',
    'subtitle' => 'Campagnes de fenêtres contextuelles dans l’application : bandeau bas ou plein écran, pour un public et une période donnés.',

    'list' => [
        'empty' => 'Aucune campagne pour le moment. Créez-en une avec le bouton ci-dessus.',
        'columns' => [
            'internal_name' => 'Nom interne',
            'type' => 'Type',
            'status' => 'Statut',
            'audiences' => 'Public',
            'display_mode' => 'Mode d’affichage',
            'priority' => 'Priorité',
            'window' => 'Période d’affichage',
            'media' => 'Pièces jointes',
            'actions' => 'Actions',
        ],
        'audiences_more' => '+:count autres',
        'no_media' => 'Aucune pièce jointe',
    ],

    'actions' => [
        'create' => 'Nouvelle campagne',
        'edit' => 'Modifier',
        'view' => 'Voir',
        'publish' => 'Publier',
        'pause' => 'Mettre en pause',
        'archive' => 'Archiver',
        'cancel' => 'Annuler',
        'save' => 'Enregistrer',
        'save_and_publish' => 'Enregistrer',
        'add_link' => 'Ajouter un lien',
        'remove_link' => 'Supprimer',
        'upload' => 'Téléverser',
    ],

    'confirm' => [
        'publish' => 'Cette campagne commencera à s’afficher pour son public immédiatement, dans sa période d’affichage. Confirmer ?',
        'pause' => 'Cette campagne cessera de s’afficher jusqu’à sa republication. Confirmer ?',
        'archive' => 'L’archivage est définitif — cette campagne ne pourra plus être republiée. Confirmer ?',
    ],

    'reason' => [
        'label' => 'Motif',
        'placeholder' => 'Indiquez le motif de cette création, modification ou changement de statut — enregistré dans le journal d’audit.',
    ],

    'fields' => [
        'internal_name' => 'Nom interne (réservé à l’administration)',
        'internal_name_help' => 'Non visible par les utilisateurs — sert uniquement à identifier la campagne dans cette liste.',
        'type' => 'Type de campagne',
        'display_mode' => 'Mode d’affichage',
        'display_mode_bottom_banner' => 'Bandeau bas (non bloquant)',
        'display_mode_fullscreen' => 'Plein écran',
        'title_locale' => 'Titre',
        'body_locale' => 'Texte',
        'title_ar' => 'Titre (arabe)',
        'title_en' => 'Titre (anglais, optionnel)',
        'title_fr' => 'Titre (français, optionnel)',
        'body_ar' => 'Texte (arabe)',
        'body_en' => 'Texte (anglais, optionnel)',
        'body_fr' => 'Texte (français, optionnel)',
        'audiences' => 'Public ciblé',
        'excluded_audiences' => 'Exclure',
        'excluded_audiences_help' => 'L’exclusion l’emporte toujours : un public exclu ne voit jamais la campagne, même s’il correspond aussi au public ciblé.',
        'is_dismissible' => 'Fermable manuellement',
        'requires_acknowledgement' => 'Nécessite un accusé de l’utilisateur',
        'acknowledgement_label' => 'Texte du bouton d’accusé',
        'auto_dismiss_seconds' => 'Fermeture automatique après (secondes)',
        'auto_dismiss_help' => 'La campagne disparaît d’elle-même après cette durée — utile si vous ne voulez ni bouton de fermeture ni accusé.',
        'auto_dismiss_disabled_hint' => 'Indisponible quand l’accusé est obligatoire — l’accusé seul suffit comme sortie sûre.',
        'safe_exit_hint' => 'Une campagne doit avoir au moins une sortie sûre : fermeture manuelle, accusé, ou fermeture automatique.',
        'priority' => 'Priorité',
        'priority_help' => 'La priorité la plus élevée s’affiche en premier quand plusieurs campagnes sont éligibles pour le même utilisateur.',
        'starts_at' => 'Début d’affichage',
        'ends_at' => 'Fin d’affichage (optionnel)',
        'placement' => 'Emplacement',
        'page_key' => 'Page spécifique',
        'frequency' => 'Fréquence',
        'action_type' => 'Bouton d’action (optionnel)',
        'action_type_none' => 'Aucun bouton d’action',
        'action_type_internal_page' => 'Une page de la plateforme',
        'action_type_external_url' => 'Lien externe',
        'action_target_internal' => 'Page',
        'action_target_external' => 'URL (doit commencer par https://)',
        'action_label' => 'Texte du bouton d’action',
        'reason' => 'Motif',
    ],

    'media' => [
        'title' => 'Pièces jointes',
        'hint' => 'Image, vidéo, audio, ou fichier téléchargeable — chaque type a sa propre limite de taille et ses types de fichiers autorisés.',
        'kind' => 'Type de fichier',
        'kind_image' => 'Image',
        'kind_video' => 'Vidéo',
        'kind_audio' => 'Audio',
        'kind_file' => 'Fichier téléchargeable',
        'has_sound' => 'Contient du son',
        'uploading' => 'Téléversement…',
        'uploaded' => 'Téléversé.',
        'upload_failed' => 'Échec du téléversement — vérifiez le type et la taille autorisés.',
        'save_before_upload' => 'Enregistrez d’abord la campagne comme brouillon avant d’ajouter des pièces jointes.',
        'empty' => 'Aucune pièce jointe pour le moment.',
    ],

    'links' => [
        'title' => 'Liens dans le texte',
        'hint' => 'Rendez un mot ou une expression du texte cliquable : saisissez-le exactement comme il apparaît ci-dessus, puis choisissez sa destination — un lien externe ou un fichier déposé sur cette campagne.',
        'text' => 'Mot ou expression',
        'text_placeholder' => 'Doit apparaître exactement tel quel dans le texte ci-dessus',
        'target_type' => 'Destination',
        'target_external' => 'Lien externe (https://)',
        'target_media' => 'Fichier déposé sur cette campagne',
        'target_media_empty' => 'Aucun fichier téléchargeable n’a encore été déposé — déposez-en un dans la section des pièces jointes.',
        'external_url' => 'URL',
        'empty' => 'Aucun lien dans le texte.',
    ],

    'status_hint' => [
        'locked_while_published' => 'Cette campagne est actuellement publiée — mettez-la en pause pour modifier son contenu.',
        'cannot_transition' => 'Aucun changement de statut disponible ici.',
    ],

    'messages' => [
        'created' => 'Campagne créée comme brouillon.',
        'updated' => 'Modifications de la campagne enregistrées.',
        'status_changed' => 'Statut de la campagne modifié.',
    ],
];
