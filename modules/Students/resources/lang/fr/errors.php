<?php

declare(strict_types=1);

/*
| Français — erreurs du module Students.
| Consommé via __('students::errors.key').
*/

return [
    'already_registered' => 'Cet utilisateur est déjà inscrit comme étudiant.',
    'code_taken' => 'Le code étudiant « :student_code » est déjà utilisé — choisissez-en un autre.',
    'archived_read_only' => 'Un profil étudiant archivé ne peut pas être modifié ; restaurez-le d’abord.',
    'already_archived' => 'Cet étudiant est déjà archivé.',
    'archive_reason_required' => 'Un motif d’archivage est exigé par la politique d’audit.',
    'not_archived' => 'Un étudiant non archivé ne peut pas être restauré.',
    'not_found' => 'Le profil étudiant demandé est introuvable.',
    'registration_invalid_transition' => 'La demande d’inscription ne peut pas passer de « :from » à « :to ».',
    'registration_contact_required' => 'Une adresse e-mail ou un numéro de téléphone est requis.',
    'registration_required_field_missing' => 'Le champ « :field » est requis avant la soumission.',
    'registration_duplicate_blocked' => 'Une demande d’inscription antérieure correspond à ces informations.',
    'registration_user_account_required' => 'La demande doit être liée à un compte utilisateur avant son acceptation.',
    'registration_student_profile_exists' => 'Un profil étudiant est déjà lié à ce compte.',
    'registration_rejection_reason_required' => 'Un motif de rejet est requis.',
    'registration_acceptance_reason_required' => 'Un motif d’acceptation est requis.',
    'registration_form_unavailable' => 'Ce formulaire d’inscription n’est plus disponible.',
    'direct_profile_creation_disabled' => 'Un profil étudiant ne peut pas être créé directement ; acceptez d’abord la demande d’inscription.',
    'registration_not_cleared_for_assignment' => 'La demande d’inscription n’a pas encore été acceptée pour l’affectation.',
    'existing_account_not_found' => 'Le compte sélectionné n’existe pas dans cette organisation.',
    'organization_mismatch' => 'Le profil demandé n’appartient pas à votre organisation.',
    'update_reason_required' => 'La modification d’un profil étudiant exige un motif écrit.',
    'bulk_placement_group_target_required' => 'Choisissez un groupe existant ou saisissez le nom d’un nouveau groupe.',
    'bulk_placement_no_eligible_students' => 'Aucun des élèves sélectionnés n’est éligible au placement dans ce groupe.',
];
