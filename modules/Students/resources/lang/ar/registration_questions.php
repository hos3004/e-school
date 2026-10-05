<?php

declare(strict_types=1);

return [
    'navigation_label' => 'أسئلة التسجيل',
    'model_label' => 'سؤال تسجيل',
    'plural_model_label' => 'أسئلة التسجيل',

    'fields' => [
        'registration_form' => 'نموذج التسجيل',
        'question_ar' => 'نص السؤال (عربي)',
        'question_en' => 'نص السؤال (إنجليزي)',
        'question_fr' => 'نص السؤال (فرنسي)',
        'type' => 'نوع السؤال',
        'options' => 'الخيارات',
        'is_required' => 'إجابة إلزامية',
        'is_active' => 'مفعّل',
        'sort_order' => 'ترتيب العرض',
        'is_filterable' => 'متاح للفلترة',
        'is_filterable_help' => 'يظهر السؤال فلترًا في شاشة طلبات التسجيل. متاح للاختيار الفردي والأرقام فقط.',
    ],

    'types' => [
        'text' => 'نص قصير',
        'textarea' => 'نص طويل',
        'select' => 'اختيار من قائمة',
        'radio' => 'اختيار فردي ظاهر',
        'checkbox' => 'اختيارات متعددة',
        'number' => 'رقم',
    ],

    'filters' => [
        'is_active' => 'مفعّل',
    ],

    'messages' => [
        'deleted' => 'تم حذف السؤال.',
    ],

    'answers' => [
        'section' => 'إجابات أسئلة التقييم',
        'question' => 'السؤال',
        'answer' => 'الإجابة',
        'empty' => 'لا توجد إجابات تقييم على هذا الطلب.',
    ],
];
