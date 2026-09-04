<?php

declare(strict_types=1);

return [
    'group' => 'Journée scolaire',
    'common' => ['yes' => 'Oui', 'no' => 'Non', 'not_available' => 'Indisponible'],
    'awaiting' => ['teacher' => 'Enseignant', 'student' => 'Étudiant', 'admin' => 'Administration', 'none' => '—'],
    'postponement' => [
        'admin_note' => 'Note administrative', 'approve' => 'Approuver', 'approved' => 'Report approuvé.',
        'details' => 'Détails de la demande', 'id' => 'Identifiant', 'not_available' => 'Indisponible',
        'reason' => 'Motif', 'reject' => 'Refuser', 'rejected' => 'Report refusé.',
        'requested_by' => 'Demandé par', 'responded_at' => 'Répondu le', 'responded_by' => 'Répondu par',
        'teacher_proposed_start' => 'Horaire proposé par l’enseignant', 'label' => 'Demande de report',
        'plural' => 'Demandes de report', 'session' => 'Séance', 'student' => 'Étudiant', 'status' => 'État',
        'awaiting' => 'En attente de', 'proposed_start' => 'Horaire proposé', 'agreed_start' => 'Horaire convenu',
        'expires_in' => 'Temps restant', 'expired' => 'Expirée', 'hours_left' => ':hours h restantes',
        'makeup' => 'Séance de rattrapage', 'propose_alternative' => 'Proposer une alternative',
        'alternative_proposed' => 'L’horaire alternatif a été envoyé.',
        'requires_admin_review' => 'Validation administrative requise',
    ],
    'schedule' => [
        'navigation' => 'Modèles de planning', 'label' => 'Modèle de planning', 'plural' => 'Modèles de planning',
        'sections' => ['target' => 'Parcours et cible', 'recurrence' => 'Récurrence et horaire', 'governance' => 'Traçabilité', 'overview' => 'Résumé du planning'],
        'targets' => ['group' => 'Placement collectif', 'student' => 'Placement individuel (un étudiant)'],
        'availability' => [
            'title' => 'Résumé des horaires de l’enseignant',
            'choose_available_time' => 'Choisir un horaire disponible',
            'booked_times_hidden' => 'Les horaires réservés sont automatiquement exclus.',
            'select_details' => 'Choisissez l’enseignant, les jours et la durée pour voir les créneaux.',
            'no_declared' => 'Cet enseignant n’a aucune disponibilité approuvée ; une séance individuelle ne peut pas être réservée.',
            'overview' => ':available horaires disponibles · :booked réservations · :planned séances prévues.',
        ],
        'created' => [
            'title' => ':count séances réservées',
            'body' => 'Bénéficiaire : :target · Cours : :course · Enseignant : :teacher · Durée : :duration minutes — Horaires des séances : :sessions — Autres horaires disponibles : :available',
        ],
        'fields' => [
            'target_type' => 'Type de cible', 'target' => 'Groupe / étudiant', 'group' => 'Groupe', 'student' => 'Étudiant',
            'course' => 'Cours', 'teacher' => 'Enseignant', 'weekdays' => 'Jours des séances',
            'interval_weeks' => 'Répéter toutes les (semaines)', 'start_time' => 'Heure locale', 'duration' => 'Durée',
            'timezone' => 'Fuseau horaire', 'starts_on' => 'Début du modèle', 'ends_on' => 'Fin',
            'reason' => 'Motif de l’opération', 'reason_help' => 'Conservé dans le journal d’audit.',
            'recurrence' => 'Récurrence', 'rrule' => 'RRULE', 'status' => 'État', 'materialized_until' => 'Séances générées jusqu’au',
        ],
        'weekdays' => [0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'],
        'minutes' => ':minutes minutes', 'minute_short' => 'min',
        'recurrence_summary' => '{1} Chaque semaine : :days|[2,*] Toutes les :interval semaines : :days',
        'status' => ['active' => 'Actif', 'inactive' => 'Inactif'],
        'actions' => [
            'individual_quran_placement' => 'Affecter les étudiants du Coran individuel',
            'materialize' => 'Générer et synchroniser', 'materialized' => ':count séances synchronisées, :warnings avertissements.',
            'deactivate' => 'Désactiver', 'deactivated' => 'Modèle désactivé ; les séances proches ont été conservées.',
            'activate' => 'Réactiver', 'activated' => 'Modèle réactivé et séances futures générées.',
        ],
        'hub' => [
            'title' => 'Centre d’exploitation du planning', 'sessions' => 'Séances générées', 'history' => 'Historique',
            'empty' => 'Aucune donnée.', 'session' => 'Séance', 'scheduled_start' => 'Début', 'scheduled_end' => 'Fin',
            'action' => 'Action', 'actor' => 'Auteur', 'changed_at' => 'Date du changement',
        ],
    ],
];
