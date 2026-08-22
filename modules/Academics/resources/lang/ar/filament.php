<?php

declare(strict_types=1);

/*
| نصوص واجهة Filament لموديول Academics.
| تُستهلك عبر __('academics::filament.key').
*/

return [
    'group' => 'الأكاديميات',

    'fields' => [
        'created_at' => 'أُنشئ في',
    ],

    'currencies' => [
        'EGP' => 'جنيه مصري',
        'SAR' => 'ريال سعودي',
        'AED' => 'درهم إماراتي',
        'USD' => 'دولار أمريكي',
    ],

    'program' => [
        'label' => 'برنامج',
        'plural' => 'البرامج',

        'fields' => [
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'duration_weeks' => 'المدة بالأسابيع',
            'default_session_minutes' => 'مدة الحصة الافتراضية (دقائق)',
            'default_rate' => 'السعر الافتراضي للحصة',
            'currency' => 'العملة',
            'is_active' => 'نشط',
        ],

        'filters' => [
            'active' => 'البرامج النشطة فقط',
        ],
    ],

    'level' => [
        'label' => 'مستوى',
        'plural' => 'المستويات',

        'fields' => [
            'program' => 'البرنامج',
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'sort_order' => 'ترتيب العرض',
        ],
    ],

    'course' => [
        'label' => 'كورس',
        'plural' => 'الكورسات',

        'fields' => [
            'level' => 'المستوى',
            'organization' => 'المؤسسة',
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'total_sessions' => 'عدد الحصص',
            'completion_rules' => 'قواعد الإكمال',
            'is_active' => 'نشط',
        ],

        'filters' => [
            'active' => 'الكورسات النشطة فقط',
        ],
    ],
];
