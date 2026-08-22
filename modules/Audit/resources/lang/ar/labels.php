<?php

declare(strict_types=1);

/*
| تسميات موديول Audit — عربي.
*/

return [

    'nav_group' => 'الحوكمة والأمان',
    'nav_sort' => 'سجل التدقيق',

    'audit_log' => [
        'label' => 'قيدة تدقيق',
        'plural' => 'سجل التدقيق',
        'view_title' => 'عرض قيدة التدقيق',
    ],

    'fields' => [
        'action' => 'الفعل',
        'actor_type' => 'نوع الفاعل',
        'actor' => 'الفاعل',
        'acting_for' => 'بالنيابة عن',
        'auditable_type' => 'نوع السجل',
        'auditable_id' => 'معرّف السجل',
        'old_values' => 'القيم قبل التغيير',
        'new_values' => 'القيم بعد التغيير',
        'reason' => 'السبب',
        'ip_address' => 'عنوان IP',
        'correlation_id' => 'معرّف الارتباط',
        'created_at' => 'وقت القيد',
    ],

    'sections' => [
        'context' => 'سياق الفاعل',
        'subject' => 'موضوع القيد',
        'changes' => 'التغييرات والسبب',
        'metadata' => 'بيانات تقنية',
    ],

    'actor_types' => [
        'user' => 'مستخدم',
        'system' => 'النظام',
        'integration' => 'تكامل خارجي',
    ],

    'actions' => [
        'created' => 'إنشاء',
        'updated' => 'تحديث',
        'deleted' => 'حذف',
        'restored' => 'استرجاع',
        'force_deleted' => 'حذف نهائي',
        'logged_in' => 'تسجيل دخول',
        'logged_out' => 'تسجيل خروج',
        'permission_changed' => 'تغيير صلاحيات',
    ],
];
