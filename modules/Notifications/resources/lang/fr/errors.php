<?php

declare(strict_types=1);

return [
    'channel_disabled' => 'Le canal « :channel » n’est pas activé.',
    'not_cancellable' => 'Une notification au statut « :status » ne peut pas être annulée.',
    'not_retryable' => 'Une notification au statut « :status » ne peut pas être renvoyée.',
    'failure_not_retryable' => 'Cet échec est permanent ; un administrateur peut renvoyer le message après correction.',
    'manual_retry_actor_required' => 'L’administrateur demandant le renvoi doit être identifié.',
    'manual_retry_reason_required' => 'Un motif clair est requis pour le renvoi manuel.',
    'cancel_reason_required' => 'Un motif clair est requis pour annuler la notification.',
    'not_readable' => 'Seule une notification interne livrée et appartenant à l’utilisateur peut être marquée comme lue.',
    'not_dispatchable' => 'L’envoi ne peut pas commencer depuis le statut « :status ».',
    'already_claimed' => 'La notification est déjà réservée par un autre expéditeur.',
    'attempt_not_recordable' => 'Une tentative ne peut pas être enregistrée au statut « :status ».',
    'invalid_status_transition' => 'Le passage du statut « :from » à « :to » n’est pas autorisé.',
    'category_unknown' => 'La catégorie « :category » est inconnue.',
    'event_id_required' => 'L’identifiant de l’événement source est requis.',
    'gateway_unconfigured' => 'Aucune passerelle n’est configurée pour le canal « :channel ».',
    'gateway_channel_mismatch' => 'Cette passerelle attend « :expected » et ne peut pas livrer « :actual ».',
    'template_missing' => 'Aucun modèle actif pour « :event », le canal « :channel » et la langue « :locale ».',
    'template_parameter_missing' => 'Le paramètre « :parameter » manque dans les données de « :event ».',
    'email_recipient_invalid' => 'Le destinataire ne dispose pas d’une adresse e-mail valide.',
    'mail_transport_failed' => 'Le transport e-mail est temporairement indisponible.',
    'manual_recipient_not_found' => 'Le destinataire est absent ou inactif dans votre organisation.',
    'manual_empty_audience' => 'Le groupe ne contient aucun destinataire actif dans l’organisation.',
    'manual_fields_required' => 'L’objet, le message et le motif sont obligatoires.',
    'manual_request_invalid' => 'L’identifiant de la demande est invalide. Rouvrez le formulaire.',
];
