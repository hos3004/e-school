<?php

declare(strict_types=1);

/*
 * إعدادات مركزية لصور المستخدمين (Avatar).
 * المصدر الوحيد للحقل هو users.avatar_path — يُخزَّن المسار فقط،
 * والملف يعيش على القرص المُهيَّأ هنا. لا Base64 ولا Binary في قاعدة البيانات.
 */

return [

    // القرص الذي تُخزَّن عليه الصور (خاص أو عام حسب بيئة النشر).
    'disk' => env('AVATARS_DISK', config('filesystems.default')),

    // المجلد الجذر؛ يُنظَّم تحته بحسب المؤسسة ثم المستخدم.
    'directory' => 'avatars',

    // مجلد مؤقت يستقبله مكوّن الرفع قبل اعتماد العملية.
    'tmp_directory' => 'avatars/tmp',

    // الحد الأقصى لحجم الملف بالكيلوبايت.
    'max_size_kb' => (int) env('AVATARS_MAX_SIZE_KB', 2048),

    // الأبعاد القصوى المسموحة (بكسل) — التحقق يتم على الخادم.
    'max_width' => (int) env('AVATARS_MAX_WIDTH', 2048),
    'max_height' => (int) env('AVATARS_MAX_HEIGHT', 2048),

    /*
     * أنواع MIME المسموحة فقط: JPEG / PNG / WebP.
     * SVG ممنوع من الرفع نهائيًا لأنه ناقل لهجمات XSS.
     */
    'accepted_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    // الامتدادات المقابلة للأنواع أعلاه عند تسمية الملف النهائي.
    'extension_by_mime' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    /*
     * الصور الافتراضية المحلية — أصول ثابتة داخل المشروع، لا روابط خارجية،
     * ولا تُحفظ لكل مستخدم في قاعدة البيانات.
     */
    'defaults' => [
        'male' => 'images/avatars/default-male.png',
        'female' => 'images/avatars/default-female.png',
        'neutral' => 'images/avatars/default-neutral.png',
    ],
];
