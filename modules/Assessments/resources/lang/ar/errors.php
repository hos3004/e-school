<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Assessments.
| تُستهلك عبر __('assessments::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'passing_score_above_total' => 'علامة النجاح لا يجوز أن تتجاوز الدرجة الكلية للاختبار.',
    'invalid_availability_window' => 'تاريخ بداية التوفر يجب أن يسبق تاريخ نهاية التوفر.',
    'invalid_max_attempts' => 'عدد المحاولات المسموح به يجب أن يكون واحدًا على الأقل.',
    'outside_availability_window' => 'هذا الاختبار غير متاح في الوقت الحالي.',
    'max_attempts_exhausted' => 'استُنفدت المحاولات المسموحة لهذا الاختبار (:max_attempts محاولات).',
    'attempt_already_submitted' => 'سُلِّمت هذه المحاولة سابقًا ولا يمكن تعديل إجاباتها.',
    'submission_deadline_passed' => 'انقضى الموعد النهائي لتسليم هذه المحاولة.',
    'grade_before_submission' => 'لا يمكن تصحيح محاولة قبل تسليمها.',
    'attempt_already_graded' => 'صُحّحت هذه المحاولة سابقًا ولا يمكن تعديل نتيجتها.',
    'score_out_of_range' => 'الدرجة يجب أن تكون بين صفر والدرجة الكلية للاختبار (:total_score).',
    'archive_with_attempts' => 'لا يمكن أرشفة اختبار سبق أن سُجلت عليه محاولات.',
    'edit_after_attempts' => 'لا يمكن تعديل أسئلة اختبار بعد تسجيل محاولات عليه.',
    'question_score_invalid' => 'درجة السؤال يجب أن تكون درجة موجبة على الأقل.',
    'questions_score_exceeds_total' => 'مجموع درجات الأسئلة يتجاوز الدرجة الكلية المحددة للاختبار.',
    'question_sort_order_taken' => 'رقم الترتيب (:sort_order) مستخدم لسؤال آخر في هذا الاختبار.',
];
