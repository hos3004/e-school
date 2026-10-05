<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Registration questions',
    'model_label' => 'Registration question',
    'plural_model_label' => 'Registration questions',

    'fields' => [
        'registration_form' => 'Registration form',
        'question_ar' => 'Question text (Arabic)',
        'question_en' => 'Question text (English)',
        'question_fr' => 'Question text (French)',
        'type' => 'Question type',
        'options' => 'Options',
        'is_required' => 'Required answer',
        'is_active' => 'Active',
        'sort_order' => 'Display order',
        'is_filterable' => 'Available as a filter',
        'is_filterable_help' => 'Shows the question as a filter on the registrations screen. Single-choice and number questions only.',
    ],

    'types' => [
        'text' => 'Short text',
        'textarea' => 'Long text',
        'select' => 'Choice list',
        'radio' => 'Visible single choice',
        'checkbox' => 'Multiple choices',
        'number' => 'Number',
    ],

    'filters' => [
        'is_active' => 'Active',
    ],

    'messages' => [
        'deleted' => 'Question deleted.',
    ],

    'answers' => [
        'section' => 'Evaluation answers',
        'question' => 'Question',
        'answer' => 'Answer',
        'empty' => 'No evaluation answers on this application.',
    ],
];
