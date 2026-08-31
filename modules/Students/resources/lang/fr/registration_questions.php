<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Questions d’inscription',
    'model_label' => 'Question d’inscription',
    'plural_model_label' => 'Questions d’inscription',

    'fields' => [
        'registration_form' => 'Formulaire d’inscription',
        'question_ar' => 'Texte de la question (arabe)',
        'question_en' => 'Texte de la question (anglais)',
        'question_fr' => 'Texte de la question (français)',
        'type' => 'Type de question',
        'options' => 'Options',
        'is_required' => 'Réponse obligatoire',
        'is_active' => 'Active',
        'sort_order' => 'Ordre d’affichage',
        'is_filterable' => 'Disponible comme filtre',
        'is_filterable_help' => 'Affiche la question comme filtre. Questions à choix unique et numériques uniquement.',
    ],

    'types' => [
        'text' => 'Texte court',
        'textarea' => 'Texte long',
        'select' => 'Liste de choix',
        'radio' => 'Choix unique visible',
        'checkbox' => 'Choix multiples',
        'number' => 'Nombre',
    ],

    'filters' => [
        'is_active' => 'Active',
    ],

    'messages' => [
        'deleted' => 'Question supprimée.',
    ],

    'answers' => [
        'section' => 'Réponses d’évaluation',
        'question' => 'Question',
        'answer' => 'Réponse',
        'empty' => 'Aucune réponse d’évaluation sur cette demande.',
    ],
];
