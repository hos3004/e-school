# Assignments — الواجبات

## يملك

`assignments` · `assignment_submissions`

## ينشر

- `assignments.assigned`
- `assignments.submitted`
- `assignments.graded`

## يعتمد على

- `Academics` (الكورس) · `Groups` (الإسناد الجماعي) · `Staff` (المُسند) · `Students` (المُسلِّم).
- `Notifications` يستقبل أحداثه ويوزعها.

## قواعد خاصة

- تسليم واحد لكل طالب لكل واجب: `UNIQUE (assignment_id, student_profile_id)`.
- التأخير مقيد بـ `allows_late` + `late_penalty_percent`؛ يُحفظ `raw_score` ثم
  `penalty_points` ثم الدرجة النهائية، ولا يعاد تفسير الدرجات القديمة.
- حالة التسليم عبر Enum: `pending | submitted | late | graded`، والدرجة والتصحيح
  يُسجلان مع `graded_by` و`graded_at` وسبب في `audit_log`.
- الجمهور لا يُكتب يدويًا: البرنامج/المقرر/المجموعة/المعلم تُتحقق عبر عقود
  الموديولات المالكة، ويُنشأ roster الطلاب النشطين داخل معاملة إنشاء الواجب.
- تغيير الجمهور مقفول بعد وجود عمل طالب، والأرشفة SoftDelete وتُمنع عند وجود
  تسليمات غير مصححة.

## الواجهة التشغيلية

- `/admin/assignments`: أسماء فعلية، حالة تشغيلية ومؤشرات المستهدفين والتصحيح.
- `/admin/assignments/create`: برنامج ← مقرر ← مجموعة اختيارية ← معلم مؤهل.
- `/admin/assignments/{record}`: Hub الملخص والمؤشرات والتسليمات وسجل التدقيق،
  مع تصحيح واعتماد من Relation Manager بلا CRUD مباشر.

## إثبات القبول — 2026-08-24

- Assignments: 10 اختبارات/58 توكيدًا.
- التكامل مع إشعارات الواجب ومسارات بوابة الطالب: 26/120.
- PHPStan بلا أخطاء، Pint كامل، وmigration `up/down/up`.
- Browser QA عربي RTL بواجب عرض فعلي لطالبين؛ الحالة `CLIENT_READY`.
