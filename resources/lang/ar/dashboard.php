<?php

declare(strict_types=1);

return [
    // أقسام الشريط الجانبي الخمسة. هذه القيم هي المرجع الوحيد لأسماء المجموعات،
    // وكل ملف ترجمة موديول يعيد النص نفسه حرفيًا. التطابق يحرسه
    // tests/Feature/AdminNavigationStructureTest.php فلا تنفرط المجموعات ثانيةً.
    'navigation' => [
        'daily' => 'اليوم الدراسي',
        'people' => 'الأشخاص',
        'learning' => 'التعلّم',
        'communication' => 'التواصل',
        'insights' => 'التقارير والإدارة',
    ],

    'common' => [
        'dash' => '—',
    ],

    'stats' => [
        'students' => [
            'label' => 'الطلاب',
            'description' => ':active نشط · :frozen مجمَّد',
        ],
        'teachers' => [
            'label' => 'المعلمون والطاقم',
            'description' => 'إجمالي المعلمين والكادر المعاون',
        ],
        'programs' => [
            'label' => 'البرامج الدراسية',
            'description' => 'البرامج والمناهج الفعالة',
        ],
        'sessions_today' => [
            'label' => 'حصص اليوم',
            'description' => ':done مكتملة · :upcoming قادمة',
        ],
        'attendance_rate' => [
            'label' => 'نسبة الحضور هذا الشهر',
            'description' => ':absent غياب من :total سجل',
            'empty_description' => 'لا سجلات بعد',
        ],
        'payroll' => [
            'label' => 'مستحقات الشهر',
            'currency' => 'ج.م',
            'deferred_description' => ':count قيدة مؤجَّلة',
            'no_deferred_description' => 'لا قيود مؤجَّلة',
        ],
    ],

    'needs_attention' => [
        'title' => 'يحتاج انتباهك',
        'subtitle' => 'بنود تنتظر إجراءً منك الآن',
        'empty' => 'لا شيء معلّق — كل البنود التشغيلية مغلقة.',
        'items' => [
            'postponements_pending' => 'طلبات تأجيل تنتظر ردًا',
            'postponements_expired' => 'طلبات تأجيل انقضت مهلتها',
            'sessions_awaiting_review' => 'حصص تنتظر اعتماد الحضور',
            'registrations_submitted' => 'تسجيلات بانتظار المراجعة',
            'enrollments_frozen' => 'قيود مجمَّدة تأديبيًا',
            'reactivations_pending' => 'طلبات فك تجميد معلّقة',
            'availability_unapproved' => 'إتاحة معلمين غير معتمدة',
            'payroll_adjustments_pending' => 'تسويات مالية تنتظر الاعتماد',
            'notifications_failed' => 'إشعارات فشل إرسالها',
        ],
    ],

    'sessions_trend' => [
        'heading' => 'الحصص خلال آخر أربعة أسابيع',
        'dataset_held' => 'أُقيمت',
        'dataset_missed' => 'لم تُقَم',
    ],

    'upcoming_sessions' => [
        'title' => 'أقرب الحصص القادمة',
        'subtitle' => 'أقرب عشر حصص مجدولة لم تبدأ بعد.',
        'empty' => 'لا توجد حصص مجدولة قادمة.',
        'columns' => [
            'start_at' => 'وقت البداية',
            'group' => 'المجموعة',
            'teacher' => 'المعلم',
            'actions' => 'عرض',
        ],
        'view_session' => 'فتح الحصة في لوحة الحصص',
    ],

    'quick_actions' => [
        'title' => 'إجراءات سريعة',
        'new_student' => 'طالب جديد',
        'new_program' => 'برنامج جديد',
        'new_group' => 'مجموعة جديدة',
        'sessions' => 'الحصص',
    ],
];
