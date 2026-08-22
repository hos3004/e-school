<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Academics.
| تُستهلك عبر __('academics::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'program_code_taken' => 'كود البرنامج «:code» مستخدم بالفعل.',
    'level_code_taken' => 'كود المستوى «:code» مستخدم بالفعل في هذا البرنامج.',
    'course_code_taken' => 'كود الكورس «:code» مستخدم بالفعل.',
    'program_not_found' => 'البرنامج المطلوب غير موجود.',
    'level_not_found' => 'المستوى المطلوب غير موجود.',
    'rate_negative' => 'لا يمكن أن يكون السعر الافتراضي سالبًا.',
    'total_sessions_invalid' => 'عدد حصص الكورس يجب أن يكون واحدًا على الأقل.',
    'program_has_active_courses' => 'لا يمكن أرشفة البرنامج «:code» لأنه يحتوي كورسات نشطة — أرشف الكورسات أولًا.',
    'level_not_in_program' => 'أحد المستويات المرسلة لا ينتمي إلى البرنامج المحدد.',
];
