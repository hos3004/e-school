<?php

declare(strict_types=1);

/*
| تسميات لوحة الإدارة (Filament) لموديول Guardians.
*/

return [
    'navigation_group' => 'الأسر والأوصياء',

    'common' => [
        'id' => 'المعرّف',
        'created_at' => 'أُنشئ في',
        'archived_at' => 'أُرشف في',
        'not_archived' => 'غير مؤرشف',
    ],

    'profile' => [
        'model_label' => 'ملف وصي',
        'plural_label' => 'ملفات الأوصياء',
        'fields' => [
            'user_id' => 'حساب المستخدم',
            'national_id_last4' => 'آخر 4 أرقام من الرقم القومي',
            'occupation' => 'المهنة',
            'preferred_contact_channel' => 'قناة التواصل المفضّلة',
            'links_count' => 'عدد الروابط',
        ],
        'filters' => [
            'archived' => 'مؤرشف؟',
        ],
    ],

    'link' => [
        'model_label' => 'رابط وصاية',
        'plural_label' => 'روابط الأوصياء',
        'fields' => [
            'guardian' => 'الوصي',
            'student' => 'الطالب',
            'relationship' => 'صلة القرابة',
            'is_primary' => 'واصٍ أساسي',
            'can_act_for' => 'يحق الوساطة',
            'visible_sections' => 'الأقسام المرئية',
            'verified_at' => 'تاريخ التوثيق',
        ],
        'unverified' => 'غير موثّق',
        'filters' => [
            'verified' => 'موثّق؟',
        ],
    ],
];
