# Sessions — الحصة (أهم كيان في المنصة)

## يملك

`sessions` · `session_status_history` · `session_participants`

## ينشر

- `sessions.scheduled`
- `sessions.confirmed`
- `sessions.started`
- `sessions.ended`
- `sessions.finalized` — **الحدث الأهم في المنصة** (Payroll · Discipline · AcademicReports · Reporting · Notifications)
- `sessions.cancelled`
- `sessions.postponed`
- `sessions.substitute_assigned`
- `sessions.makeup_completed`

## يعتمد على

- `Scheduling` (الجداول ومواعيد التلافي) · `Attendance` (يسمح بالإقفال عند تأكيده) · `VirtualClassroom` (`classroom.started/ended`) · `Groups` · `Academics` · `Staff` · `Enrollments`.

## قواعد خاصة

- **أهم آلة حالات**: أي تغيير حصرًا عبر `SessionStatus::canTransitionTo()` (docs/05) ويُسجَّل في `session_status_history`.
- **قيد قاعدة البيانات** `EXCLUDE USING gist` يمنع ازدواج حجز المعلم فيزيائيًا مهما كان التزامن؛ الحد `[)` يعني حصة تنتهي 18:00 وأخرى تبدأ 18:00 ليستا متعارضتين.
- `occurredOn` في `sessions.finalized` هو **تاريخ الحصة لا تاريخ الإقفال** — الخلط بينهما يعني قيدة في الشهر الخطأ.
- الإقفال الآلي بعد 30 دقيقة من نهاية الوقت المجدول: فُتح الفصل → `AwaitingReview`، لم يُفتح → `NoShow`.
- المعلم البديل بأجره هو، والخصم من المعلم الأساسي؛ البديل يحتاج صلاحية `session.assign_substitute` ولا يُسند لحصة `InProgress` أو نهائية.
