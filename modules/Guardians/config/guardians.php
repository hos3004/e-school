<?php

declare(strict_types=1);

/*
| إعدادات موديول Guardians — كل رقم سياسة يعيش هنا لا في الكود.
*/

return [

    'account' => [
        'guardian_role' => Illuminate\Support\Env::get('GUARDIAN_ACCOUNT_ROLE', 'guardian'),
    ],

    'limits' => [
        // أقصى عدد روابط (أوصياء) مسموح لطالب واحد.
        'max_links_per_student' => 4,

        // أقصى عدد طلاب مرتبطين بوصي واحد.
        'max_students_per_guardian' => 15,
    ],

    'links' => [
        // هل يُشترط توثيق الرابط قبل تمكين الوسيط من التصرف باسم الطالب؟
        'require_verification_for_acting' => true,

        // الأقسام المرئية افتراضيًا عند إنشاء رابط دون تحديد.
        'default_visible_sections' => ['attendance', 'schedule', 'grades'],

        // الأقسام المسموح اختيارها أصلًا.
        'allowed_visible_sections' => ['attendance', 'schedule', 'grades', 'billing', 'recordings'],
    ],

];
