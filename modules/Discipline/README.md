# Discipline — الانضباط: المخالفات والتجمد وإعادة التفعيل

## يملك

`violation_events` · `discipline_actions` · `reactivation_requests`

## ينشر

- `discipline.violation_recorded`
- `discipline.action_applied` — ينفّذه `Enrollments` (التجميد)

## يعتمد على

- `Sessions`: يستقبل `sessions.finalized` (خاصة `NoShow`) و`sessions.cancelled`.
- `Attendance`: يستقبل `attendance.confirmed` لمخالفات الغياب.
- `AcademicReports`: يستقبل `academic_reports.session_report_submitted` (تأخر التقرير مخالفة معلم).
- `Assessments`: يستقبل `assessments.graded` لاختبار فك التجميد.

## قواعد خاصة

- **العدّاد لا يُخزَّن رقمًا أبدًا** — يُحسب من `violation_events` بنافذة شهرية (`window_key`, `is_countable`, `waived_at IS NULL`)، فلا يوجد مسار يعدّل عدد مخالفات الطالب مباشرة.
- سُلَّم التصعيد (تنبيه ← إنذار ← تجميد) وحدود 1/2/3 شهريًا كلها من `config/discipline.php` — **ممنوع** `if ($count >= 3)` في الكود.
- العفو عن مخالفة (`waived_by/at/reason`) قرار إداري موثَّق لا حذف للحدث.
- إعادة التفعيل تمر بختبار جدية (`reactivation_requests`) قبل العودة إلى `Active`.
