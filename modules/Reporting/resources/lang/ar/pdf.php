<?php

declare(strict_types=1);

return [
    'document' => [
        'organization_fallback' => 'المدرسة الإلكترونية',
        'generated_at' => 'تاريخ الإنشاء',
        'period' => 'الفترة',
        'timezone' => 'المنطقة الزمنية',
        'filters' => 'الفلاتر المطبقة',
        'summary' => 'ملخص التقرير',
        'sessions' => 'تفاصيل الحصص',
        'no_results' => 'لا توجد حصص مطابقة للفترة والفلاتر المحددة.',
        'page' => 'صفحة',
    ],
    'columns' => [
        'session' => 'الحصة',
        'schedule' => 'الموعد',
        'group' => 'المجموعة',
        'teacher' => 'المعلم',
        'students' => 'الطلاب',
        'attendance' => 'الحضور',
        'status' => 'الحالة',
        'cancellation_reason' => 'سبب الإلغاء',
    ],
    'labels' => [
        'course' => 'المقرر',
        'type' => 'النوع',
        'duration' => 'المدة',
        'minutes' => ':count دقيقة',
        'original_teacher' => 'المعلم الأصلي',
        'report_status' => 'حالة التقرير',
        'not_available' => '—',
    ],
    'errors' => [
        'invalid_configuration' => 'تعذر تصدير التقرير بسبب إعداد غير صالح في خدمة PDF.',
        'temporary_directory_unavailable' => 'تعذر تجهيز مساحة العمل المؤقتة لتصدير التقرير.',
        'rendering_failed' => 'تعذر إنشاء ملف PDF. حاول مرة أخرى أو تواصل مع الدعم.',
        'output_invalid' => 'أنشأت خدمة التصدير ملفًا غير صالح.',
    ],
];
