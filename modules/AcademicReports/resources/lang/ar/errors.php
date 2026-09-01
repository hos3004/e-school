<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول AcademicReports.
| تُستهلك عبر __('academicreports::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'session_report_session_not_found' => 'الحصة المطلوبة غير موجودة داخل مؤسستك.',
    'session_report_teacher_not_assigned' => 'لا يمكنك إرسال تقرير لحصة غير مسندة إليك.',
    'session_report_invalid_session_state' => 'لا يمكن إرسال التقرير في حالة الحصة الحالية (:status).',
    'session_report_students_mismatch' => 'يجب أن يشمل التقرير جميع طلاب الحصة الحاليين فقط.',
    'session_report_already_submitted' => 'تم تقديم تقرير لهذه الحصة مسبقًا، ولا يجوز تقديم أكثر من تقرير واحد للحصة الواحدة. (الحصة: :session_id)',
    'session_report_empty_students' => 'لا يمكن تقديم تقرير حصة دون تقييم طالب واحد على الأقل.',
    'session_report_duplicate_student' => 'الطالب مُقيَّم أكثر من مرة في نفس التقرير. (الطالب: :student_profile_id)',
    'session_report_score_out_of_range' => 'التقييمات يجب أن تكون بين :min و :max لكل محور من محاور التقييم.',
    'monthly_report_duplicate_period' => 'يوجد بالفعل تقرير شهري لهذا الطالب عن الفترة :month/:year، ولا يجوز إنشاء تقرير مكرر.',
    'monthly_report_invalid_transition' => 'لا يمكن تغيير حالة التقرير الشهري من «:from» إلى «:to» — هذا الانتقال غير مسموح.',
];
