<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Formulaires d’inscription',
    'model_label' => 'Formulaire d’inscription',
    'plural_model_label' => 'Formulaires d’inscription',
    'sections' => [
        'identity' => 'Identité et publication',
        'questions' => 'Questions du formulaire',
        'questions_help' => 'Les données principales de l’élève sont incluses automatiquement. Ajoutez ici les questions propres au programme.',
    ],
    'fields' => [
        'title' => 'Nom du formulaire',
        'title_ar' => 'Nom (arabe)',
        'title_en' => 'Nom (anglais)',
        'title_fr' => 'Nom (français)',
        'slug' => 'Identifiant du lien',
        'slug_help' => 'Lettres latines minuscules, chiffres et tirets uniquement.',
        'description_ar' => 'Description (arabe)',
        'description_en' => 'Description (anglais)',
        'description_fr' => 'Description (français)',
        'is_active' => 'Publié et ouvert aux inscriptions',
        'is_active_help' => 'La désactivation conserve l’historique mais bloque les nouvelles demandes.',
        'questions_count' => 'Questions',
        'updated_at' => 'Dernière mise à jour',
        'change_reason' => 'Motif de création ou modification',
        'change_reason_help' => 'Le motif et les valeurs avant/après sont enregistrés dans le journal d’audit.',
    ],
    'filters' => ['is_active' => 'État de publication'],
    'actions' => [
        'add_question' => 'Ajouter une question',
        'open_public_form' => 'Ouvrir le formulaire public',
    ],
];
