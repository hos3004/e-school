<?php

declare(strict_types=1);

return [
    'id' => 'Identifiant', 'organization_id' => 'Organisation', 'user_id' => 'Utilisateur',
    'category' => 'Catégorie', 'channel' => 'Canal', 'locale' => 'Langue du message',
    'event_name' => 'Nom de l’événement', 'event_id' => 'Identifiant de l’événement',
    'correlation_id' => 'Identifiant de corrélation', 'subject' => 'Objet', 'body' => 'Message',
    'payload' => 'Données supplémentaires', 'idempotency_key' => 'Clé d’idempotence',
    'scheduled_for' => 'Planifiée pour', 'status' => 'Statut', 'attempts' => 'Tentatives',
    'last_error' => 'Dernière erreur', 'sent_at' => 'Envoyée le',
    'external_message_id' => 'Identifiant chez le fournisseur', 'provider_status' => 'Statut du fournisseur',
    'failure_reason' => 'Motif de l’échec', 'read_at' => 'Lue le', 'read_status' => 'Statut de lecture',
    'last_manual_retry_by' => 'Dernier renvoi par', 'last_manual_retry_at' => 'Dernier renvoi manuel le',
    'created_at' => 'Créée le', 'updated_at' => 'Modifiée le', 'enabled' => 'Activé', 'reason' => 'Motif',
    'attempt_number' => 'Numéro de tentative', 'attempted_at' => 'Tentative le',
    'provider_response' => 'Réponse du fournisseur', 'error' => 'Erreur', 'succeeded' => 'Réussie',
    'routing' => 'Routage', 'content' => 'Contenu', 'dispatching' => 'Livraison',
    'is_active' => 'Actif', 'parameters' => 'Paramètres',
    'provider_template_name' => 'Nom du modèle fournisseur', 'scope' => 'Portée',
    'recipient' => 'Destinataire', 'retry_reason' => 'Motif du renvoi', 'cancel_reason' => 'Motif de l’annulation',
    'attempts_history' => 'Historique des tentatives de livraison', 'result' => 'Résultat',
    'audit_history' => 'Historique des décisions et de l’audit', 'action' => 'Action', 'actor' => 'Effectuée par',
    'recipient_type' => 'Type de destinataire', 'recipient_count' => 'Nombre de destinataires',
    'preview' => 'Aperçu de la notification',
    'recipient_types' => ['student' => 'Élève', 'teacher' => 'Enseignant', 'group' => 'Groupe'],
];
