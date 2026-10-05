<?php

declare(strict_types=1);

return [
    'group' => 'Apprentissage',
    'fields' => [
        'created_at' => 'Créé le', 'reason' => 'Motif du changement',
        'reason_help' => 'Ce motif est conservé dans le journal d’audit.',
    ],
    'sections' => ['audit' => 'Motif et audit'],
    'filters' => ['trashed' => 'Archivés'],
    'hub' => ['empty' => 'Aucune donnée enregistrée.', 'unrestricted' => 'Sans restriction'],
    'currencies' => ['EGP' => 'Livre égyptienne', 'SAR' => 'Riyal saoudien', 'AED' => 'Dirham émirati', 'USD' => 'Dollar américain'],
    'program' => [
        'label' => 'programme', 'plural' => 'Programmes',
        'sections' => [
            'identity' => 'Identité du programme', 'delivery' => 'Périmètre, durée et objectifs',
            'pricing' => 'Séance et tarif par défaut', 'eligibility' => 'Admission et correspondance',
            'eligibility_help' => 'Une liste vide signifie qu’aucune restriction ne s’applique.',
        ],
        'hub' => [
            'title' => 'Centre du programme', 'overview' => 'Vue d’ensemble', 'levels' => 'Niveaux',
            'courses' => 'Cours', 'eligibility' => 'Éligibilité', 'categories' => 'Catégories',
        ],
        'fields' => [
            'code' => 'Code', 'name' => 'Nom', 'name_ar' => 'Nom (arabe)', 'name_en' => 'Nom (anglais)',
            'description_ar' => 'Description (arabe)', 'description_en' => 'Description (anglais)',
            'duration_weeks' => 'Durée en semaines', 'default_session_minutes' => 'Durée de séance par défaut',
            'default_rate' => 'Tarif par défaut', 'currency' => 'Devise', 'is_active' => 'Actif',
            'sort_order' => 'Ordre', 'program_type' => 'Type de programme', 'start_date' => 'Date de début',
            'end_date' => 'Date de fin', 'target_gender' => 'Public cible', 'age_from' => 'Âge minimum',
            'age_to' => 'Âge maximum', 'age_range' => 'Tranche d’âge', 'objectives' => 'Objectifs',
            'objective_key' => 'Code objectif', 'objective_value' => 'Description de l’objectif',
            'language' => 'Langue', 'rate_minor_units_help' => 'Saisissez le montant dans la plus petite unité monétaire.',
            'countries' => 'Pays autorisés', 'regions' => 'Régions autorisées',
            'teacher_gender_rule' => 'Règle de correspondance enseignant', 'manual_approval_required' => 'Approbation manuelle requise',
            'requires_individual_sessions' => 'Séances individuelles requises', 'levels_count' => 'Niveaux',
            'courses_count' => 'Cours', 'active_courses_count' => 'Cours actifs',
        ],
        'filters' => ['active' => 'Programmes actifs seulement'],
    ],
    'level' => [
        'label' => 'niveau', 'plural' => 'Niveaux', 'sections' => ['identity' => 'Identité du niveau'],
        'hub' => ['title' => 'Centre du niveau', 'overview' => 'Vue d’ensemble', 'courses' => 'Cours'],
        'fields' => [
            'program' => 'Programme', 'code' => 'Code', 'name' => 'Nom', 'name_ar' => 'Nom (arabe)',
            'name_en' => 'Nom (anglais)', 'sort_order' => 'Ordre', 'courses_count' => 'Cours',
        ],
    ],
    'course' => [
        'label' => 'Cours', 'plural' => 'Cours',
        'hub' => [
            'title' => 'Centre du cours', 'overview' => 'Vue d’ensemble', 'description' => 'Description',
            'rules' => 'Règles et prérequis', 'categories' => 'Catégories',
        ],
        'sections' => ['identity' => 'Identité du cours', 'delivery' => 'Classification et diffusion', 'rules' => 'Règles et prérequis'],
        'fields' => [
            'level' => 'Niveau', 'program' => 'Programme', 'organization' => 'Organisation', 'code' => 'Code',
            'name' => 'Nom', 'name_ar' => 'Nom (arabe)', 'name_en' => 'Nom (anglais)',
            'description_ar' => 'Description (arabe)', 'description_en' => 'Description (anglais)',
            'total_sessions' => 'Nombre de séances', 'completion_rules' => 'Règles de réussite',
            'prerequisites' => 'Prérequis', 'rule_key' => 'Règle', 'rule_value' => 'Valeur', 'is_active' => 'Actif',
            'session_mode' => 'Mode de séance', 'target_gender' => 'Public cible', 'inherits_program' => 'Hérité du programme',
            'age_from' => 'Âge minimum', 'age_to' => 'Âge maximum', 'age_range' => 'Tranche d’âge', 'any_age' => 'Tout âge',
            'age_from_only' => ':age et plus', 'age_to_only' => 'Jusqu’à :age', 'default_duration_minutes' => 'Durée de séance',
            'duration_help' => 'Valeur par défaut lors de la planification.', 'sessions_per_week' => 'Séances par semaine',
            'categories' => 'Catégories',
        ],
        'filters' => ['active' => 'Cours actifs seulement', 'program' => 'Programme', 'trashed' => 'Archivés'],
        'errors' => ['no_organization' => 'Votre compte n’est lié à aucune organisation.', 'level_outside_organization' => 'Le niveau appartient à une autre organisation.'],
    ],
    'category' => [
        'label' => 'Catégorie',
        'fields' => [
            'code' => 'Code', 'name' => 'Nom', 'name_ar' => 'Nom (arabe)', 'name_en' => 'Nom (anglais)',
            'parent' => 'Catégorie parente', 'scope' => 'Périmètre', 'sort_order' => 'Ordre', 'is_active' => 'Active',
        ],
    ],
    'actions' => [
        'create_level' => 'Ajouter un niveau', 'level_created' => 'Le niveau a été créé.',
        'create_category' => 'Ajouter une catégorie', 'update_category' => 'Modifier une catégorie',
        'archive_category' => 'Archiver une catégorie', 'category_created' => 'La catégorie a été créée.',
        'category_updated' => 'La catégorie a été mise à jour.', 'category_archived' => 'La catégorie a été archivée.',
        'activate' => 'Activer', 'deactivate' => 'Désactiver', 'status_updated' => 'Le statut a été mis à jour.', 'archive' => 'Archiver',
    ],
    'program_types' => ['fixed_duration' => 'Durée déterminée', 'ongoing' => 'Continu'],
    'teacher_gender_rules' => ['any' => 'Tout enseignant qualifié', 'same' => 'Même genre', 'opposite' => 'Genre opposé'],
    'session_modes' => ['individual' => 'Individuel', 'group' => 'Groupe', 'both' => 'Individuel et groupe'],
    'target_genders' => ['male' => 'Garçons', 'female' => 'Filles', 'all' => 'Tout le monde'],
];
