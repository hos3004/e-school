<?php

declare(strict_types=1);

return [
    'participant_not_active' => 'Le participant ne relève pas de cet établissement ou son invitation à la séance n’est plus active.',
    'sheet_no_participants' => "Cette séance n'a aucun élève invité ; il n'y a pas de feuille de présence à enregistrer.",
    'sheet_participant_outside_session' => 'Un des élèves de la feuille ne participe pas à cette séance.',
    'participant_required' => 'L’identifiant du participant est requis.',
    'already_recorded' => 'La présence de ce participant a déjà été enregistrée.',
    'already_confirmed' => 'Cette présence est déjà confirmée.',
    'confirmer_required' => 'L’identifiant de la personne qui confirme est requis.',
    'negative_minutes' => 'Les minutes doivent être positives ou nulles.',
    'invalid_session_duration' => 'La durée de la séance doit être positive.',
    'override_reason_required' => 'La dérogation exige un motif d’au moins :min_chars caractères.',
    'override_no_change' => 'Le nouveau statut doit être différent du statut actuel (:status).',
    'sheet_teacher_not_assigned' => 'Seul l’enseignant affecté à cette séance peut enregistrer ou modifier la présence.',
    'sheet_teacher_not_present' => 'La présence ne peut être modifiée manuellement avant que l’enseignant ait rejoint la salle pendant l’horaire officiel.',
];
