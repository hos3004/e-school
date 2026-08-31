<?php

declare(strict_types=1);

/* تسميات لوحة Filament — تُستهلك عبر __('accesscontrol::filament.*'). */

return [

    'group' => 'التحكم بالوصول',

    'fields' => [
        'created_at' => 'أُنشئ في',
    ],

    'role' => [
        'label' => 'دور',
        'plural' => 'الأدوار',
        'fields' => [
            'name' => 'اسم الدور',
            'guard' => 'الحارس',
            'organization' => 'المنظمة',
            'scope' => 'النطاق',
            'scope_global' => 'دور عام',
            'scope_organization' => 'خاص بالمؤسسة',
            'system' => 'دور نظامي',
            'permissions' => 'الصلاحيات الممنوحة',
            'user_id' => 'حساب المستخدم',
            'reason' => 'سبب تغيير الصلاحية',
        ],
        'actions' => [
            'assign_user' => 'إسناد لمستخدم',
            'revoke_user' => 'سحب من مستخدم',
            'user_search_help' => 'ابحث بالاسم أو اسم المستخدم أو البريد أو الهاتف داخل المؤسسة الحالية.',
            'assigned' => 'تم إسناد الدور وتسجيل سبب التغيير.',
            'revoked' => 'تم سحب الدور وتسجيل سبب التغيير.',
        ],
        'filters' => [
            'system' => 'الأدوار النظامية فقط',
        ],
    ],

    'permission' => [
        'label' => 'صلاحية',
        'plural' => 'الصلاحيات',
        'fields' => [
            'name' => 'اسم الصلاحية',
            'guard' => 'الحارس',
            'module' => 'الموديول',
        ],
    ],
];
