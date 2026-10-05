<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Enrollments.
| تُستهلك عبر __('enrollments::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'placement_reason_required' => 'سبب تسكين الطالب إلزامي.',
    'organization_mismatch' => 'القيد ينتمي إلى مؤسسة أخرى.',
    'archived_enrollment' => 'لا يمكن إعادة استخدام قيد مؤرشف في التسكين.',
    'invalid_placement_transition' => 'لا يمكن نقل القيد من «:from» إلى «:to» أثناء التسكين.',
    'student_not_cleared' => 'الطالب غير مقبول للتسكين بعد.',
    'academic_context_invalid' => 'البرنامج أو الكورس المحدد غير نشط داخل هذه المؤسسة.',
    'eligibility_blocked' => 'الطالب لا يستوفي شروط أهلية البرنامج المحدد.',
    'duplicate_active_enrollment' => 'يوجد قيد قائم لهذا الطالب في البرنامج نفسه.',
    'invalid_freeze_type' => 'نوع التجميد غير صالح. القيم المتاحة: :types.',
    'pause_return_date_in_past' => 'موعد العودة لا يمكن أن يكون في الماضي.',
    'reactivation_permission_denied' => 'لا تملك صلاحية اعتماد فك التجميد (:permission).',
    'use_pause_action' => 'استخدم إجراء الإيقاف المؤقت لتحديد موعد العودة.',
    'use_freeze_action' => 'استخدم إجراء التجميد لتسجيل النوع والسبب.',
    'reactivation_requires_permission' => 'العودة من التقييم تتطلب صلاحية :permission.',
    'transition_reason_required' => 'يجب كتابة سبب واضح لهذا التغيير.',
    'invalid_transition' => 'لا يمكن نقل القيد من :from إلى :to.',
    'student_outside_organization' => 'الطالب المحدد غير موجود في هذه المؤسسة.',
    'program_outside_organization' => 'البرنامج المحدد غير موجود أو غير متاح في هذه المؤسسة.',
    'level_outside_program' => 'المستوى المحدد لا ينتمي إلى برنامج القيد داخل المؤسسة.',
    'level_unchanged' => 'المستوى المحدد هو المستوى الحالي بالفعل.',
    'activation_requires_placement' => 'تفعيل القيد المقبول يجب أن يتم عبر تسكين الطالب في كورس ومجموعة متاحة.',
];
