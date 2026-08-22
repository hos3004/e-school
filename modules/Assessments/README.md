# Assessments — الاختبارات والدرجات

## يملك

`assessments` · `questions` · `assessment_attempts`

## ينشر

- `assessments.attempt_submitted`
- `assessments.graded`

## يعتمد على

- `Academics` (الكورس) · `Students` (المُختبر).
- `Enrollments`: يستقبل `enrollments.reactivation_requested` ليتيح اختبار فك التجميد.
- `Discipline` يستهلك نتيجة `assessments.graded` لاختبار الجدية.

## قواعد خاصة

- نوع `reactivation` مخصص **لاختبار فك التجميد التأديبي** — يرتبط بـ `reactivation_request_id` في المحاولة.
- `max_attempts` و`passing_score` يحكمان إعادة المحاولة — لا اجتهاد خارج الحقلين.
- أنواع الأسئلة: `mcq | true_false | short_answer | essay`؛ الإجابات والدرجات تُخزَّن في المحاولة نفسها.
