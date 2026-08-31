<?php

declare(strict_types=1);

return [
    'navigation_group' => 'الموظفون',
    'common' => [
        'active' => 'على رأس العمل',
    ],
    'profile' => [
        'model_label' => 'ملف موظف',
        'plural_label' => 'ملفات الموظفين',
        'fields' => [
            'bio' => 'النبذة التعريفية',
            'country' => 'الدولة',
            'date_of_birth' => 'تاريخ الميلاد',
            'employment_type' => 'نوع التوظيف',
            'gender' => 'الجنس',
            'hired_at' => 'تاريخ التعيين',
            'name' => 'الاسم',
            'phone' => 'رقم الهاتف',
            'region' => 'المنطقة',
            'specializations' => 'التخصصات',
            'staff_code' => 'الرقم الوظيفي',
            'terminated_at' => 'تاريخ انتهاء الخدمة',
            'reason' => 'سبب التعديل',
            'reason_help' => 'سبب إداري واضح يظهر في سجل التدقيق ولا يُخزَّن مع الملف.',
        ],
        'resources' => [
            'actions' => [
                'edit' => 'تعديل الملف',
            ],
        ],
        'filters' => [
            'active' => 'على رأس العمل',
            'country' => 'الدولة',
            'region' => 'المنطقة',
        ],
        'gender_options' => [
            'female' => 'أنثى',
            'male' => 'ذكر',
        ],
    ],
    'teachers' => [
        'label' => 'المعلمون',
        'title' => 'دليل المعلمين التشغيلي',
        'description' => 'واجهة متخصصة للمعلمين — بيانات مجمّعة من الأنظمة الفعلية، والعمليات تُدار من مركز عمليات المعلم.',
        'open_hub' => 'مركز عمليات المعلم',
        'edit' => 'تعديل الملف',
        'fields' => [
            'avatar' => 'الصورة',
            'name' => 'الاسم',
            'account_status' => 'حالة الحساب',
            'qualified_courses' => 'الكورسات المؤهل لها',
            'active_groups' => 'المجموعات النشطة',
            'upcoming_sessions' => 'الحصص القادمة',
            'completed_this_month' => 'حصص هذا الشهر (مكتملة)',
            'cancelled_this_month' => 'ملغاة هذا الشهر',
            'availability' => 'التوافر',
        ],
        'filters' => [
            'qualified_course' => 'الكورس المؤهل له',
            'group' => 'المجموعة',
        ],
    ],
];
