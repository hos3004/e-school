<?php

declare(strict_types=1);

return [
    'sections' => ['audience' => 'Contexte académique', 'content' => 'Titre et instructions', 'scoring' => 'Notes et tentatives', 'availability' => 'Disponibilité', 'overview' => 'Résumé du test', 'metrics' => 'Indicateurs', 'questions' => 'Banque de questions', 'attempts' => 'Tentatives', 'answers' => 'Réponses', 'audit' => 'Journal d’audit'],
    'actions' => ['edit' => 'Modifier', 'archive' => 'Archiver', 'add_question' => 'Ajouter une question', 'remove_question' => 'Supprimer la question', 'grade' => 'Corriger et valider'],
    'helpers' => ['course_optional' => 'Cours obligatoire pour quiz/examen, facultatif pour placement/réactivation.', 'reason' => 'Le motif est enregistré dans l’audit.', 'question_options' => 'Utilisez des clés uniques a, b, c puis indiquez la bonne clé.', 'score_lock' => 'Le total des questions doit correspondre à la note du test.'],
    'metrics' => ['questions' => 'Questions', 'allocated_score' => 'Points distribués', 'remaining_score' => 'Points restants', 'attempts' => 'Tentatives', 'awaiting_grading' => 'À corriger', 'passed' => 'Réussies', 'failed' => 'Échouées'],
    'hub' => ['history' => 'Opérations et historique', 'question_snapshot' => 'Questions', 'attempt_snapshot' => 'Tentatives', 'audit' => 'Audit', 'no_audit' => 'Aucun changement enregistré.', 'action' => 'Action', 'actor' => 'Acteur', 'changed_at' => 'Date'],
    'empty' => ['questions' => 'Aucune question.', 'attempts' => 'Aucune tentative.'],
];
