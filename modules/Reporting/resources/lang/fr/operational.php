<?php

declare(strict_types=1);

return [
    'navigation' => 'Centre de rapports',
    'title' => 'Rapports opérationnels des séances',
    'description' => 'Synthèses et détails des séances, élèves, enseignants et groupes selon la période et les filtres.',
    'periods' => [
        'today' => 'Aujourd’hui', 'yesterday' => 'Hier', 'this_week' => 'Cette semaine',
        'previous_week' => 'Semaine précédente', 'this_month' => 'Ce mois', 'custom' => 'Période personnalisée',
    ],
    'filters' => [
        'period' => 'Période', 'preset' => 'Période rapide', 'from' => 'Du', 'until' => 'Au',
        'status' => 'État de la séance', 'attendance_status' => 'Présence', 'session_type' => 'Type de séance',
        'student' => 'Élève', 'teacher' => 'Enseignant effectif', 'original_teacher' => 'Enseignant initial',
        'group' => 'Groupe', 'course' => 'Cours', 'report_status' => 'État du rapport enseignant',
        'search' => 'Recherche',
    ],
    'columns' => [
        'session' => 'Séance', 'scheduled_at' => 'Horaire', 'duration' => 'Durée', 'course' => 'Cours',
        'group' => 'Groupe', 'teacher' => 'Enseignant', 'students' => 'Élèves', 'attendance' => 'Présence',
        'status' => 'État', 'session_type' => 'Type', 'report_status' => 'Rapport enseignant',
        'cancellation_reason' => 'Motif d’annulation/report',
    ],
    'summary' => [
        'total' => 'Total', 'completed' => 'Terminées', 'cancelled' => 'Annulées', 'postponed' => 'Reportées',
        'no_show' => 'Absence', 'excused' => 'Absence justifiée', 'scheduled' => 'À venir/en cours',
        'students' => 'Élèves', 'teachers' => 'Enseignants', 'groups' => 'Groupes',
        'present' => 'Présents', 'absent' => 'Absents', 'attendance_rate' => 'Taux de présence',
        'scheduled_minutes' => 'Minutes prévues', 'actual_minutes' => 'Minutes réelles',
        'reports_submitted' => 'Rapports remis', 'reports_late' => 'Rapports en retard', 'reports_missing' => 'Rapports manquants',
    ],
    'report_status' => ['submitted' => 'Remis', 'late' => 'Remis en retard', 'missing' => 'Manquant', 'not_required' => 'Pas encore requis'],
    'attendance' => ['unrecorded' => 'Non enregistrée', 'present_count' => ':count présent(s)', 'absent_count' => ':count absent(s)'],
    'actions' => ['run_report' => 'Générer le rapport', 'export_pdf' => 'Exporter en PDF', 'reset_filters' => 'Réinitialiser les filtres'],
    'initial_title' => 'Choisissez la période et les filtres, puis générez le rapport',
    'initial_description' => 'Les données des séances ne sont chargées qu’après le lancement du rapport.',
    'empty' => 'Aucune séance ne correspond à la période et aux filtres.',
    'limit_exceeded' => 'Le nombre de résultats dépasse la limite. Réduisez la période ou ajoutez des filtres.',
    'period_label' => 'Du :from au :until',
    'substitute' => 'Remplaçant de :teacher',
    'selected_value' => 'Valeur sélectionnée',
    'not_available' => 'Indisponible',
    'separators' => ['list' => ', '],
    'minutes' => ':count min',
    'unknown_session' => 'Séance sans titre', 'unknown_student' => 'Élève inconnu', 'unknown_teacher' => 'Enseignant inconnu',
    'unknown_group' => 'Groupe inconnu', 'unknown_course' => 'Cours inconnu', 'no_students' => 'Aucun élève',
];
