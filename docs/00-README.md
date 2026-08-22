# وثائق معمارية منصة E-School

هذه الوثائق هي مصدر الحقيقة للمشروع. الكود يتبعها، لا العكس.
أي قرار يخالف ما هنا يحتاج تعديل الوثيقة أولًا وتسجيل السبب في `18-ADRs.md`.

---

## ترتيب القراءة

### للمطوّر أو الوكيل الجديد

| # | الملف | لماذا |
|---|-------|-------|
| 1 | [`01-PRD.md`](01-PRD.md) | ما الذي نبنيه، لمن، وما حجمه |
| 2 | [`03-domain-model.md`](03-domain-model.md) | مفردات المشروع ومعانيها |
| 3 | [`05-state-machines.md`](05-state-machines.md) | دورات الحياة الأربع الحاكمة |
| 4 | [`08-module-boundaries.md`](08-module-boundaries.md) | من يكلّم من، وكيف |
| 5 | [`17-coding-standards.md`](17-coding-standards.md) | كيف نكتب |
| 6 | [`21-definition-of-done.md`](21-definition-of-done.md) | متى تقول "خلصت" |

### لمن يبني موديولًا بعينه

اقرأ الستة أعلاه، ثم حزمة موديولك في [`20-agent-task-packages.md`](20-agent-task-packages.md)،
ثم الوثيقة المتخصصة إن وُجدت (`13` للجدولة، `14` للمستحقات، `12` للإشعارات، `11` للمزوّدين).

---

## الفهرس الكامل

### الأساس
- [`01-PRD.md`](01-PRD.md) — وثيقة المتطلبات
- [`02-scope-and-phases.md`](02-scope-and-phases.md) — النطاق والمراحل وما هو خارج النطاق
- [`client-answers.md`](client-answers.md) — محضر إجابات العميل والقرارات المشتقة منها

### النمذجة
- [`03-domain-model.md`](03-domain-model.md) — الكيانات والمفاهيم
- [`04-entity-relationship-model.md`](04-entity-relationship-model.md) — العلاقات
- [`05-state-machines.md`](05-state-machines.md) — آلات الحالات
- [`07-database-schema.md`](07-database-schema.md) — الجداول والفهارس والقيود

### المعمارية
- [`08-module-boundaries.md`](08-module-boundaries.md) — حدود الموديولات
- [`09-domain-events.md`](09-domain-events.md) — سجل الأحداث
- [`10-api-contracts.md`](10-api-contracts.md) — عقود الواجهة البرمجية
- [`11-provider-interfaces.md`](11-provider-interfaces.md) — واجهات المزوّدين الخارجيين
- [`12-notification-architecture.md`](12-notification-architecture.md) — محرّك الإشعارات

### قواعد العمل
- [`13-scheduling-rules.md`](13-scheduling-rules.md) — الجدولة والتأجيل والإلغاء
- [`14-payroll-rules.md`](14-payroll-rules.md) — المستحقات بالأمثلة
- [`06-permissions-matrix.md`](06-permissions-matrix.md) — من يرى ماذا ومن يعدّل ماذا

### الجودة
- [`15-security-model.md`](15-security-model.md) — الأمن والخصوصية
- [`16-testing-strategy.md`](16-testing-strategy.md) — استراتيجية الاختبار
- [`17-coding-standards.md`](17-coding-standards.md) — معايير الكود
- [`21-definition-of-done.md`](21-definition-of-done.md) — تعريف الإنجاز

### التنفيذ
- [`18-ADRs.md`](18-ADRs.md) — سجل القرارات المعمارية
- [`19-agent-dependency-graph.md`](19-agent-dependency-graph.md) — ترتيب البناء
- [`20-agent-task-packages.md`](20-agent-task-packages.md) — حزم عمل الوكلاء
- [`PROGRESS.md`](PROGRESS.md) — حالة البناء الحالية

---

## قواعد التوثيق

- الوثيقة تصف **قرارًا**، لا احتمالات. لو القرار لم يُتخذ بعد، يُكتب صراحةً
  تحت عنوان **مفتوح** مع اسم صاحب القرار وموعد الحسم.
- كل رقم يخص سياسة المدرسة يُذكر مع مكانه في `config/` وليس كقيمة في الكود.
- عند تغيير قاعدة عمل: عدّل الوثيقة، وسجّل ADR، وعدّل ملف `config/` — بهذا الترتيب.
