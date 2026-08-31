<?php

declare(strict_types=1);

/*
| إعدادات موديول Content — كل أرقام السياسة تعيش هنا لا داخل الكود.
*/

return [
    'reason_max_length' => 1000,

    'uploads' => [
        // الحد الأقصى لحجم الملف المرفوع بالميغابايت.
        'max_size_mb' => env('CONTENT_MAX_SIZE_MB', 100),

        // امتدادات الملفات المسموحة للمواد التعليمية.
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'mp3', 'zip'],

        'accepted_mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
            'video/mp4',
            'audio/mpeg',
            'application/zip',
        ],

        // القرص الافتراضي لتخزين المواد.
        'disk' => env('CONTENT_DISK', 'public'),
    ],
];
