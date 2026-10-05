<?php

declare(strict_types=1);

/*
| Français — validation du module Staff.
*/

return [
    'amount_invalid' => 'Saisissez un montant valide avec au plus deux décimales.',
    'amount_required' => 'Le montant est requis.',
    'availability_time_invalid' => 'L’heure de fin doit être postérieure à l’heure de début.',
    'basis_required' => 'La base du contrat est requise.',
    'contract_period_invalid' => 'La date de fin doit être postérieure à la date de début.',
    'country_invalid' => 'Le pays sélectionné n’est pas disponible.',
    'country_required' => 'Le pays est requis.',
    'decision_required' => 'La décision de congé est requise.',
    'employment_type_invalid' => 'Le type d’emploi sélectionné est invalide.',
    'employment_type_required' => 'Le type d’emploi est requis.',
    'gender_invalid' => 'La valeur du sexe sélectionnée est invalide.',
    'gender_required' => 'Le sexe est requis.',
    'leave_period_invalid' => 'L’heure de fin du congé doit suivre son heure de début.',
    'reason_required' => 'Un motif clair est requis.',
    'region_country_mismatch' => 'La région sélectionnée n’appartient pas au pays sélectionné ou n’est pas disponible.',
    'region_required' => 'La région est requise.',
    'scope_required' => 'La portée du tarif est requise.',
    'staff_code_required' => 'Le code du personnel est requis.',
    'staff_code_unique' => 'Ce code du personnel est déjà attribué à un autre membre.',
    'hire_before_birth_invalid' => 'La date d’embauche doit être postérieure à la date de naissance.',
    'staff_profile_required' => 'Le profil du membre est requis.',
    'ulid' => 'L’identifiant utilisateur est invalide.',
    'user_id_required' => 'Le compte utilisateur est requis.',
    'weekday_invalid' => 'Le jour de la semaine sélectionné est invalide.',
    'full_name_required' => 'Le nom complet est requis.',
    'contact_required' => 'Une adresse e-mail ou un numéro de téléphone est requis.',
    'import_row_failed' => 'Impossible d’importer la ligne ; vérifiez le compte et les champs uniques.',

    'attributes' => [
        'amount' => 'montant',
        'base_amount' => 'montant de base',
        'basis' => 'base du contrat',
        'country' => 'pays',
        'date_of_birth' => 'date de naissance',
        'decision' => 'décision',
        'effective_from' => 'date de début d’effet',
        'effective_to' => 'date de fin d’effet',
        'employment_type' => 'type d’emploi',
        'gender' => 'sexe',
        'end_time' => 'heure de fin',
        'ends_at' => 'heure de fin du congé',
        'reason' => 'motif',
        'region' => 'région',
        'phone' => 'numéro de téléphone',
        'scope' => 'portée du tarif',
        'staff_code' => 'code du personnel',
        'staff_profile' => 'profil du personnel',
        'start_time' => 'heure de début',
        'starts_at' => 'heure de début du congé',
        'user_id' => 'compte utilisateur',
        'weekday' => 'jour de la semaine',
    ],
];
