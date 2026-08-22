# Payroll — مستحقات المعلمين (موديول مختوم — أخطر موديول في المنصة)

## يملك

`payroll_periods` · `payroll_entries` (دفتر أستاذ append-only) · `payroll_adjustments` · `staff_obligations`

## ينشر

- `payroll.entry_created`
- `payroll.entry_deferred`
- `payroll.entry_released`
- `payroll.adjustment_proposed`
- `payroll.adjustment_approved`
- `payroll.period_calculated`
- `payroll.period_approved`
- `payroll.period_paid`

## يعتمد على

- `Staff` عبر عقد `TeacherRateResolver` **فقط** — لا يلمس جداوله ولا نماذجه.
- `Sessions` / `Scheduling` عبر أحداثها: `sessions.finalized/cancelled/postponed/substitute_assigned/makeup_completed`.
- **مختوم** (`sealed_domains`): سلامة مالية — كل مسار يمر بالعقود المعلنة.

## قواعد خاصة (docs/14)

- **دفتر أستاذ لا يُعدَّل**: لا `update` ولا `delete` في المستودع أصلًا؛ التصحيح بقيدة تسوية جديدة تشير للفترة المقفلة.
- كل قيدة تحمل `rate_snapshot` بسعر **تاريخ الحصة** — تغيير سعر المعلم لاحقًا لا يمس القيود القديمة.
- منع الازدواج: `UNIQUE (session_id, staff_profile_id, entry_type)` — إعادة تشغيل المهمة لا تضاعف قيدة.
- **من اقترح التسوية لا يعتمدها**: `CHECK (approved_by IS NULL OR approved_by <> proposed_by)` على مستوى قاعدة البيانات، والملحوظة إلزامية.
- بعد `Paid` تُقفل الفترة نهائيًا؛ أي تصحيح يصبح قيدة في الفترة التالية.
- مصفوفة النتائج (`outcomes`) ومسار استنباط السعر كلاهما في `config/payroll.php` — لا منطق أسعار في الكود.
- مجموع قيود الدورة الثابتة (`course_fixed`) لا يتجاوز مبلغ الدورة — حارس يُفحص عند كل قيدة.
