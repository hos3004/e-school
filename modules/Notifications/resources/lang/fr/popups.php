<?php

declare(strict_types=1);

return [

    'status' => [
        'draft' => 'Brouillon',
        'published' => 'Publiée',
        'paused' => 'En pause',
        'archived' => 'Archivée',
    ],

    'effective_status' => [
        'scheduled' => 'Programmée',
        'active' => 'Active maintenant',
        'expired' => 'Expirée',
    ],

    'type' => [
        'urgent_announcement' => 'Annonce urgente',
        'program_promotion' => 'Promotion de programme',
        'reminder' => 'Rappel',
        'administrative' => 'Avis administratif',
        'general' => 'Annonce générale',
    ],

    'audience' => [
        'student' => 'Élèves',
        'guardian' => 'Parents',
        'teacher' => 'Enseignants',
        'supervisor' => 'Superviseurs',
        'administrator' => 'Administrateurs',
        'all_authenticated' => 'Tous les utilisateurs connectés',
    ],

    'placement' => [
        'after_login' => 'Après connexion',
        'dashboard' => 'Tableau de bord',
        'specific_page' => 'Page spécifique',
        'all_authenticated_pages' => 'Première page éligible',
    ],

    'frequency' => [
        'once' => 'Une fois',
        'once_per_login' => 'Une fois par connexion',
        'once_per_day' => 'Une fois par jour',
        'until_acknowledged' => "Jusqu'à accusé de réception",
        'every_eligible_visit' => 'Chaque visite (usage limité)',
    ],

    'frequency_help' => [
        'once' => 'Affichée une seule fois par utilisateur',
        'once_per_login' => 'Affichée une fois après chaque connexion',
        'once_per_day' => 'Une fois par jour en UTC',
        'until_acknowledged' => "Continue jusqu'à l'accusé de réception",
        'every_eligible_visit' => 'À chaque visite — à utiliser avec prudence',
    ],

    'pages' => [
        'student_dashboard' => 'Tableau de bord élève',
        'student_schedule' => 'Emploi du temps élève',
        'guardian_dashboard' => 'Tableau de bord parent',
        'teacher_dashboard' => 'Tableau de bord enseignant',
        'admin_dashboard' => 'Panneau admin',
    ],

    'tabs' => [
        'content' => 'Contenu',
        'audience' => 'Public',
        'display' => 'Affichage',
        'scheduling' => 'Planification',
        'review' => 'Révision et aperçu',
    ],

    'fields' => [
        'internal_name' => 'Nom interne',
        'type' => 'Type de popup',
        'title_ar' => 'Titre (arabe)',
        'title_en' => 'Titre (anglais)',
        'title_fr' => 'Titre (français)',
        'body_ar' => 'Texte (arabe)',
        'body_en' => 'Texte (anglais)',
        'body_fr' => 'Texte (français)',
        'arabic_content' => 'Contenu arabe (obligatoire)',
        'optional_translations' => 'Traductions optionnelles',
        'plain_text_help' => 'Texte brut uniquement — pas de HTML ni de code. Toujours affiché échappé.',
        'cta_section' => 'Bouton d’action (optionnel)',
        'action_type' => "Type d'action",
        'internal_page' => 'Page interne approuvée',
        'external_url' => 'Lien externe (HTTPS uniquement)',
        'external_url_help' => 'S’ouvre dans un nouvel onglet en toute sécurité. Les liens non HTTPS sont refusés.',
        'action_label_ar' => 'Libellé du bouton (arabe)',
        'audiences' => 'Publics cibles',
        'audiences_help' => 'Choisissez au moins un public. « Tout le monde » couvre tous les utilisateurs authentifiés.',
        'placement' => 'Emplacement',
        'page_key' => 'Page cible',
        'frequency' => 'Règle de fréquence',
        'is_dismissible' => 'Fermeture possible par l’utilisateur',
        'requires_acknowledgement' => 'Accusé de réception obligatoire',
        'acknowledgement_label' => 'Libellé du bouton d’accusé',
        'priority' => 'Priorité (la plus haute s’affiche en premier)',
        'starts_at' => 'Début d’affichage (UTC)',
        'ends_at' => 'Fin d’affichage (UTC) — optionnel',
        'reason' => 'Motif de la modification',
        'reason_help' => 'Un motif clair enregistré dans le journal d’audit.',
    ],

    'options' => [
        'no_action' => 'Sans bouton',
    ],

    'actions' => [
        'create' => 'Nouvelle campagne',
        'view' => 'Voir',
        'edit' => 'Modifier',
        'publish' => 'Publier',
        'pause' => 'Mettre en pause',
        'resume' => 'Reprendre',
        'duplicate' => 'Dupliquer en brouillon',
        'archive' => 'Archiver',
    ],

    'confirm' => [
        'publish_description' => 'La campagne devient visible pour le public choisi dès l’ouverture de sa fenêtre, selon la fréquence et la priorité.',
        'archive_description' => 'L’archivage est définitif : la campagne ne réapparaît plus. Dupliquer en brouillon reste l’alternative sûre.',
    ],

    'messages' => [
        'status_changed' => 'Statut de la campagne mis à jour.',
        'duplicated' => 'Une nouvelle copie brouillon a été créée.',
    ],

    'errors' => [
        'reason_required' => 'Un motif est requis et est enregistré dans le journal d’audit.',
        'invalid_transition' => 'Cette transition de statut n’est pas autorisée.',
        'arabic_content_required' => 'Le titre et le texte arabes sont obligatoires avant publication.',
        'audience_required' => 'Sélectionnez au moins un public.',
        'unsafe_exit' => 'Un popup ni fermable ni soumis à accusé piégerait l’utilisateur.',
        'invalid_page_key' => 'La page choisie n’est pas dans le registre approuvé.',
        'invalid_window' => 'La fin doit venir après le début.',
        'locked_while_published' => 'Les campagnes publiées sont verrouillées — mettez en pause ou dupliquez.',
        'not_available' => 'Cette campagne n’est pas disponible actuellement.',
        'not_dismissible' => 'Cette campagne ne peut pas être fermée.',
        'no_acknowledgement' => 'Cette campagne ne demande pas d’accusé.',
        'no_action' => 'Cette campagne n’a pas de bouton d’action.',
        'invalid_interaction' => 'Interaction inconnue.',
    ],

    'filters' => [
        'active_now' => 'Active maintenant',
    ],

    'view' => [
        'overview' => 'Aperçu général',
        'analytics' => 'Statistiques',
        'audit_note' => 'Origine et audit',
        'created_by' => 'Créée par',
        'updated_by' => 'Dernière modification par',
        'published_by' => 'Publiée par',
        'published_at' => 'Publiée le',
        'created_at' => 'Créée le',
        'updated_at' => 'Mise à jour le',
    ],

    'analytics' => [
        'seen_users' => 'Utilisateurs uniques vus',
        'impressions' => 'Impressions',
        'acknowledgements' => 'Accusés de réception',
        'dismissals' => 'Fermetures',
        'clicks' => 'Clics CTA',
        'ctr' => 'Taux de clic (CTR)',
    ],

    'preview' => [
        'action' => 'Aperçu',
        'banner' => 'Aperçu — ce n’est pas un popup réel ; aucune statistique n’est enregistrée',
        'no_tracking_note' => 'Les aperçus n’enregistrent jamais impressions, clics ni accusés.',
        'unsafe_exit_warning' => 'Alerte : cette campagne serait refusée à la publication car elle piège l’utilisateur.',
    ],

    'frontend' => [
        'acknowledge_default' => 'Compris',
        'dismiss' => 'Fermer',
    ],

    'duplicate_suffix' => 'copie',
];
