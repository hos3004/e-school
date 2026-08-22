# Staff — المعلمون والموظفون

## يملك

`staff_profiles` · `teacher_contracts` · `teacher_rates` · `teacher_availability` · `teacher_leaves` · `teacher_courses`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا؛ المستحقات تستهلك أسعاره عبر العقد أدناه.

## يعتمد على

- `Identity` (حساب المستخدم).
- يعلن للآخرين: `TeacherRateResolver` — عقد عام في `Domain/Contracts` يستعمله `Payroll` دون معرفة جداوله (docs/08 §2).
- يعلن `TeacherQualificationQueries` لإرجاع معرّفات المعلمين المؤهلين للكورس والتحقق من التأهيل والجنس دون كشف نماذج Eloquent.

## قواعد خاصة

- **قيد قاعدة بيانات** `EXCLUDE USING gist` يمنع تداخل عقدين ساريَين لنفس المعلم.
- السعر الساري هو الذي كان فعالًا **بتاريخ الحصة** (`effective_from <= session_date < effective_to`) — لا السعر الحالي (docs/14 §2).
- مصادر 2–5 في `config/payroll.php → rate_resolution` كلها من `teacher_rates`: course · program · session_type · default.
- إتاحة المعلم `teacher_availability` مرجع تحذير التعارض في الجدولة، والإجازات المعتمدة تمنع الجدولة عليها (docs/13 §2).
- جنس المعلم والدولة والمنطقة محفوظة في `staff_profiles`؛ الإدخالات الجديدة لا تقبل منطقة لا تنتمي إلى الدولة عبر عقد الجغرافيا العام.
