<?php

declare(strict_types=1);

/*
| Français — compléments de filament pour le module Staff.
*/

return [
    'navigation_group' => 'Personnes',
    'common' => [
        'active' => 'En poste',
    ],
    'profile' => [
        'model_label' => 'Profil du personnel',
        'plural_label' => 'Profils du personnel',
        'fields' => [
            'bio' => 'Résumé',
            'country' => 'Pays',
            'date_of_birth' => 'Date de naissance',
            'employment_type' => 'Type d’emploi',
            'gender' => 'Sexe',
            'hired_at' => 'Date d’embauche',
            'phone' => 'Téléphone',
            'region' => 'Région',
            'specializations' => 'Spécialisations',
            'staff_code' => 'Code du personnel',
            'terminated_at' => 'Fin de service',
            'reason' => 'Motif de la modification',
            'reason_help' => 'Un motif administratif clair enregistré dans le journal d’audit ; il n’est pas stocké sur le profil.',
        ],
        'resources' => [
            'actions' => [
                'edit' => 'Modifier le profil',
            ],
        ],
        'filters' => [
            'active' => 'En poste',
            'country' => 'Pays',
            'region' => 'Région',
        ],
        'gender_options' => [
            'female' => 'Femme',
            'male' => 'Homme',
        ],
    ],
    'teachers' => [
        'label' => 'Enseignants',
        'title' => 'Répertoire opérationnel des enseignants',
        'description' => 'Vue spécialisée des enseignants — indicateurs agrégés des systèmes réels ; les opérations restent dans le hub de l’enseignant.',
        'open_hub' => 'Ouvrir le hub enseignant',
        'edit' => 'Modifier le profil',
        'fields' => [
            'avatar' => 'Photo',
            'name' => 'Nom',
            'account_status' => 'Statut du compte',
            'qualified_courses' => 'Cours qualifiés',
            'active_groups' => 'Groupes actifs',
            'upcoming_sessions' => 'Séances à venir',
            'completed_this_month' => 'Terminées ce mois',
            'cancelled_this_month' => 'Annulées ce mois',
            'availability' => 'Disponibilité',
        ],
        'filters' => [
            'qualified_course' => 'Cours qualifié',
            'group' => 'Groupe',
        ],
    ],
];
