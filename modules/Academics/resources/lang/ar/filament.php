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

        'sections' => [
            'identity' => 'تعريف الكورس',
            'delivery' => 'التصنيف وطريقة التقديم',
            'rules' => 'قواعد الإكمال والمتطلبات',
        ],

        'fields' => [
            'level' => 'المستوى',
            'program' => 'البرنامج',
            'organization' => 'المؤسسة',
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'description_ar' => 'الوصف (عربي)',
            'description_en' => 'الوصف (إنجليزي)',
            'total_sessions' => 'عدد الحصص',
            'completion_rules' => 'قواعد الإكمال',
            'prerequisites' => 'المتطلبات السابقة',
            'rule_key' => 'القاعدة',
            'rule_value' => 'القيمة',
            'is_active' => 'نشط',
            'session_mode' => 'نمط الحصة',
            'target_gender' => 'الفئة المستهدفة',
            'inherits_program' => 'يرث من البرنامج',
            'age_from' => 'من عمر',
            'age_to' => 'إلى عمر',
            'age_range' => 'الفئة العمرية',
            'any_age' => 'كل الأعمار',
            'age_from_only' => ':age فأكثر',
            'age_to_only' => 'حتى :age',
            'default_duration_minutes' => 'مدة الحصة (دقائق)',
            'duration_help' => 'تُستعمل كقيمة افتراضية عند جدولة حصص هذا الكورس.',
            'sessions_per_week' => 'حصص في الأسبوع',
        ],

        'filters' => [
            'active' => 'الكورسات النشطة فقط',
            'program' => 'البرنامج',
            'trashed' => 'المؤرشفة',
        ],

        'errors' => [
            'no_organization' => 'حسابك غير مرتبط بمؤسسة، فلا يمكن إنشاء كورس.',
            'level_outside_organization' => 'المستوى المختار لا ينتمي إلى مؤسستك.',
        ],
    ],

    'session_modes' => [
        'individual' => 'فردي',
        'group' => 'جماعي',
        'both' => 'فردي وجماعي',
    ],

    'target_genders' => [
        'male' => 'ذكور',
        'female' => 'إناث',
        'all' => 'الجميع',
    ],
];
