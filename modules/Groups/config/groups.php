<?php

declare(strict_types=1);

return [
    'capacity' => [
        'minimum' => 1,
        'maximum' => 25,
    ],

    /*
     * شروط تفعيل المجموعة.
     *
     * المجموعة تُنشأ «قيد التخطيط» بالحد الأدنى من البيانات، ولا تُفعَّل قبل
     * استيفاء هذه الشروط. الشرط سياسة مدرسة لا قاعدة تقنية، فمكانه هنا لا
     * داخل ActivateGroupAction.
     */
    'activation' => [
        // لا تفعيل بلا معلم مُسند وإسناده ما زال مفتوحًا.
        'requires_teacher' => (bool) env('GROUPS_ACTIVATION_REQUIRES_TEACHER', true),

        // لا تفعيل بلا سعة معلنة وتاريخ بداية.
        'requires_capacity' => true,
        'requires_start_date' => true,
    ],
];
