<?php

declare(strict_types=1);

/*
| من أين تأتي سجلات الصفحات التي لا تُنشأ من اللوحة — messaging::origin
|
| الجدول الفارغ بلا تفسير يقرأ كعطل. هذه النصوص تقول للمستخدم أين يقع الإجراء
| فعلًا بدل أن تتركه يبحث عن زرٍّ لا وجود له عمدًا.
*/

return [
    'conversation' => [
        'heading' => 'No conversations yet',
        'description' => 'Conversations start from the student or teacher portal. This page is for oversight, not for starting one on their behalf.',
        'action' => '',
    ],
    'message' => [
        'heading' => 'No messages yet',
        'description' => 'Messages are sent inside an existing conversation from the portal. They appear here for oversight and can be flagged for review.',
        'action' => '',
    ],
    'wall' => [
        'heading' => 'No class wall posts',
        'description' => 'Teachers publish to their class wall from the portal. Posts appear here for oversight and audit.',
        'action' => '',
    ],
    'whatsapp' => [
        'heading' => 'No inbound WhatsApp messages',
        'description' => 'Messages arrive through the provider webhook. An empty list with an active integration usually means the webhook is misconfigured.',
        'action' => '',
    ],
];
