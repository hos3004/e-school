<?php

declare(strict_types=1);

/**
 * الإعدادات الأكاديمية والتقويم.
 *
 * المؤسسة إلكترونية بالكامل (بلا مقر) وتخدم الوطن العربي، لذا التوقيت
 * والعطلات تُدار مركزيًا بينما يُعرض كل شيء بتوقيت المستخدم.
 */
return [

    'default_timezone' => env('ORG_DEFAULT_TIMEZONE', 'Africa/Cairo'),
    'week_starts_on' => env('ORG_WEEK_STARTS_ON', 'saturday'),

    /*
     * أنواع الحصص وسعتها.
     */
    'session_types' => [
        'individual' => ['min_students' => 1, 'max_students' => 1],
        'group' => ['min_students' => 2, 'max_students' => 25],
        'webinar' => ['min_students' => 1, 'max_students' => 70],
        'makeup' => ['min_students' => 1, 'max_students' => 25],
        'assessment' => ['min_students' => 1, 'max_students' => 25],
    ],

    /*
     * المجموعات.
     * مجموعات القرآن الحالية من 2 إلى 6 طلاب؛ الحد الأعلى العام 25.
     */
    'groups' => [
        'default_capacity' => 6,
        'max_capacity' => 25,
        'allow_waitlist' => true,

        // مجموعة واحدة قد تدرس أكثر من برنامج، وقد يكون لها أكثر من معلم
        // لأكثر من مادة — الهيكل يدعم الحالتين.
        'allows_multiple_programs' => true,
        'allows_multiple_teachers' => true,
    ],

    /*
     * التقويم الأكاديمي.
     */
    'calendar' => [
        'holiday_sources' => ['manual', 'hijri_official'],
        'blocks_scheduling_on_holiday' => false, // تحذير وليس منعًا
        'teacher_leave_requires_approval' => true,
        'teacher_leave_approver_permission' => 'staff.leave.approve',
    ],

    /*
     * سياسة الحضور — كيف نحوّل الدقائق الفعلية إلى حالة حضور.
     * تُحسب آليًا من أحداث الدخول والخروج ثم يؤكدها المعلم.
     */
    'attendance' => [
        'auto_compute_from_join_events' => true,
        'teacher_confirmation_required' => true,

        'thresholds' => [
            // حضر هذه النسبة من مدة الحصة أو أكثر = حاضر
            'present_min_percent' => 75,

            // بين هذه النسبة والسابقة = حضور جزئي
            'partial_min_percent' => 40,

            // دخل بعد هذه الدقائق من البداية = متأخر
            'late_after_minutes' => 10,

            // خرج قبل هذه الدقائق من النهاية = انصرف مبكرًا
            'left_early_before_minutes' => 10,
        ],

        // مهلة المعلم لاعتماد كشف الحضور قبل التصعيد للمشرف.
        'confirmation_sla_hours' => 24,
    ],

    /*
     * تقرير الحصة الذي يكتبه المعلم.
     */
    'session_report' => [
        'required' => true,
        'sla_hours' => 24,
        'group_requires_per_student_note' => false,
        'individual_requires_per_student_note' => true,
        'late_report_counts_as_violation' => true,
    ],

    /*
     * التقرير الشهري — يُولّده النظام مسوّدةً ويعتمده المشرف.
     */
    'monthly_report' => [
        'auto_draft' => true,
        'generate_on_day' => 1,
        'sources' => ['attendance', 'session_reports', 'assessments', 'assignments', 'discipline'],
        'requires_supervisor_approval' => true,
        'send_to' => ['student', 'guardian'],
    ],
];
