<?php

declare(strict_types=1);

return [
    'navigation_label' => 'نماذج التسجيل',
    'model_label' => 'نموذج تسجيل',
    'plural_model_label' => 'نماذج التسجيل',
    'sections' => [
        'identity' => 'بيانات النموذج والنشر',
        'questions' => 'أسئلة النموذج',
        'questions_help' => 'البيانات الأساسية للطالب تظهر تلقائيًا. أضف هنا الأسئلة الخاصة بهذا البرنامج ورتبها بالسحب.',
    ],
    'fields' => [
        'title' => 'اسم النموذج',
        'title_ar' => 'اسم النموذج (عربي)',
        'title_en' => 'اسم النموذج (إنجليزي)',
        'title_fr' => 'اسم النموذج (فرنسي)',
        'slug' => 'رمز الرابط',
        'slug_help' => 'أحرف إنجليزية صغيرة وأرقام وشرطات فقط؛ مثل kids-coding.',
        'description_ar' => 'الوصف (عربي)',
        'description_en' => 'الوصف (إنجليزي)',
        'description_fr' => 'الوصف (فرنسي)',
        'is_active' => 'منشور ويقبل التسجيل',
        'is_active_help' => 'عند الإيقاف يبقى تاريخ الطلبات محفوظًا لكن الرابط لا يقبل طلبات جديدة.',
        'questions_count' => 'عدد الأسئلة',
        'updated_at' => 'آخر تحديث',
        'change_reason' => 'سبب الإنشاء أو التعديل',
        'change_reason_help' => 'يُحفظ السبب في سجل التدقيق مع القيم قبل التغيير وبعده.',
    ],
    'filters' => ['is_active' => 'حالة النشر'],
    'actions' => [
        'add_question' => 'إضافة سؤال',
        'open_public_form' => 'فتح النموذج العام',
    ],
];
