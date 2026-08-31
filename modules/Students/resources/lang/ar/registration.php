<?php

declare(strict_types=1);

return [
    'navigation_label' => 'طلبات التسجيل',
    'model_label' => 'طلب تسجيل',
    'plural_model_label' => 'طلبات التسجيل',
    'status' => [
        'draft' => 'مسودة',
        'submitted' => 'مقدَّم',
        'under_review' => 'قيد المراجعة',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'waiting_assignment' => 'بانتظار التوزيع',
        'assigned' => 'موزَّع',
    ],
    'actions' => [
        'submit' => 'تقديم الطلب',
        'review' => 'بدء المراجعة',
        'accept' => 'قبول الطلب',
        'reject' => 'رفض الطلب',
        'reject_heading' => 'رفض طلب التسجيل',
        'reject_description' => 'يُحفظ سبب الرفض مع قرار المراجعة.',
    ],
    'messages' => [
        'submitted' => 'تم تقديم طلب التسجيل.',
        'under_review' => 'انتقل الطلب إلى المراجعة.',
        'accepted' => 'قُبل الطلب وأُنشئ ملف الطالب.',
        'rejected' => 'تم رفض طلب التسجيل.',
        'assigned' => 'تم تسكين الطالب في المجموعة.',
    ],
    'filters' => [
        'registration_form' => 'مصدر التسجيل / النموذج',
        'registration_form_unknown' => 'تسجيل داخلي أو مصدر قديم',
        'status' => 'الحالة',
        'country' => 'الدولة',
        'region' => 'المنطقة',
        'language' => 'اللغة المفضّلة',
        'age_range' => 'نطاق العمر',
        'age_from' => 'من عمر',
        'age_to' => 'إلى عمر',
        'age_indicator' => 'العمر من :from إلى :to',
        'registered_at' => 'تاريخ التسجيل',
        'registered_from' => 'من تاريخ',
        'registered_until' => 'إلى تاريخ',
        'value_from' => 'من',
        'value_until' => 'إلى',
    ],
    'duplicate' => 'طلب مكرر محتمل',
    'duplicate_yes' => 'نعم',
    'duplicate_no' => 'لا',
];
