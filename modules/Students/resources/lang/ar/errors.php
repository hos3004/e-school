<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Students.
| تُستهلك عبر __('students::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [

    'already_registered' => 'هذا المستخدم مسجَّل بالفعل كطالب.',
    'code_taken' => 'رقم الطالب «:student_code» مستخدم من قبل — اختر رقمًا آخر.',
    'archived_read_only' => 'لا يمكن تعديل ملف طالب مؤرشف؛ استرجعه أولًا.',
    'already_archived' => 'الطالب مؤرشف بالفعل.',
    'archive_reason_required' => 'سبب الأرشفة إلزامي وفق سياسة التدقيق.',
    'not_archived' => 'لا يمكن استرجاع طالب غير مؤرشف.',
    'not_found' => 'ملف الطالب المطلوب غير موجود.',

];
