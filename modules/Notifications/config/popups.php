<?php

declare(strict_types=1);

/*
| إعدادات الحملات المنبثقة (Popup Campaigns) — كل أرقام السياسة هنا لا في الكود.
*/

return [

    /*
    | نطاق الأولوية المسموح به للأدمن (أعلى رقم = أولوية أعلى).
    */
    'priority' => [
        'min' => 1,
        'max' => 10,
        'default' => 5,
    ],

    /*
    | حد أقصى للحملات المرشحة التي تُقيَّم لطلب واحد (حماية أداء).
    */
    'max_candidates_per_request' => 25,

    /*
    | حدود نصوص المحتوى الآمنة — نص عادي متعدد الأسطر، بلا HTML.
    */
    'content' => [
        'title_max' => 120,
        'body_max' => 2000,
        'acknowledgement_label_max' => 60,
        'action_label_max' => 60,
        'internal_name_max' => 120,
        'page_key_max' => 64,
        'action_target_max' => 500,
        'link_text_max' => 60,
        'link_url_max' => 500,
        'max_links' => 10,
    ],

    /*
    | الروابط الخارجية: HTTPS إلزامي، ولا مسار آخر للـCTA الخارجي.
    */
    'external_action' => [
        'allowed_schemes' => ['https'],
    ],

    /*
    | حدود الإغلاق التلقائي (auto_dismiss_seconds) — نطاق معقول يمنع قيمًا
    | متطرفة (0 يعني بلا معنى، وقيمة كبيرة جدًا تعادل عمليًا "بلا إغلاق تلقائي").
    */
    'auto_dismiss' => [
        'min_seconds' => 3,
        'max_seconds' => 300,
    ],

    /*
    | مرفقات الميديا — القرص والمجلد من الإعداد دائمًا، لا من مدخلات العميل.
    | نفس نمط modules/Messaging/config/messaging.php → wall.attachments.
    */
    'attachments' => [
        'disk' => env('POPUP_ATTACHMENTS_DISK', 'local'),
        'directory' => 'popups',

        'image' => [
            'max_size_kilobytes' => (int) env('POPUP_IMAGE_MAX_KB', 8192),
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
        ],

        'video' => [
            'max_size_kilobytes' => (int) env('POPUP_VIDEO_MAX_KB', 51200),
            'allowed_mime_types' => [
                'video/mp4',
                'video/webm',
            ],
        ],

        'audio' => [
            'max_size_kilobytes' => (int) env('POPUP_AUDIO_MAX_KB', 20480),
            'allowed_mime_types' => [
                'audio/mpeg',
                'audio/mp4',
                'audio/ogg',
            ],
        ],

        'file' => [
            'max_size_kilobytes' => (int) env('POPUP_FILE_MAX_KB', 20480),
            'allowed_mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
            ],
        ],

        /*
        | مدة صلاحية رابط تنزيل الملفات الموقَّع (signed URL) — دقائق قليلة
        | فقط. الرابط يُستهلك مرة عبر مدير تنزيل النظام على الموبايل بلا
        | ترويسة Authorization، لذا يجب أن يبقى قصير العمر.
        */
        'download_link_ttl_minutes' => 5,
    ],
];
