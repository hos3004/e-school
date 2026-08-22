<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| إعدادات موديول Audit
|--------------------------------------------------------------------------
| كل رقم سياسة يعيش هنا — لا أرقام داخل الكود إطلاقًا.
| يُدمج تلقائيًا عبر BaseModuleServiceProvider ويُقرأ بـ config('audit.*').
*/

return [

    /*
    | مدة الاحتفاظ بقيود التدقيق بالأيام قبل أن تؤهَّل للحذف الدوري.
    */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 730),

    /*
    | أنماط الأفعال الحساسة التي يُشترط لها سبب مكتوب في القيدة.
    | تدعم wildcards بنمط Str::is مثل payroll.*.
    */
    'reason_required_actions' => [
        'presence.*',
        'academic_status.*',
        'payroll.*',
        'billing.*',
        'permission_changed',
        'enrollment.*',
    ],

    /*
    | حجم صفحة القيود الافتراضي في واجهات القراءة.
    */
    'per_page' => 50,
];
