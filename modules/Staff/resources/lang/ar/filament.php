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
            'phone' => 'رقم الهاتف',
            'region' => 'المنطقة',
            'specializations' => 'التخصصات',
            'staff_code' => 'الرقم الوظيفي',
            'terminated_at' => 'تاريخ انتهاء الخدمة',
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
];
