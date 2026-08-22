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
];
