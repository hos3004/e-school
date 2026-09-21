<?php

declare(strict_types=1);

/*
| Textes de la page Archives de la console.
|
| « Archive » signifie ici cloture et retire de la vue courante, pas supprime.
| La formulation preserve cette difference, car l'administrateur decide a partir
| d'elle : qui croit supprimer n'appuiera jamais sur le bouton.
*/

return [
    'title' => 'Archives',
    'description' => "Ce qui est termine et a quitte la vue quotidienne — avec toutes ses donnees et son bilan de cloture. Rien ici n'est supprime, et tout peut etre rouvert.",

    'sections' => [
        'archived' => 'Archive',
        'candidates' => 'Pret a archiver',
        'programs' => 'Programmes',
        'courses' => 'Cours',
        'groups' => 'Groupes',
    ],

    'empty' => [
        'archived' => "Rien n'a encore ete archive.",
        'candidates' => "Il n'y a rien a archiver pour le moment.",
    ],

    'labels' => [
        'closed_at' => 'Archive le',
        'closed_by' => 'Archive par',
        'reason' => 'Motif',
        'summary' => 'Bilan de cloture',
        'captured_at' => 'Instantane pris le',
    ],

    'summary' => [
        'levels_total' => 'Niveaux',
        'courses_total' => 'Cours',
        'courses_closed' => 'Cours archives',
        'enrollments_total' => 'Inscriptions',
        'enrollments_completed' => 'Inscriptions terminees',
        'enrollments_withdrawn' => 'Inscriptions retirees',
        'students_distinct' => 'Beneficiaires',
        'sessions_total' => 'Seances',
        'sessions_completed' => 'Seances tenues',
        'sessions_cancelled' => 'Seances annulees',
        'sessions_stale' => 'Seances passees jamais cloturees',
        'sessions_other' => 'Autres seances (absence/excuse/report/remplacee)',
        'courses_open' => 'Cours non archives',
        'teachers_distinct' => 'Enseignants',
        'first_session_at' => 'Premiere seance',
        'last_session_at' => 'Derniere seance',
        'planned_sessions' => 'Seances prevues',
        'members_total' => 'Membres',
        'teachers_total' => 'Enseignants affectes',
        'programs_total' => 'Programmes lies',
        'capacity' => 'Capacite',
        'status' => 'Statut',
        'starts_on' => 'Debut',
        'ends_on' => 'Fin',
    ],

    'blockers' => [
        'title' => 'Archivage impossible pour le moment',
        'help' => "L'archivage ne supprime rien, mais il masque de la vue courante — il est donc refuse tant qu'un travail en cours se trouve dessous.",
        'courses_active' => 'Cours actifs : :count',
        'enrollments_live' => 'Inscriptions ouvertes : :count',
        'sessions_open' => 'Seances pas dans un etat final : :count',
        'members_active' => 'Eleves encore inscrits : :count',
    ],

    'actions' => [
        'preview' => 'Apercu du bilan',
        'close' => 'Archiver',
        'reopen' => 'Rouvrir',
        'cancel' => 'Annuler',
        'loading' => 'Calcul en cours…',
    ],

    'dialog' => [
        'close_title' => 'Archiver :name',
        'reopen_title' => 'Rouvrir :name',
        'reason' => 'Motif',
        'reason_placeholder' => 'Pourquoi archivez-vous ceci maintenant ?',
        'reason_help' => "Le motif est conserve avec le bilan dans le journal d'audit, et reste lisible apres reouverture.",
        'closable' => 'Ce bilan sera fige tel quel lors de l archivage.',
    ],

    'flash' => [
        'closed' => 'Archive. Le bilan est conserve, et vous pouvez rouvrir quand vous voulez.',
        'reopened' => 'Rouvert et de retour dans la vue courante.',
    ],
];
