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
    ],

    /*
    | الروابط الخارجية: HTTPS إلزامي، ولا مسار آخر للـCTA الخارجي.
    */
    'external_action' => [
        'allowed_schemes' => ['https'],
    ],
];
