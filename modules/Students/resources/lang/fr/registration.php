<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Demandes d’inscription',
    'model_label' => 'Demande d’inscription',
    'plural_model_label' => 'Demandes d’inscription',
    'status' => [
        'draft' => 'Brouillon',
        'submitted' => 'Soumise',
        'under_review' => 'En cours d’examen',
        'accepted' => 'Acceptée',
        'rejected' => 'Rejetée',
        'waiting_assignment' => 'En attente d’affectation',
        'assigned' => 'Affectée',
    ],
    'actions' => [
        'submit' => 'Soumettre la demande',
        'review' => 'Commencer l’examen',
        'accept' => 'Accepter la demande',
        'reject' => 'Rejeter la demande',
        'reject_heading' => 'Rejeter la demande d’inscription',
        'reject_description' => 'Le motif du rejet est conservé avec la décision d’examen.',
    ],
    'messages' => [
        'submitted' => 'La demande d’inscription a été soumise.',
        'under_review' => 'La demande est maintenant en cours d’examen.',
        'accepted' => 'La demande a été acceptée et la fiche élève créée.',
        'rejected' => 'La demande d’inscription a été rejetée.',
        'assigned' => 'L’élève a été placé dans un groupe.',
    ],
    'filters' => [
        'registration_form' => 'Source / formulaire d’inscription',
        'registration_form_unknown' => 'Inscription interne ou historique',
        'status' => 'Statut',
        'country' => 'Pays',
        'region' => 'Région',
        'language' => 'Langue préférée',
        'age_range' => 'Tranche d’âge',
        'age_from' => 'À partir de',
        'age_to' => 'Jusqu’à',
        'age_indicator' => 'Âge de :from à :to',
        'registered_at' => 'Date d’inscription',
        'registered_from' => 'À partir du',
        'registered_until' => 'Jusqu’au',
        'value_from' => 'De',
        'value_until' => 'À',
    ],
    'duplicate' => 'Doublon potentiel',
    'duplicate_yes' => 'Oui',
    'duplicate_no' => 'Non',
];
