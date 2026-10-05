<?php

declare(strict_types=1);

return [
    'invalid_transition' => 'Transition impossible de « :from » vers « :to ».',
    'duplicate_external_id' => 'Un enregistrement avec cet identifiant existe déjà chez :provider.',
    'archive_driver_missing' => 'Aucun pilote d’archivage n’est configuré.',
    'already_deleted' => 'Cet enregistrement est déjà suspendu.',
    'delete_expired' => 'Un enregistrement expiré ne peut pas être suspendu (:status).',
    'deleter_required' => 'L’utilisateur effectuant la suspension est requis.',
    'deletion_reason_required' => 'Un motif documenté est requis.',
    'not_watchable' => 'Cet enregistrement ne peut pas être visionné (:status).',
    'download_not_allowed' => 'Le téléchargement n’est pas autorisé.',
    'grant_target_invalid' => 'Choisissez un seul bénéficiaire : utilisateur ou groupe.',
    'grant_reason_required' => 'Un motif documenté est requis pour accorder l’accès.',
    'grant_expiry_invalid' => 'La date d’expiration doit être future.',
    'grant_status_invalid' => 'L’accès ne peut pas être accordé dans l’état :status.',
    'granter_required' => 'L’auteur doit être un utilisateur valide de l’organisation.',
    'grant_target_not_found' => 'Le bénéficiaire n’existe pas dans cette organisation.',
    'grant_duplicate' => 'Une autorisation active existe déjà pour ce bénéficiaire.',
    'revocation_context_required' => 'La révocation exige un auteur et un motif.',
    'grant_not_found' => 'Cette autorisation n’appartient pas à l’enregistrement.',
    'grant_already_revoked' => 'Cette autorisation est déjà révoquée.',
    'context_invalid' => 'La séance ou la classe n’appartient pas à l’organisation.',
];
