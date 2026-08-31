<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Registration forms',
    'model_label' => 'Registration form',
    'plural_model_label' => 'Registration forms',
    'sections' => [
        'identity' => 'Form identity and publishing',
        'questions' => 'Form questions',
        'questions_help' => 'Core student details are included automatically. Add and drag program-specific questions here.',
    ],
    'fields' => [
        'title' => 'Form name',
        'title_ar' => 'Form name (Arabic)',
        'title_en' => 'Form name (English)',
        'title_fr' => 'Form name (French)',
        'slug' => 'Link slug',
        'slug_help' => 'Lowercase Latin letters, numbers, and hyphens only; for example kids-coding.',
        'description_ar' => 'Description (Arabic)',
        'description_en' => 'Description (English)',
        'description_fr' => 'Description (French)',
        'is_active' => 'Published and accepting registrations',
        'is_active_help' => 'Disabling preserves application history while stopping new submissions.',
        'questions_count' => 'Questions',
        'updated_at' => 'Last updated',
        'change_reason' => 'Reason for creating or changing',
        'change_reason_help' => 'The reason and before/after values are written to the audit log.',
    ],
    'filters' => ['is_active' => 'Publishing status'],
    'actions' => [
        'add_question' => 'Add question',
        'open_public_form' => 'Open public form',
    ],
];
