<?php

declare(strict_types=1);

/*
| نصوص واجهة Filament لموديول Groups.
| تُستهلك عبر __('groups::filament.key').
*/

return [
    'navigation_group' => 'الأشخاص',
    'navigation_label' => 'المجموعات',
    'model_label' => 'مجموعة',
    'plural_model_label' => 'المجموعات',
    'name_locale_key' => 'اللغة',
    'name_value_label' => 'الاسم',
    'active_members_count' => 'عدد الطلاب الحاليين',
    'not_available' => 'غير متاح',
    'fields' => [
        'name_ar' => 'اسم المجموعة بالعربية',
        'name_en' => 'اسم المجموعة بالإنجليزية',
        'name_fr' => 'اسم المجموعة بالفرنسية',
        'reason_help' => 'اكتب سبب الإنشاء أو التعديل ليظهر في سجل التدقيق.',
    ],
    'hub' => [
        'title' => 'مركز عمليات المجموعة',
        'overview' => 'ملخص المجموعة',
        'available_places' => 'الأماكن المتاحة',
        'programs' => 'البرامج',
        'teachers' => 'المعلمون',
        'students' => 'الطلاب',
        'sessions' => 'الحصص',
        'empty' => 'لا توجد بيانات في هذا القسم.',
        'fields' => [
            'teacher' => 'المعلم',
            'student' => 'الطالب',
            'student_code' => 'كود الطالب',
            'session' => 'الحصة',
            'scheduled_start' => 'بداية الحصة',
            'scheduled_end' => 'نهاية الحصة',
        ],
    ],
    'actions' => [
        'schedule_sessions' => 'جدولة حصص',
        'place_student' => 'تسكين طالب',
        'student_placed' => 'تم تسكين الطالب في المجموعة.',
        'assign_teacher' => 'إسناد معلم',
        'teacher_assigned' => 'تم إسناد المعلم إلى المجموعة.',
        'attach_program' => 'ربط برنامج',
        'program_attached' => 'تم ربط البرنامج بالمجموعة.',
        'activate' => 'تفعيل المجموعة',
        'complete' => 'إتمام المجموعة',
        'archive' => 'أرشفة المجموعة',
        'active_success' => 'تم تفعيل المجموعة.',
        'completed_success' => 'تم إتمام المجموعة.',
    ],
];
