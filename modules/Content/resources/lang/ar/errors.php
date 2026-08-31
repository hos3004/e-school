<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Content.
| تُستهلك عبر __('content::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'extension_not_allowed' => 'امتداد الملف «:extension» غير مسموح به.',
    'file_requires_storage' => 'المادة من نوع ملف تتطلب تحديد مساحة التخزين ومسار الملف.',
    'file_too_large' => 'يجب ألا يتجاوز حجم الملف :max_mb ميغابايت.',
    'link_requires_url' => 'المادة من نوع رابط تتطلب رابطًا خارجيًا صالحًا.',
    'removal_reason_required' => 'يجب كتابة سبب إزالة المادة التعليمية.',
    'visibility_window_invalid' => 'يجب أن يكون وقت انتهاء الإتاحة بعد وقت بدايتها أو مساويًا له.',
    'reason_required' => 'يجب كتابة سبب واضح لهذا التغيير.',
    'course_outside_organization' => 'المقرر المحدد غير موجود أو غير متاح داخل هذه المؤسسة.',
    'invalid_status_transition' => 'لا يمكن نقل حالة النشر من «:from» إلى «:to».',
];
