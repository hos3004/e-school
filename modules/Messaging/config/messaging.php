<?php

declare(strict_types=1);

/*
| إعدادات موديول Messaging — التقنية والسياسات الرقمية الخاصة بالمحادثات.
| أي رقم يُستخدم في الكود يُقرأ من هنا عبر config('messaging.*').
*/

return [
    // نافذة تعديل الرسالة بعد إرسالها بالدقائق.
    'edit' => [
        'window_minutes' => 15,
    ],

    // حدود نصية للمحتوى.
    'limits' => [
        'message_body_max' => 4000,
        'wall_post_body_max' => 8000,
        'wall_comment_body_max' => 2000,
        'conversation_subject_max' => 120,
        'max_participants' => 100,
    ],

    // واتساب الوارد: أقصى طول للنص وحجم الوسائط المسموح.
    'whatsapp' => [
        'body_max' => 4096,
        'media_max_items' => 5,
    ],

    /*
     * قناة "راسل الإشراف": أسماء الأدوار التي تصل إليها رسائل المعلم للإشراف.
     * مرحلة أولى بسيطة — دور واحد ثابت. المالك يخطط لاحقًا لفتح هذا لصلاحية
     * قابلة للمنح لأفراد بعينهم بدل الاعتماد على اسم دور، فهذا الإعداد هو
     * نقطة التبديل الوحيدة المطلوبة يوم يحصل ذلك، من غير لمس الكود.
     */
    'supervision' => [
        'recipient_role_names' => ['platform_admin'],
        'conversation_subject' => 'تواصل مع الإشراف',
    ],

    /*
     * صورة واحدة اختيارية لكل منشور حائط — تُخزَّن على قرص خاص (local) لا يُخدَم
     * مباشرة عبر الويب، وتُسلَّم فقط عبر مسار محروس بنفس سياسة عرض المنشور
     * نفسها (canAccessClass)، فلا يصبح رابط صورة الحائط رابطًا عامًا يتداوله أحد.
     */
    'wall' => [
        'attachments' => [
            'disk' => env('WALL_ATTACHMENTS_DISK', 'local'),
            'directory' => 'class-wall',
            'max_size_kilobytes' => (int) env('WALL_ATTACHMENTS_MAX_KB', 8192),
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],
    ],
];
