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
    'program_already_closed' => 'البرنامج «:code» مُقفل بالفعل.',
    'program_not_closed' => 'البرنامج «:code» غير مُقفل، فلا شيء يُعاد فتحه.',
    'program_closure_blocked' => 'لا يمكن إقفال البرنامج «:code» الآن لأن تحته :blockers. أنهِ ما سبق أو أقفل ما تحته أولًا ثم أعد المحاولة. الإقفال لا يحذف شيئًا ويمكن التراجع عنه بإعادة الفتح.',
    'course_already_closed' => 'الكورس «:code» مُقفل بالفعل.',
    'course_not_closed' => 'الكورس «:code» غير مُقفل، فلا شيء يُعاد فتحه.',
    'course_closure_blocked' => 'لا يمكن إقفال الكورس «:code» الآن لأن تحته :blockers. أنهِ الحصص المتبقية أو ألغِها ثم أعد المحاولة. الإقفال لا يحذف شيئًا ويمكن التراجع عنه بإعادة الفتح.',
    'closure_blocker_courses_active' => ':count كورس نشط',
    'closure_blocker_enrollments_live' => ':count قيد طالب لم يُغلق بعد',
    'closure_blocker_sessions_open' => ':count حصة لم تصل حالة نهائية',
    'level_already_closed' => 'المستوى «:code» مُقفل بالفعل.',
    'level_not_closed' => 'المستوى «:code» غير مُقفل، فلا شيء يُعاد فتحه.',
    'level_closure_blocked' => 'لا يمكن إقفال المستوى «:code» الآن لأن تحته :blockers. أنهِ ما سبق أو أقفل كورساته أولًا ثم أعد المحاولة. الإقفال لا يحذف شيئًا ويمكن التراجع عنه بإعادة الفتح.',
    'level_closed_parent' => 'المستوى «:code» مؤرشَف، فلا يقبل كورسًا جديدًا ولا نقل كورس إليه. أعد فتحه من الأرشيف أولًا.',
    'program_closed_parent' => 'البرنامج «:code» مؤرشَف، فلا يقبل مستوى جديدًا. أعد فتحه من الأرشيف أولًا.',
];
