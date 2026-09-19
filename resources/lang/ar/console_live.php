<?php

declare(strict_types=1);

return [
    'title' => 'المتابعة الآن',
    'description' => 'كل حصص اليوم بحالتها الآن: من دخل الغرفة ومن لم يدخل، وما انتهى دون قرار.',
    'as_of' => 'حتى الساعة',
    'filtered' => 'المعروض حالة واحدة فقط.',
    'show_all' => 'اعرض الكل',
    'empty' => 'لا حصص في هذا اليوم ضمن هذه الحالة.',
    'teacher_in' => 'داخل الغرفة',
    'teacher_out' => 'لم يدخل',
    'students' => 'الطلاب',
    'minute' => 'دقيقة',
    'needs_decision' => 'انتهى موعدها ولم تُقفل — لن يُحتسب لها مستحق حتى تقرر.',
    'go_review' => 'اعتماد الحصص',
    'report_missing' => 'لا يوجد تقرير حصة.',
    'states' => [
        'running_nobody' => 'جارية ولم يدخل أحد',
        'running_no_teacher' => 'جارية والمعلم لم يدخل',
        'running_no_student' => 'جارية والطالب لم يدخل',
        'running_ok' => 'جارية والجميع حاضر',
        'upcoming' => 'قادمة اليوم',
        'ended_unresolved' => 'منتهية بلا قرار',
        'ended' => 'منتهية',
    ],
];
