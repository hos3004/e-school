# طابور Antigravity — Phase 1 V2 (تاريخي)

> **الحالة:** ⛔ **مُستبدَل — لا يُنفَّذ كطابور حالي.**
>
> بموجب **ADR-018** (2026-08-24) صار ترتيب التنفيذ حزم الرحلة أولًا ثم إغلاق
> الديون، والترتيب المعتمد في `docs/22-client-journey-fit-gap.md` §6.
> تقارير المهام الأربع أدناه تبقى **أدلة تاريخية** على ما نُفِّذ، ولا تبقى أوامر.
>
> **مرجع النطاق الوحيد:** `docs/phase-1-approved-scope.md`

## تحذير من الطابور القديم

ملفات `AGENT-A` إلى `AGENT-H` الموجودة بجوار هذا الملف، وكذلك
`docs/20-agent-task-packages.md`، حزم تاريخية وتقاريرها أدلة سابقة.

**ممنوع تنفيذها كطابور حالي أو دمج أوامرها مع المهام الأربع أدناه.**
Git يحتفظ بنسخة الطابور V1 قبل هذا التعديل، لذلك لم نحذف ملفات الأدلة القديمة.

## ترتيب القراءة في كل محادثة

1. `I:\e-school\AGENTS.md`
2. `I:\e-school\PROJECT_MAP.md`
3. `I:\e-school\docs\phase-1-approved-scope.md`
4. ملف المهمة الواحدة فقط من القائمة التالية
5. تقرير المهمة السابقة والمراجع التي يسميها ملف المهمة فقط

## تقسيم المسؤوليات

- **Codex:** إدارة النطاق، إصدار التكليف، مراجعة الحدود، وإعادة تشغيل أدلة القبول.
- **Antigravity:** البناء الرئيسي للحزمة الواحدة من الواجهة إلى البيانات والتكامل.
- **OpenCode:** اختبارات مستقلة ومهام بسيطة يحددها Codex بعد تسليم الحزمة؛ لا يغيّر
  قواعد أعمال أو Policies لتخضير الاختبار، ولا يعمل على الملفات نفسها بالتوازي.

كل منفذ يستخدم قاعدة اختبار معزولة. تقرير Antigravity لا يعتمد تلقائيًا؛ يُراجع قبل
تغيير حالة المهمة وفتح المحادثة التالية.

## الطابور الإلزامي

| الترتيب | المهمة | الملف | شرط البدء | تقرير الخروج | الحالة |
|---------|--------|-------|-----------|---------------|--------|
| 01 | الأداء + عزل الاختبارات + الحسابات والصلاحيات والعزل | `phase-1-v2/AGENT-ANTIGRAVITY-01-foundation-access.md` | الآن | `phase-1-v2/REPORT-ANTIGRAVITY-01.md` | **Ready** |
| 02 | الطلاب والمعلمون والدورات والتسجيل والمجموعات والتسكين | `phase-1-v2/AGENT-ANTIGRAVITY-02-academics-enrollment.md` | اعتماد 01 | `phase-1-v2/REPORT-ANTIGRAVITY-02.md` | Pending |
| 03 | الجدولة والحصة وBBB والتسجيل والحضور والتجميد والتقرير | `phase-1-v2/AGENT-ANTIGRAVITY-03-sessions-bbb-attendance.md` | اعتماد 02 | `phase-1-v2/REPORT-ANTIGRAVITY-03.md` | Pending |
| 04 | الإشعارات والتواصل والتكليفات والتقارير والإقفال E2E | `phase-1-v2/AGENT-ANTIGRAVITY-04-communications-e2e.md` | اعتماد 03 | `phase-1-v2/REPORT-ANTIGRAVITY-04.md` | Pending |

## قواعد مشتركة لا يعاد التفاوض عليها

- كل بنود `phase-1-approved-scope.md` إلزامية، بما فيها البنود التي وصفها العميل سابقًا
  بعبارة «لو بدأنا بها يكون جيد».
- BigBlueButton فقط. لا Zoom.
- كل شخص حساب مستقل وواجهة حسب الصلاحية؛ لا أنظمة Auth متعددة ولا فحص اسم دور.
- المالية كلها خارج المرحلة الأولى.
- لا `git commit` ولا `git push` إلا بأمر المستخدم.
- PHP/Composer/Artisan داخل Docker فقط.
- الاختبارات على البيئة المعزولة؛ لا قاعدة development ولا قاعدة مشتركة بين الوكلاء.
- لا موديول/جدول/abstraction جديد إذا كان المالك الحالي يستطيع تنفيذ المتطلب.
- لا Gate وهمي في الاختبار، ولا Policy موسعة، ولا fake completion.
- `Model/API/Resource` ليس إنجازًا بلا route وواجهة وصلاحية وحفظ ونتيجة ظاهرة.
- كل مهمة تكتب تقريرها الصادق، ثم Codex يعيد تشغيل الأدلة قبل اعتمادها.

## البرومبت الافتتاحي للمحادثة الأولى

انسخ النص التالي إلى Antigravity:

```text
هذه مهمة تنفيذ معتمدة وليست طلب إعداد خطة فقط.

مجلد المشروع:
I:\e-school

اقرأ بالترتيب وبالكامل:
1) I:\e-school\AGENTS.md
2) I:\e-school\PROJECT_MAP.md
3) I:\e-school\docs\phase-1-approved-scope.md
4) I:\e-school\docs\agent-tasks\phase-1-v2\AGENT-ANTIGRAVITY-01-foundation-access.md

لا تنفذ ملفات AGENT-A إلى AGENT-H ولا docs/20-agent-task-packages.md؛ هي تاريخية.
افحص الواقع على القرص ثم اكتب خطة قصيرة وابدأ التنفيذ داخل Task 01 مباشرة.
لا تتوقف لطلب اعتماد الخطة، ولا تبدأ Task 02، ولا تدّع الإنجاز مع فشل أو جزء غير موصول.
لا commit ولا push. استخدم Docker لكل PHP/Composer/Artisan، ولا تشغّل الاختبارات على
قاعدة التطوير أو قاعدة مشتركة. في النهاية اكتب التقرير المحدد في ملف المهمة بالحقيقة.
```

## البرومبت الافتتاحي للمحادثة الثانية

```text
هذه مهمة تنفيذ معتمدة وليست طلب إعداد خطة فقط. اعمل داخل I:\e-school.
اقرأ كاملًا: AGENTS.md، PROJECT_MAP.md، docs/phase-1-approved-scope.md،
docs/agent-tasks/phase-1-v2/REPORT-ANTIGRAVITY-01.md، ثم
docs/agent-tasks/phase-1-v2/AGENT-ANTIGRAVITY-02-academics-enrollment.md.
لا تنفذ AGENT-A..H أو docs/20-agent-task-packages.md. افحص الواقع، اكتب خطة قصيرة،
وابدأ Task 02 مباشرة. لا تبدأ Task 03، لا commit/push، واستخدم بيئة الاختبار المعزولة.
في النهاية اكتب REPORT-ANTIGRAVITY-02.md بالحقيقة ولا تدّع إتمام جزء غير موصول.
```

## البرومبت الافتتاحي للمحادثة الثالثة

```text
هذه مهمة تنفيذ معتمدة وليست طلب إعداد خطة فقط. اعمل داخل I:\e-school.
اقرأ كاملًا: AGENTS.md، PROJECT_MAP.md، docs/phase-1-approved-scope.md،
docs/agent-tasks/phase-1-v2/REPORT-ANTIGRAVITY-02.md، ثم
docs/agent-tasks/phase-1-v2/AGENT-ANTIGRAVITY-03-sessions-bbb-attendance.md.
لا تنفذ AGENT-A..H أو docs/20-agent-task-packages.md. افحص الواقع، اكتب خطة قصيرة،
وابدأ Task 03 مباشرة. لا تبدأ Task 04، لا commit/push، واستخدم بيئة الاختبار المعزولة.
في النهاية اكتب REPORT-ANTIGRAVITY-03.md، وافصل Fake/contract عن اختبار BBB الحقيقي.
```

## البرومبت الافتتاحي للمحادثة الرابعة

```text
هذه مهمة تنفيذ معتمدة وليست طلب إعداد خطة فقط. اعمل داخل I:\e-school.
اقرأ كاملًا: AGENTS.md، PROJECT_MAP.md، docs/phase-1-approved-scope.md، وتقارير
REPORT-ANTIGRAVITY-01.md وREPORT-ANTIGRAVITY-02.md وREPORT-ANTIGRAVITY-03.md تحت
docs/agent-tasks/phase-1-v2/، ثم اقرأ
docs/agent-tasks/phase-1-v2/AGENT-ANTIGRAVITY-04-communications-e2e.md.
لا تنفذ AGENT-A..H أو docs/20-agent-task-packages.md. افحص الواقع، اكتب خطة قصيرة،
وابدأ Task 04 مباشرة. لا commit/push، واستخدم بيئة الاختبار المعزولة.
لا تعلن Phase 1 مكتملة مع test skipped أو تكامل Fake أو صلاحية/عزل غير مثبت؛ اكتب
REPORT-ANTIGRAVITY-04.md بنتائج الإقفال الحقيقية.
```
