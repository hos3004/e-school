<?php

declare(strict_types=1);

/*
| Français — erreurs du module Staff.
*/

return [
    'existing_account_not_found' => 'Le compte sélectionné n’est pas disponible dans cette organisation.',
    'qualification_invalid_course' => 'L’un des cours sélectionnés est inactif ou appartient à une autre organisation.',
    'amount_negative' => 'Le montant ne peut pas être négatif.',
    'availability_approved_not_removable' => 'La disponibilité approuvée est utilisée par la planification ; seul un superviseur peut la retirer.',
    'availability_decision_invalid' => 'La décision de disponibilité sélectionnée est invalide.',
    'availability_invalid_approval_transition' => 'Aucune nouvelle décision ne peut être prise depuis l’état actuel.',
    'availability_time_invalid' => 'L’heure de début doit précéder l’heure de fin.',
    'availability_timezone_invalid' => 'Le fuseau horaire sélectionné n’est pas reconnu.',
    'availability_weekday_invalid' => 'Le jour doit être compris entre le dimanche et le samedi.',
    'availability_overlaps' => 'Une période de disponibilité chevauche cette période le même jour.',
    'contract_base_not_allowed' => 'La base de calcul sélectionnée ne s’applique pas à ce type de contrat.',
    'contract_base_required' => 'Une base de calcul est requise pour ce type de contrat.',
    'contract_overlaps' => 'Un contrat en cours chevauche déjà cette période.',
    'contract_period_invalid' => 'La date de fin ne peut pas précéder la date de début.',
    'leave_overlaps_approved' => 'Un congé approuvé chevauche déjà cette période.',
    'leave_period_invalid' => 'La date de fin du congé ne peut pas précéder sa date de début.',
    'leave_transition_forbidden' => 'Le congé ne peut pas passer à cet état depuis son état actuel.',
    'profile_already_exists' => 'Ce compte possède déjà un profil du personnel.',
    'profile_already_terminated' => 'Ce membre du personnel a déjà été licencié.',
    'rate_overlaps' => 'Un tarif en cours chevauche déjà cette période pour la même portée.',
    'rate_scope_course_required' => 'Un cours doit être sélectionné lorsque la portée du tarif est un cours.',
    'rate_scope_program_required' => 'Un programme doit être sélectionné lorsque la portée du tarif est un programme.',
    'update_reason_required' => 'La modification d’un profil du personnel exige un motif écrit.',
    'organization_mismatch' => 'Le profil demandé n’appartient pas à votre organisation.',
    'archived_read_only' => 'Un profil archivé ne peut pas être modifié ; restaurez-le d’abord.',
    'revocation_reason_required' => 'La révocation d’une qualification exige un motif écrit.',
    'qualification_already_revoked' => 'Cette qualification de cours est déjà révoquée.',
    'qualification_active_assignment' => 'La qualification ne peut pas être révoquée tant qu’une affectation active existe ; terminez d’abord l’affectation.',
];
