<?php

declare(strict_types=1);

/*
| Français — messages de validation du module Students, consommés par les FormRequests.
*/

return [
    'registration_offering_invalid' => 'Le cours sélectionné n’est pas disponible dans cette organisation et ce programme.',
    'registration_answers_invalid' => 'Les réponses contiennent une question qui n’appartient pas à ce formulaire.',
    'registration_answer_required' => 'Cette question est obligatoire.',
    'registration_answer_invalid' => 'La valeur choisie n’est pas disponible pour cette question.',

    'user_already_student' => 'Cet utilisateur est déjà lié à un profil étudiant.',
    'code_taken' => 'Le code étudiant est déjà utilisé — il doit être unique.',
    'birth_before_today' => 'La date de naissance doit être dans le passé.',
    'minimum_self_registration_age' => 'L’inscription autonome est possible à partir de :age ans.',

    'reason_required' => 'Le motif d’archivage est requis.',
    'country_invalid' => 'Le pays sélectionné n’est pas disponible.',
    'region_not_in_country' => 'La région sélectionnée n’appartient pas au pays sélectionné.',
    'full_name_required' => 'Le nom complet est requis.',
    'date_of_birth_required' => 'Une date de naissance valide est requise.',
    'gender_invalid' => 'Le sexe sélectionné est invalide.',
    'contact_required' => 'Une adresse e-mail ou un numéro de téléphone est requis.',
    'import_row_failed' => 'La ligne n’a pas pu être importée ; vérifiez son compte et ses champs uniques.',
];
