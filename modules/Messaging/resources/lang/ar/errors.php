<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Messaging.
| تُستهلك عبر __('messaging::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'not_participant' => 'لا يمكنك إرسال رسالة في محادثة لست مشاركًا فيها.',
    'too_many_participants' => 'عدد المشاركين يتجاوز الحد المسموح (:max).',
    'direct_exceeds_two' => 'المحادثة المباشرة تقبل طرفين فقط.',
    'not_message_author' => 'لا يمكن تعديل رسالة لم تكتبها.',
    'message_flagged_locked' => 'رسالة موسومة كمخالفة لا يمكن تعديلها.',
    'message_already_edited' => 'تم تعديل هذه الرسالة مسبقًا ولا يمكن تعديلها مرة أخرى.',
    'edit_window_expired' => 'انتهت مهلة تعديل الرسالة (:minutes دقيقة).',
    'message_already_flagged' => 'هذه الرسالة موسومة كمخالفة بالفعل.',
    'wall_comment_too_long' => 'التعليق أطول من الطول المسموح (:max حرفًا).',
    'whatsapp_duplicate_message' => 'رسالة الواتساب هذه مسجّلة مسبقًا.',
    'whatsapp_already_handled' => 'تم التعامل مع هذه الرسالة مسبقًا.',
];
