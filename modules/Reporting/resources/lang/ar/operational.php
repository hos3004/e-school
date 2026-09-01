<?php

declare(strict_types=1);

return [
    'navigation' => 'تقارير الحصص',
    'title' => 'التقارير التشغيلية للحصص',
    'description' => 'موجز وتفاصيل الحصص والطلاب والمعلمين والمجموعات بحسب الفترة والفلاتر المحددة.',
    'periods' => [
        'today' => 'اليوم',
        'yesterday' => 'أمس',
        'this_week' => 'هذا الأسبوع',
        'previous_week' => 'الأسبوع السابق',
        'this_month' => 'هذا الشهر',
        'custom' => 'فترة مخصصة',
    ],
    'filters' => [
        'period' => 'الفترة', 'preset' => 'الفترة السريعة', 'from' => 'من تاريخ', 'until' => 'إلى تاريخ',
        'status' => 'حالة الحصة', 'attendance_status' => 'حالة الحضور', 'session_type' => 'نوع الحصة',
        'student' => 'الطالب', 'teacher' => 'المعلم المنفذ', 'original_teacher' => 'المعلم الأصلي',
        'group' => 'المجموعة', 'course' => 'المقرر', 'report_status' => 'حالة تقرير المعلم',
        'search' => 'البحث',
    ],
    'columns' => [
        'session' => 'الحصة', 'scheduled_at' => 'الموعد', 'duration' => 'المدة', 'course' => 'المقرر',
        'group' => 'المجموعة', 'teacher' => 'المعلم', 'students' => 'الطلاب', 'attendance' => 'الحضور',
        'status' => 'حالة الحصة', 'session_type' => 'النوع', 'report_status' => 'تقرير المعلم',
        'cancellation_reason' => 'سبب الإلغاء/التأجيل',
    ],
    'summary' => [
        'total' => 'إجمالي الحصص', 'completed' => 'مكتملة', 'cancelled' => 'ملغاة', 'postponed' => 'مؤجلة',
        'no_show' => 'عدم حضور', 'excused' => 'غياب بعذر', 'scheduled' => 'قادمة/جارية',
        'students' => 'طلاب', 'teachers' => 'معلمون', 'groups' => 'مجموعات',
        'present' => 'حضور', 'absent' => 'غياب', 'attendance_rate' => 'نسبة الحضور',
        'scheduled_minutes' => 'دقائق مجدولة', 'actual_minutes' => 'دقائق فعلية',
        'reports_submitted' => 'تقارير مقدمة', 'reports_late' => 'تقارير متأخرة', 'reports_missing' => 'تقارير ناقصة',
    ],
    'report_status' => ['submitted' => 'مقدّم', 'late' => 'مقدّم متأخرًا', 'missing' => 'غير مقدّم', 'not_required' => 'غير مطلوب بعد'],
    'attendance' => ['unrecorded' => 'غير مرصود', 'present_count' => ':count حاضر', 'absent_count' => ':count غائب'],
    'actions' => ['run_report' => 'تشغيل التقرير', 'export_pdf' => 'تصدير PDF', 'reset_filters' => 'إعادة ضبط الفلاتر'],
    'initial_title' => 'اختر الفترة والفلاتر ثم شغّل التقرير',
    'initial_description' => 'لن تُحمّل بيانات الحصص حتى تضغط «تشغيل التقرير»، ويمكنك تعديل الفلاتر قبل ذلك.',
    'empty' => 'لا توجد حصص تطابق الفترة والفلاتر المحددة.',
    'limit_exceeded' => 'عدد النتائج أكبر من الحد المسموح. ضيّق الفترة أو أضف فلاتر قبل التصدير.',
    'period_label' => 'من :from إلى :until',
    'substitute' => 'بديل عن :teacher',
    'selected_value' => 'قيمة محددة',
    'not_available' => 'غير متاح',
    'separators' => ['list' => '، '],
    'minutes' => ':count دقيقة',
    'unknown_session' => 'حصة بلا عنوان', 'unknown_student' => 'طالب غير معروف', 'unknown_teacher' => 'معلم غير معروف',
    'unknown_group' => 'مجموعة غير معروفة', 'unknown_course' => 'مقرر غير معروف', 'no_students' => 'لا يوجد طلاب',
];
