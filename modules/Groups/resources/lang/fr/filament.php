<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Affaires académiques',
    'navigation_label' => 'Groupes',
    'model_label' => 'Groupe',
    'plural_model_label' => 'Groupes',
    'name_locale_key' => 'Langue',
    'name_value_label' => 'Nom',
    'active_members_count' => 'Étudiants actifs',
    'not_available' => 'Indisponible',
    'fields' => [
        'name_ar' => 'Nom du groupe en arabe',
        'name_en' => 'Nom du groupe en anglais',
        'name_fr' => 'Nom du groupe en français',
        'reason_help' => "Indiquez la raison de la création ou de la modification pour le journal d'audit.",
    ],
    'hub' => [
        'title' => 'Centre opérationnel du groupe',
        'overview' => 'Vue d’ensemble du groupe',
        'available_places' => 'Places disponibles',
        'programs' => 'Programmes',
        'teachers' => 'Enseignants',
        'students' => 'Étudiants',
        'sessions' => 'Séances',
        'empty' => 'Aucune donnée dans cette section.',
        'fields' => [
            'teacher' => 'Enseignant',
            'student' => 'Étudiant',
            'student_code' => 'Code étudiant',
            'session' => 'Séance',
            'scheduled_start' => 'Début',
            'scheduled_end' => 'Fin',
        ],
    ],
    'actions' => [
        'place_student' => 'Affecter un étudiant',
        'student_placed' => 'L’étudiant a été affecté au groupe.',
        'assign_teacher' => 'Affecter un enseignant',
        'teacher_assigned' => 'L’enseignant a été affecté au groupe.',
        'attach_program' => 'Rattacher un programme',
        'program_attached' => 'Le programme a été rattaché au groupe.',
        'activate' => 'Activer le groupe',
        'complete' => 'Terminer le groupe',
        'archive' => 'Archiver le groupe',
        'active_success' => 'Le groupe a été activé.',
        'completed_success' => 'Le groupe a été terminé.',
    ],
];
