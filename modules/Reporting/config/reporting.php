<?php

declare(strict_types=1);

/*
| إعدادات موديول Reporting.
|
| كل أرقام السياسة وخرائط الإسقاط تعيش هنا — لا شيء hardcoded داخل الكود.
| تُدمج تلقائيًا عبر BaseModuleServiceProvider وتُقرأ بـ config('reporting.*').
*/

return [

    /*
    | خريطة إسقاط أحداث الموديولات الأخرى على لوحات القراءة.
    |
    | المفتاح: اسم الحدث المستقر (name()).
    | القيمة: قائمة تحديثات، كل واحدة تحدد اللوحة والمقياس وحقول الحمولة
    |         التي تُقرأ كمعرّفات، وما إذا كان التأثير زيادة أم تصفيرًا.
    */
    'projections' => [
        'sessions.completed' => [
            ['board' => 'student', 'metric' => 'sessions_completed', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => 'completed_at'],
            ['board' => 'teacher', 'metric' => 'sessions_completed', 'keys' => ['staff_profile_id'], 'at' => 'completed_at'],
        ],
        'sessions.no_show_recorded' => [
            ['board' => 'student', 'metric' => 'sessions_missed', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => null],
        ],
        'attendance.confirmed' => [
            ['board' => 'student', 'metric' => 'sessions_attended', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => null],
        ],
        'discipline.violation_recorded' => [
            ['board' => 'student', 'metric' => 'violation_recorded', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => 'occurred_at'],
        ],
        'payroll.entry_recorded' => [
            ['board' => 'teacher', 'metric' => 'payout_credited', 'keys' => ['staff_profile_id'], 'at' => null, 'amount_minor' => 'amount_minor'],
        ],
        'enrollments.frozen' => [
            ['board' => 'student', 'metric' => 'freeze_recorded', 'keys' => ['enrollment_id', 'student_profile_id'], 'at' => null],
        ],
    ],

    // سقف طول سبب التصحيح اليدوي في التقارير.
    'correction' => [
        'reason_min_chars' => 5,
        'reason_max_chars' => 500,
    ],

    // عتبات العرض: نسبة الحضور تحتها يُعد الطالب معرّضًا للخطر (من أصل 10000).
    'thresholds' => [
        'at_risk_max_rate_bp' => 7000,
        'at_risk_list_limit' => 50,
    ],

    // التقارير التشغيلية الحية عبر عقود القراءة العامة.
    'operational' => [
        'default_preset' => 'this_week',
        'week_starts_at' => 6,
        'max_period_days' => 366,
        'max_rows' => 10000,
        'export_max_rows' => 1000,
        'scan_page_size' => 250,
        'search_max_chars' => 120,
        'default_per_page' => 25,
        'per_page_options' => [10, 25, 50, 100],
        'datetime_format' => 'Y-m-d H:i',
    ],

    // تصدير PDF محلي عبر mPDF؛ لا متصفح ولا عملية خارجية ولا طلبات شبكة.
    'pdf' => [
        'temporary_directory' => env('REPORTING_PDF_TEMP_DIRECTORY', storage_path('app/reporting/pdf')),
        'format' => env('REPORTING_PDF_FORMAT', 'A4-L'),
        'default_font' => env('REPORTING_PDF_DEFAULT_FONT', 'dejavusans'),
    ],

];
