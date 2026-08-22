<?php

declare(strict_types=1);

/*
| إعدادات موديول Content — كل أرقام السياسة تعيش هنا لا داخل الكود.
*/

return [
    'uploads' => [
        // الحد الأقصى لحجم الملف المرفوع بالميغابايت.
        'max_size_mb' => env('CONTENT_MAX_SIZE_MB', 100),

        // امتدادات الملفات المسموحة للمواد التعليمية.
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'mp3', 'zip'],

        // القرص الافتراضي لتخزين المواد.
        'disk' => env('CONTENT_DISK', 'public'),
    ],
];
