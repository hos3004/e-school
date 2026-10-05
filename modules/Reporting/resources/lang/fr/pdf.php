<?php

declare(strict_types=1);

return [
    'document' => [
        'organization_fallback' => 'École en ligne',
        'generated_at' => 'Généré le',
        'period' => 'Période',
        'timezone' => 'Fuseau horaire',
        'filters' => 'Filtres appliqués',
        'summary' => 'Résumé du rapport',
        'sessions' => 'Détails des séances',
        'no_results' => 'Aucune séance ne correspond à la période et aux filtres sélectionnés.',
        'page' => 'Page',
    ],
    'columns' => [
        'session' => 'Séance',
        'schedule' => 'Horaire',
        'group' => 'Groupe',
        'teacher' => 'Enseignant',
        'students' => 'Élèves',
        'attendance' => 'Présence',
        'status' => 'Statut',
        'cancellation_reason' => "Motif d'annulation",
    ],
    'labels' => [
        'course' => 'Cours',
        'type' => 'Type',
        'duration' => 'Durée',
        'minutes' => ':count min',
        'original_teacher' => "Enseignant d'origine",
        'report_status' => 'Statut du rapport',
        'not_available' => '—',
    ],
    'errors' => [
        'invalid_configuration' => 'Le rapport ne peut pas être exporté car le service PDF est mal configuré.',
        'temporary_directory_unavailable' => "L'espace temporaire d'exportation du rapport est indisponible.",
        'rendering_failed' => "Le PDF n'a pas pu être créé. Réessayez ou contactez l'assistance.",
        'output_invalid' => "Le service d'exportation a produit un fichier non valide.",
    ],
];
