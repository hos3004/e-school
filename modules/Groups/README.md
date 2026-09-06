# Groups — المجموعات الدراسية

## يملك

`groups` · `group_programs` · `group_teachers` · `group_memberships`

## ينشر

- `student.assigned_to_group` بعد نجاح التسكين.
- `GroupAdministrationQueries` يعيد DTOs للمجموعات المتاحة وعضويات الطالب وإسنادات المعلم.

## يعتمد على

- `Academics` (البرامج المرتبطة بالمجموعة).
- `Staff` (معلمو المجموعة الأساسيون والمساعدون).
- `Students` (العضويات).

## قواعد خاصة

- **سعة المجموعة**: `CHECK (capacity BETWEEN 1 AND 25)` على مستوى قاعدة البيانات — مجموعة بلغت 25 ترفض عضوًا جديدًا (docs/13 §10).
- عضوية واحدة نشطة لكل طالب في المجموعة: `UNIQUE (group_id, student_profile_id) WHERE left_at IS NULL`.
- حالات المجموعة: `forming | active | completed | archived` عبر Enum مع انتقالات معلنة.

### Console setup boundary
`Application/Services/ConsoleSetupService` provides organization-scoped group readiness
and identifier-based create/update/assignment/activation operations to the console.
Writes delegate to the existing Actions and Policies. Group creation and program linking
share a transaction. Program/course relationships are checked through AcademicCatalogQueries;
teacher labels use TeacherDirectoryQueries. No foreign model or cross-module join is used.
Draft capacity/start date remain optional; existing activation configuration controls readiness.
Existing program links cannot disappear through an ordinary group edit.
