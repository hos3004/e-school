# Attendance — الحضور والغياب

## يملك

`attendances`

## ينشر

- `attendance.computed` — الاستنباط الآلي قبل اعتماد المعلم
- `attendance.confirmed` — يعتمد الإقفال في Sessions
- `attendance.overridden` — أي تجاوز للحالة المشتقة (إلى Audit + Notifications)

## يعتمد على

- `Sessions` (المشاركة والحصة).
- `VirtualClassroom` عبر أحداث `classroom.participant_joined/left`.
- مفاتيح المخالفات تُقرأ من `config/discipline.php → countable_events`.

## قواعد خاصة

- الحالة تُستنبط آليًا من الدقائق (`deriveFromMinutes`) ثم **يعتمدها المعلم** — الاقتراح ليس قرارًا.
- **قيد قاعدة بيانات**: `CHECK (status = derived_status OR override_reason IS NOT NULL)` — لا تعديل على الحضور المشتق بلا سبب مكتوب.
- أي تعديل بعد الاعتماد يُسجَّل في `audit_log` بسبب مكتوب.
- عتبات الحضور من `config/academic.php → attendance.thresholds` — لا أرقام في الكود.
