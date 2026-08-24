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
            'system' => 'دور نظامي',
            'permissions' => 'الصلاحيات الممنوحة',
            'user_id' => 'معرّف المستخدم',
        ],
        'actions' => [
            'assign_user' => 'إسناد لمستخدم',
            'revoke_user' => 'سحب من مستخدم',
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
