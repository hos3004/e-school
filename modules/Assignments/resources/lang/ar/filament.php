<?php

declare(strict_types=1);

return [
    'navigation_group' => 'التعلّم والتقييم',
    'assignments' => ['navigation_label' => 'الواجبات', 'model_label' => 'واجب', 'plural_model_label' => 'الواجبات'],
    'sections' => [
        'audience' => 'الجمهور الأكاديمي',
        'audience_help' => 'اختر البرنامج ثم المقرر، وحدد مجموعة أو اتركها فارغة لاستهداف كل طلاب المقرر النشطين.',
        'content' => 'المحتوى والتعليمات',
        'grading' => 'التوقيت وسياسة الدرجات',
    ],
    'course_wide_help' => 'اترك المجموعة فارغة ليستهدف الواجب كل طلاب المقرر النشطين.',
    'reason_help' => 'يظهر السبب في سجل التدقيق ولا يُعرض للطالب.',
    'actions' => ['edit' => 'تعديل', 'archive' => 'أرشفة', 'grade' => 'تصحيح واعتماد'],
    'metrics' => [
        'recipients' => 'الطلاب المستهدفون',
        'pending' => 'لم يسلّموا',
        'submitted' => 'سلّموا في الموعد',
        'late' => 'سلّموا متأخرًا',
        'graded' => 'تم تصحيحهم',
        'awaiting_grading' => 'بانتظار التصحيح',
    ],
    'hub' => [
        'overview' => 'ملخص الواجب', 'metrics' => 'مؤشرات التنفيذ', 'history' => 'التسليمات والتاريخ',
        'submission_snapshot' => 'لقطة التسليمات', 'audit' => 'سجل التدقيق', 'no_audit' => 'لا توجد تغييرات مسجلة بعد.',
        'action' => 'الإجراء', 'actor' => 'المنفذ', 'changed_at' => 'وقت التغيير',
    ],
    'submissions' => [
        'title' => 'تسليمات الطلاب', 'student' => 'الطالب', 'submitted_at' => 'وقت التسليم', 'is_late' => 'متأخر',
        'raw_score' => 'الدرجة قبل الخصم', 'penalty_points' => 'نقاط الخصم', 'empty' => 'لا يوجد طلاب مستهدفون لهذا الواجب.',
    ],
];
