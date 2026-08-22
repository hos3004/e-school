# Groups — المجموعات الدراسية

## يملك

`groups` · `group_programs` · `group_teachers` · `group_memberships`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا.

## يعتمد على

- `Academics` (البرامج المرتبطة بالمجموعة).
- `Staff` (معلمو المجموعة الأساسيون والمساعدون).
- `Students` (العضويات).

## قواعد خاصة

- **سعة المجموعة**: `CHECK (capacity BETWEEN 1 AND 25)` على مستوى قاعدة البيانات — مجموعة بلغت 25 ترفض عضوًا جديدًا (docs/13 §10).
- عضوية واحدة نشطة لكل طالب في المجموعة: `UNIQUE (group_id, student_profile_id) WHERE left_at IS NULL`.
- حالات المجموعة: `forming | active | completed | archived` عبر Enum مع انتقالات معلنة.
