<?php

declare(strict_types=1);

/*
| رسائل موديول Reporting العامة.
| تُستهلك عبر __('reporting::messages.key') — ولا نص ظاهر خارج ملفات الترجمة.
*/

return [

    'organization_required' => 'يلزم وجود مؤسسة نشطة لعرض التقارير.',
    'invalid_period' => 'يجب أن تكون الفترة صحيحة وألا تتجاوز :days يومًا.',
    'invalid_period_dates' => 'تاريخا بداية الفترة ونهايتها غير صالحين.',
    'pdf_failed' => 'تعذر إنشاء ملف PDF. يرجى المحاولة مرة أخرى.',
    'report_failed' => 'تعذر تحميل التقرير. يرجى المحاولة مرة أخرى.',

    'seeder_no_enrollments' => 'لا توجد تسجيلات بعد — تم تخطي لوحات الطلاب.',

    'seeder_no_staff' => 'لا توجد ملفات موظفين بعد — تم تخطي لوحات المعلمين.',

    'dashboard_corrected' => 'تم تصحيح اللوحة وتوثيق السبب.',

    'snapshot_recorded' => 'تم تسجيل لقطة اليوم التنظيمية.',

];
