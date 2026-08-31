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
        'reason' => 'سبب التغيير',
        'reason_help' => 'يظهر السبب في سجل التدقيق؛ اكتب وصفًا واضحًا للقرار.',
    ],

    'sections' => ['audit' => 'سبب وتدقيق العملية'],
    'filters' => ['trashed' => 'المؤرشفة'],
    'hub' => [
        'empty' => 'لا توجد بيانات مسجلة بعد.',
        'unrestricted' => 'بلا قيود',
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

        'sections' => [
            'identity' => 'هوية البرنامج',
            'delivery' => 'النطاق والمدة والأهداف',
            'pricing' => 'الحصة والتسعير الافتراضي',
            'eligibility' => 'شروط القبول والمطابقة',
            'eligibility_help' => 'القوائم الفارغة تعني عدم تقييد القبول بهذا الحقل.',
        ],

        'hub' => [
            'title' => 'مركز البرنامج',
            'overview' => 'ملخص البرنامج',
            'levels' => 'المستويات',
            'courses' => 'الكورسات',
            'eligibility' => 'الأهلية والقبول',
            'categories' => 'التصنيفات',
        ],

        'fields' => [
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'description_ar' => 'الوصف (عربي)',
            'description_en' => 'الوصف (إنجليزي)',
            'duration_weeks' => 'المدة بالأسابيع',
            'default_session_minutes' => 'مدة الحصة الافتراضية (دقائق)',
            'default_rate' => 'السعر الافتراضي للحصة',
            'currency' => 'العملة',
            'is_active' => 'نشط',
            'sort_order' => 'ترتيب العرض',
            'program_type' => 'نوع البرنامج',
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'target_gender' => 'الفئة المستهدفة',
            'age_from' => 'من عمر',
            'age_to' => 'إلى عمر',
            'age_range' => 'الفئة العمرية',
            'objectives' => 'أهداف البرنامج',
            'objective_key' => 'رمز الهدف',
            'objective_value' => 'وصف الهدف',
            'language' => 'لغة التقديم',
            'rate_minor_units_help' => 'أدخل القيمة بأصغر وحدة للعملة (مثل القرش/السنت).',
            'countries' => 'الدول المسموح بها',
            'regions' => 'المناطق المسموح بها',
            'teacher_gender_rule' => 'قاعدة مطابقة جنس المعلم',
            'manual_approval_required' => 'يتطلب موافقة يدوية',
            'requires_individual_sessions' => 'يتطلب حصصًا فردية',
            'levels_count' => 'عدد المستويات',
            'courses_count' => 'عدد الكورسات',
            'active_courses_count' => 'الكورسات النشطة',
        ],

        'filters' => [
            'active' => 'البرامج النشطة فقط',
        ],
    ],

    'level' => [
        'label' => 'مستوى',
        'plural' => 'المستويات',

        'sections' => ['identity' => 'تعريف المستوى'],
        'hub' => [
            'title' => 'مركز المستوى',
            'overview' => 'ملخص المستوى',
            'courses' => 'كورساته',
        ],

        'fields' => [
            'program' => 'البرنامج',
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'sort_order' => 'ترتيب العرض',
            'courses_count' => 'عدد الكورسات',
        ],
    ],

    'course' => [
        'label' => 'كورس',
        'plural' => 'الكورسات',

        'hub' => [
            'title' => 'مركز الكورس',
            'overview' => 'ملخص الكورس',
            'description' => 'الوصف',
            'rules' => 'قواعد الإكمال والمتطلبات',
            'categories' => 'التصنيفات',
        ],

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
            'categories' => 'التصنيفات',
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

    'category' => [
        'label' => 'التصنيف',
        'fields' => [
            'code' => 'الكود',
            'name' => 'الاسم',
            'name_ar' => 'الاسم (عربي)',
            'name_en' => 'الاسم (إنجليزي)',
            'parent' => 'التصنيف الأب',
            'scope' => 'النطاق',
            'sort_order' => 'ترتيب العرض',
            'is_active' => 'نشط',
        ],
    ],

    'actions' => [
        'create_level' => 'إضافة مستوى',
        'level_created' => 'تم إنشاء المستوى.',
        'create_category' => 'إضافة تصنيف',
        'update_category' => 'تعديل تصنيف',
        'archive_category' => 'أرشفة تصنيف',
        'category_created' => 'تم إنشاء التصنيف.',
        'category_updated' => 'تم تحديث التصنيف.',
        'category_archived' => 'تمت أرشفة التصنيف.',
        'activate' => 'تفعيل',
        'deactivate' => 'تعطيل',
        'status_updated' => 'تم تحديث الحالة.',
        'archive' => 'أرشفة',
    ],

    'program_types' => [
        'fixed_duration' => 'محدد المدة',
        'ongoing' => 'مستمر',
    ],

    'teacher_gender_rules' => [
        'any' => 'أي معلم مؤهل',
        'same' => 'من الجنس نفسه',
        'opposite' => 'من الجنس المقابل',
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
