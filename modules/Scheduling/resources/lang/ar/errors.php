<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Scheduling.
| تُستهلك عبر __('scheduling::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'postponement_invalid_transition' => 'لا يمكن نقل طلب التأجيل من :from إلى :to.',
    'rejection_reason_required' => 'سبب الرفض مطلوب.',
    'reason_required' => 'سبب العملية مطلوب.',
    'session_not_found' => 'الحصة المطلوبة غير موجودة.',
    'session_not_postponable' => 'لا يمكن تأجيل الحصة في حالتها الحالية (:status).',
    'postponement_not_found' => 'طلب التأجيل غير موجود.',
    'postponement_notice_not_met' => 'يجب تقديم طلب التأجيل قبل الموعد بـ :required دقيقة على الأقل.',
    'postponement_already_pending' => 'يوجد طلب تأجيل معلّق لهذه الحصة بالفعل.',
    'proposed_start_in_past' => 'الموعد المقترح يجب أن يكون في المستقبل.',
    'student_not_participant' => 'الطالب ليس مشاركًا في هذه الحصة.',
    'teacher_not_assigned_to_session' => 'لا يمكنك طلب تأجيل حصة غير مسندة إليك.',
    'outside_makeup_window' => 'يجب أن تقع حصة التلافي خلال :days يومًا من الموعد الأصلي.',
    'conflict_detected' => 'الموعد يتعارض مع :count حصة قائمة.',
    'weekdays_invalid' => 'اختر يومًا صحيحًا واحدًا على الأقل.',
    'interval_invalid' => 'فاصل التكرار الأسبوعي غير صالح.',
    'rrule_invalid' => 'قاعدة التكرار غير صالحة أو غير مدعومة.',
    'timezone_invalid' => 'المنطقة الزمنية غير صالحة.',
    'target_invalid' => 'اختر مجموعة أو طالبًا واحدًا فقط.',
    'course_not_found' => 'الكورس غير موجود أو لا يتبع المؤسسة.',
    'teacher_not_eligible' => 'المعلم غير نشط أو غير مؤهل لهذا الكورس.',
    'teacher_not_assigned' => 'المعلم غير مسند لهذا الكورس في المجموعة طوال مدة الجدول.',
    'ends_before_start' => 'تاريخ نهاية الجدول يجب ألا يسبق تاريخ البداية.',
    'duration_invalid' => 'مدة الحصة ليست من المدد المعتمدة.',
    'course_mode_mismatch' => 'نوع الجدولة لا يطابق نمط الحصص المعتمد للكورس.',
    'group_not_eligible' => 'المجموعة غير نشطة أو غير مرتبطة ببرنامج الكورس.',
    'student_not_schedulable' => 'الطالب لا يملك قيدًا يسمح بجدولته في برنامج الكورس.',
    'schedule_inactive' => 'قالب الجدول متوقف.',
    'teacher_on_leave' => 'المعلم في إجازة معتمدة بتاريخ :date.',
    'outside_teacher_availability' => 'الموعد خارج الإتاحة المعتمدة للمعلم.',
    'individual_quran_course_missing' => 'كورس القرآن الفردي غير موجود أو غير مفعّل.',
    'bulk_no_eligible_students' => 'لا يوجد ضمن التحديد طالب مؤهل لتسكين القرآن الفردي.',
    'bulk_insufficient_slots' => 'الخانات المستقلة المتاحة (:slots) أقل من عدد الطلاب المؤهلين (:students).',
    'bulk_limit_exceeded' => 'لا يمكن تسكين أكثر من :maximum طالب في عملية واحدة.',
];
