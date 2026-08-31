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
    'reason_required' => 'سبب التغيير إلزامي.',
    'fixed_program_dates_required' => 'البرنامج محدد المدة يحتاج تاريخ بداية ونهاية.',
    'ongoing_program_end_forbidden' => 'البرنامج المستمر لا يقبل تاريخ نهاية؛ حوّله إلى محدد المدة أولًا.',
    'program_end_before_start' => 'تاريخ نهاية البرنامج يجب ألا يسبق تاريخ البداية.',
    'age_range_invalid' => 'نهاية الفئة العمرية يجب ألا تقل عن بدايتها.',
    'category_code_taken' => 'كود التصنيف «:code» مستخدم داخل المؤسسة.',
    'category_parent_invalid' => 'التصنيف الأب غير صالح أو لا ينتمي إلى المؤسسة.',
    'category_outside_course_program' => 'أحد التصنيفات لا ينتمي إلى مؤسسة الكورس أو برنامجه.',
    'organization_required' => 'لا يمكن تنفيذ العملية الأكاديمية دون مؤسسة محددة.',
];
