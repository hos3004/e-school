# Staff — المعلمون والموظفون

## يملك

`staff_profiles` · `teacher_contracts` · `teacher_rates` · `teacher_availability` · `teacher_leaves` · `teacher_courses`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا؛ المستحقات تستهلك أسعاره عبر العقد أدناه.

## يعتمد على

- `Identity` (حساب المستخدم).
- يعلن للآخرين: `TeacherRateResolver` — عقد عام في `Domain/Contracts` يستعمله `Payroll` دون معرفة جداوله (docs/08 §2).
- يعلن `TeacherQualificationQueries` لإرجاع معرّفات المعلمين المؤهلين للكورس والتحقق من التأهيل والجنس دون كشف نماذج Eloquent.
- يعلن `StaffAdministrationQueries` لإرجاع توافر المعلم كـDTOs إلى Hub الإدارة.

## قواعد خاصة

- **قيد قاعدة بيانات** `EXCLUDE USING gist` يمنع تداخل عقدين ساريَين لنفس المعلم.
- السعر الساري هو الذي كان فعالًا **بتاريخ الحصة** (`effective_from <= session_date < effective_to`) — لا السعر الحالي (docs/14 §2).
- مصادر 2–5 في `config/payroll.php → rate_resolution` كلها من `teacher_rates`: course · program · session_type · default.
- إتاحة المعلم `teacher_availability` مرجع تحذير التعارض في الجدولة، والإجازات المعتمدة تمنع الجدولة عليها (docs/13 §2).
- جنس المعلم والدولة والمنطقة محفوظة في `staff_profiles`؛ الإدخالات الجديدة لا تقبل منطقة لا تنتمي إلى الدولة عبر عقد الجغرافيا العام.


## سياسة أوقات الإتاحة — 2026-09-08

- أوقات المعلم تصبح متاحة للتسكين فور حفظها عندما يكون
  AVAILABILITY_TEACHER_APPROVAL=false (الإعداد الافتراضي).
  تسجل الحالة التقنية approved لتبقى عقود الجدولة متوافقة، بلا معتمد بشري.
- الإجراء المشترك SetTeacherAvailability يغطي لوحة الإدارة وبوابتي المعلم وواجهة API واللوحة القديمة؛
  يحتفظ بالتحقق من المنطقة الزمنية والتداخل وفترة السريان وسجل التدقيق.
- يستطيع المعلم صاحب صلاحية الإتاحة سحب نافذته بنفسه. إداري المؤسسة يحتاج صلاحية
  staff.contract.update. السحب يؤثر في اقتراحات التسكين القادمة فقط، ولا يلغي أي حصة محجوزة.
- سياسة المراجعة الاختيارية القديمة ما زالت مدعومة برمجيًا للاختبارات والتوافق.
  لا تظهر أزرار المراجعة وتنبيه الفترات المعلقة عند تعطيلها.

### تفعيل النوافذ السابقة

أمر الصيانة staff:activate-pending-availability يتطلب --organization=<ULID>.
بدون --apply يعرض العدد فقط، ومعه يفعّل pending فقط داخل المؤسسة المحددة.
لا يغيّر المرفوضة أو السجلات التابعة لمؤسسة أخرى أو الحصص أو المبالغ.
يسجل كل انتقال مرة واحدة بفاعل system وسبب السياسة، ولا يرسل إشعارات اعتماد.
يحترم تواريخ سريان النوافذ القائمة؛ تغيير الحالة لا يمدد أي فترة.
الأمر قابل لإعادة التشغيل ويُرفض إذا كانت سياسة المراجعة مفعلة.

Financial visibility: Staff owns staff_profiles.financials_visible, default true. TeacherFinancialVisibilityGate restricts teacher financial read abilities when hidden; administrators retain their existing access. Setting changes are organization-scoped and audited. Payroll data is not changed.
