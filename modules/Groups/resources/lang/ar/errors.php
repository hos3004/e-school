<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Groups.
| تُستهلك عبر __('groups::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'code_taken' => 'رمز المجموعة «:code» مستخدم بالفعل. اختر رمزًا آخر.',
    'ends_before_starts' => 'تاريخ الانتهاء (:ends_on) يجب أن يكون بعد تاريخ البدء (:starts_on).',
    'already_archived' => 'المجموعة المطلوبة مؤرشفة بالفعل ولا يمكن تعديلها.',
    'invalid_status_transition' => 'لا يمكن نقل المجموعة من حالة «:from» إلى «:to».',
    'group_not_accepting_members' => 'المجموعة في حالة «:status» ولا تقبل تعديلات على الطلاب أو المعلمين أو البرامج.',
    'capacity_reached' => 'المجموعة مكتملة العدد؛ السعة القصوى :capacity طالبًا.',
    'already_enrolled' => 'الطالب «:student_profile_id» منتسب بالفعل إلى هذه المجموعة.',
    'withdraw_reason_required' => 'لا يمكن تسجيل خروج الطالب دون كتابة سبب.',
    'archive_reason_required' => 'لا يمكن أرشفة مجموعة دون كتابة السبب.',
    'membership_not_active' => 'سجل الانتساب «:membership_id» غير نشط، فلا يمكن تسجيل الخروج منه.',
    'teacher_already_assigned' => 'المعلم «:staff_profile_id» مُسند بالفعل إلى هذا المقرر داخل المجموعة.',
    'assignment_already_closed' => 'إسناد المعلم «:assignment_id» مغلق بالفعل ولا يمكن إلغاؤه مرة أخرى.',
    'program_already_attached' => 'البرنامج «:program_id» مرتبط بالفعل بهذه المجموعة.',
];
