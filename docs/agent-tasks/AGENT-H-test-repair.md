# AGENT H — إصلاح الاختبارات الفاشلة في الموديولات غير المشغولة

> **SUPERSEDED — DO NOT EXECUTE.** سجل تاريخي؛ الطابور الحالي في `QUEUE-antigravity.md`.

> مهمة **مستقلة تمامًا**. منفّذون آخرون يعملون على المستودع الآن،
> ونطاقك مفصول عنهم بالكامل. التزم بقائمة الموديولات أدناه حرفيًا.

## الوضع

المستودع فيه **~212 اختبارًا فاشلًا** من أصل 688. أغلبها ديون قديمة من موجات بناء
سابقة، لا أعطال جديدة. مهمتك: تصفية هذا الدين في **الموديولات التي لا يعمل عليها أحد**.

## نطاقك — هذه الموديولات وحدها

```
modules/Assessments
modules/Attendance
modules/AcademicReports
modules/Audit
modules/Discipline
modules/Guardians
modules/Reporting
```

**47 ملف اختبار.** لا تخرج عن هذه السبعة.

### ممنوع منعًا باتًا الاقتراب من

| المسار | السبب |
|--------|-------|
| `modules/Notifications/**` · `modules/Integrations/**` | منفّذ آخر يعمل عليها الآن |
| `modules/Sessions/**` · `modules/Scheduling/**` · `modules/VirtualClassroom/**` · `modules/Recordings/**` | مدير المشروع يعمل عليها الآن |
| `modules/Students/**` · `modules/Staff/**` · `modules/Organization/**` · `modules/Identity/**` | منفّذ آخر يبدأ عليها الآن |
| `modules/Academics/**` · `modules/Groups/**` · `modules/Enrollments/**` | منفّذ آخر يبدأ عليها الآن |
| `modules/AccessControl/**` · `modules/Messaging/**` | منفّذ آخر يبدأ عليها الآن |
| `tests/` في الجذر | منفّذ آخر |
| `config/**` · `docs/**` | مدير المشروع |

إن كان سبب فشل اختبار في ملف خارج نطاقك: **سجّله في تقريرك ولا تعدّله.**

---

## البيئة — اقرأ هذا القسم كاملًا قبل أي أمر

PHP على الجهاز 5.6 ولا يعمل. **كل أوامر PHP داخل Docker.** الحاويات تعمل بالفعل.

**حرج:** منفّذان آخران يشغّلان اختبارات على نفس الحاوية في هذه اللحظة.
إن استخدمت قاعدة الاختبار المشتركة ستُفسد نتائجهم ونتائجك معًا وتحصل على فشل وهمي.

**لذلك اعمل على قاعدة اختبار معزولة خاصة بك.** انسخ ملف الإعداد:

```bash
cp phpunit.xml phpunit.agent-h.xml
```

ثم في `phpunit.agent-h.xml` غيّر سطرًا واحدًا فقط:

```xml
<env name="DB_DATABASE" value="eschool_testing_agent_h" force="true"/>
```

(يوجد ملف `phpunit.agent-d.xml` في الجذر كمثال جاهز على نفس الفكرة — انظره.)

وشغّل اختباراتك هكذا **دائمًا**:

```bash
docker compose exec -T app vendor/bin/pest --configuration=phpunit.agent-h.xml modules/Assessments/tests
```

**ممنوع تمامًا** `php artisan test` بلا `--filter`، وممنوع تشغيل المجموعة كاملة.

أوامر مفيدة:
```bash
docker compose exec -T postgres psql -U eschool -d eschool -c "\d attendances"
docker compose exec -T app vendor/bin/pint modules/Assessments
```

---

## الطريقة — موديول واحد في المرة

اعمل بالترتيب التالي، **ولا تنتقل لموديول قبل إغلاق سابقه**:

`Audit` → `Guardians` → `Attendance` → `Assessments` → `AcademicReports` → `Discipline` → `Reporting`

(الترتيب من الأبسط للأعقد — يبني ثقتك في البيئة أولًا.)

لكل موديول:
1. شغّل اختباراته وحدها على إعدادك المعزول.
2. اقرأ رسالة الفشل الحقيقية — لا تخمّن.
3. أصلح **السبب في كود الإنتاج**.
4. أعد التشغيل حتى يمر الموديول.
5. `pint` على الموديول.
6. اكتب سطرًا في تقريرك ثم انتقل للتالي.

---

## القاعدة الحاكمة — اقرأها مرتين

> **أصلح سبب الفشل، لا الاختبار.**

**ممنوع منعًا باتًا:**
- حذف اختبار أو تعطيله
- `markTestSkipped` أو `->skip()` للتحايل
- تخفيف التوكيد (تغيير `toBe` إلى `toBeTruthy` مثلًا)
- تغيير القيمة المتوقعة لتطابق الناتج الخاطئ
- التقاط استثناء وابتلاعه لتمرير الاختبار

إن كان **الاختبار نفسه** خاطئًا (يتوقع سلوكًا يخالف `CLAUDE.md` أو
`docs/client-answers.md`)، فصحّحه **واشرح في تقريرك لماذا كان خاطئًا وما المرجع
الذي استندت إليه**. هذا استثناء نادر، لا مخرج عام.

إن لم تعرف السبب الحقيقي بعد محاولتين جادتين: **اترك الاختبار فاشلًا** واكتب في
تقريرك رسالة الفشل الكاملة وما جرّبته. الفشل الموثَّق أفضل من إصلاح كاذب.

---

## مثال حقيقي من هذا المستودع

`modules/Attendance/tests/Unit/RecordAttendanceActionTest.php` يفشل هكذا:

```
Failed asserting that exception of type "Illuminate\Database\QueryException"
matches expected exception "Shared\Support\BusinessRuleViolation".

SQLSTATE[23503]: Foreign key violation:
insert or update on table "attendances" violates foreign key constraint
"attendances_session_participant_id_foreign"
Key (session_participant_id)=(01M0...) is not present in table "session_participants".
```

**القراءة الصحيحة:** الاختبار يتوقع أن يرفض `RecordAttendanceAction` مشاركًا غير
موجود **بخطأ عمل واضح** (`BusinessRuleViolation`)، لكن الكود يمرّر المعرّف إلى
قاعدة البيانات مباشرة فينفجر بخطأ FK خام.

**الإصلاح الصحيح:** تحقّق من وجود المشارك داخل
`modules/Attendance/src/Application/Actions/RecordAttendanceAction.php:54`
قبل الإدراج، وارمِ `BusinessRuleViolation` بمفتاح ترجمة مناسب.

**الإصلاح الخاطئ (ممنوع):** تغيير الاختبار ليتوقع `QueryException`.

هذا هو النمط المتوقع في أغلب الفشل: **الكود يعتمد على قاعدة البيانات لفرض قاعدة
عمل، بينما القاعدة يجب أن تُفرض في طبقة التطبيق برسالة مترجمة.**

---

## القواعد الملزمة (من `CLAUDE.md`)

- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*` من موديول آخر.
  التواصل بأحداث Domain أو عقود عامة أو Query Services تعيد DTOs.
  إن كان الإصلاح يتطلب كسر هذا الحد — **لا تكسره**، سجّله في تقريرك.
- **لا أرقام سياسة في الكود.** أي رقم يخص سياسة المدرسة يُقرأ من `config/`.
  لو وجدت `if ($count >= 3)` داخل موديول فهذه مخالفة — أصلحها بالقراءة من الإعدادات.
- **الحالات Enums لا strings**، مع `canTransitionTo()`.
- **لا حذف** — `SoftDeletes`.
- **ممنوع نص مكتوب مباشرة** في رسالة خطأ أو واجهة. كل النصوص عبر
  `modules/<Module>/resources/lang/{ar,en}/`.
- **الصلاحيات عبر Policies و`can:`** — ممنوع `if ($user->role === ...)`.
- **التواريخ UTC** في التخزين.
- `declare(strict_types=1)` · `final` · تعليقات عربية تشرح **لماذا** لا ماذا.

## ممنوع

- `git commit` و `git push` — مهما كان السبب.
- إنشاء موديول أو جدول أو خدمة جديدة. مهمتك إصلاح لا بناء.
- تشغيل `migrate:fresh` على قاعدة التطوير `eschool` — اعمل على قاعدة الاختبار المعزولة فقط.
- لمس أي ملف خارج الموديولات السبعة.

---

## التقرير — `docs/agent-tasks/REPORT-H.md`

ابدأ الملف من أول موديول ولا تؤجّله للنهاية. جدول:

| الموديول | قبل | بعد | ما أُصلح فعلًا | ملفات الإنتاج المعدَّلة |
|----------|-----|-----|-----------------|--------------------------|

ثم قسمان:

**١. فشل لم أستطع إصلاحه** — رسالة الفشل الكاملة + ما جرّبته + سبب توقفي.

**٢. أسباب خارج نطاقي** — أي فشل سببه ملف خارج الموديولات السبعة.
اذكر الملف والسطر والسبب. **لا تعدّله.** هذا القسم مهم جدًا لمدير المشروع.

**اكتب الأرقام الحقيقية.** المدير سيعيد تشغيل الاختبارات ويقارن.
