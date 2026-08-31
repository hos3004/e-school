# Enrollments — قيد الطلاب ودورة حياته

## يملك

`enrollments` · `enrollment_status_history`

## ينشر

- `enrollments.created`
- `enrollments.activated`
- `enrollments.paused`
- `enrollments.frozen` — **السلسلة الحرجة**: يستقبل `discipline.action_applied` وينفّذ التجميد
- `enrollments.reactivation_requested`
- `enrollments.reactivated`
- `enrollments.withdrawn`
- `EnrollmentAdministrationQueries` يعيد ملخصات قيود الطالب كـDTOs للعرض الإداري.
- `EnrollmentPlacementGateway` يفعّل القيد من منسق التسكين فقط ويكتب history وAudit.

## يعتمد على

- `Students` · `Academics`.
- `Discipline` عبر استقبال حدث `discipline.action_applied` فقط (لا استدعاء مباشر).

## قواعد خاصة

- **الحساب لا يُحذف أبدًا**: التجميد (`frozen`) يمنع الوصول للكورسات والحصص فقط — البيانات والسجل يبقيان كما هما.
- أي تغيير حالة حصرًا عبر `EnrollmentStatus::canTransitionTo()` (docs/05) ويُسجَّل في `enrollment_status_history` بسبب مكتوب.
- مركز Filament يعرض الطالب والبرنامج والمستوى والمجموعات وسجل الحالة بأسماء فعلية،
  ويستدعي Actions نفسها؛ لا توجد كتابة مباشرة في القيد أو عضوية المجموعة.
- القيد المقبول لا ينتقل إلى `active` عبر الإجراء العام؛ يجب أن يمر بتسكين
  برنامج → كورس → مجموعة متاحة عبر `AssignStudentToGroupAction`.
- `UNIQUE (student_profile_id, program_id) WHERE deleted_at IS NULL` — قيد واحد نشط لكل برنامج.
