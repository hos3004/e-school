<?php

declare(strict_types=1);
use Illuminate\Support\Env;

/*
| إعدادات موديول Guardians — كل رقم سياسة يعيش هنا لا في الكود.
*/

return [

    'account' => [
        'guardian_role' => Env::get('GUARDIAN_ACCOUNT_ROLE', 'guardian'),
    ],

    'limits' => [
        // أقصى عدد روابط (أوصياء) مسموح لطالب واحد. رُفع من 4 إلى 10 ليتسع
        // لحالات أب وأم وأجداد وولي أمر قانوني معًا بلا احتكاك، مع إبقاء سقف
        // معقول يمنع إدخالًا خاطئًا متكررًا.
        'max_links_per_student' => 10,

        // أقصى عدد طلاب مرتبطين بوصي واحد. سقف حماية تقني بعيد لا حد عمل
        // فعلي — طلب المالك صراحة عدم تقييد عدد الأبناء.
        'max_students_per_guardian' => 1000,
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
