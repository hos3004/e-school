# AGENT E — عطل Alpine/Filament · واجهة الإدارة

> **SUPERSEDED — DO NOT EXECUTE.** سجل تاريخي؛ الطابور الحالي في `QUEUE-antigravity.md`.

> **هذه أهم حزمة منفردة في المشروع الآن.** لوحة الإدارة معطّلة: الويدجتات والنماذج
> تظهر فارغة، ولا يمكن التحقق يدويًا من أي ميزة حتى تُصلَح. كل الحزم الأخرى تنتظر هذا.

## نطاقك حصريًا — لا تخرج عنه
وكلاء آخرون يعملون بالتوازي **الآن** على `modules/{Students,Staff,Organization,Identity,Academics,Groups,Enrollments,Notifications,Integrations,Sessions,Scheduling}`.
**أي تعديل داخل `src/Domain/` أو `src/Application/` لأي موديول = تعارض. ممنوع.**

ملفاتك:
- `resources/views/**` · `resources/js/**` · `resources/css/**`
- `app/Providers/Filament/**` · `app/Filament/**`
- `vite.config.ts` · `package.json`
- `lang/**` (الترجمات الجذرية)
- `modules/*/resources/lang/**` — **فقط** لإضافة مفاتيح ترجمة ناقصة، لا لتغيير منطق

## البيئة
```bash
docker compose ps                      # الحاويات تعمل
docker compose exec -T app php artisan optimize:clear
npm run build                          # أو npm run dev
```
اللوحة على `http://localhost:8090/admin`.
PHP على الجهاز 5.6 — **كل أوامر PHP داخل Docker**: `docker compose exec -T app php artisan ...`

**~212 اختبارًا فاشلًا سابقًا لا علاقة له بك — لا تصلحها.**

---

## E1 · العطل — التشخيص الكامل (لا تُعِد اكتشافه)

ترتيب السكربتات الفعلي في الصفحة:
```
0-2  inline (darkMode · collapsedGroups · filamentData)
3    actions.js      4  notifications.js   5  schemas.js
6    support.js      7  tables.js          8  echo.js       9  app.js
10   inline loadDarkMode()                11 shim           12 livewire.js
```

**ما أُثبت بالتجربة في المتصفح — حقائق مؤكدة، ابنِ عليها:**
- `window.Alpine` موجود **قبل** تنفيذ `livewire.js` → أي أن `support.js` يبدأ Alpine بنفسه.
- المستمعات **مُسجَّلة فعلًا**. إعادة إطلاق `alpine:init` يدويًا تُسجّل
  `filamentSchema` · `filamentSchemaComponent` · `filamentActionModals` · `filamentTable`،
  ثم `Alpine.initTree(document.body)` **تجعل اللوحة تعمل بالكامل**. جُرّب ونجح.
- نسخة Alpine **واحدة فقط** على `window` — ليست مشكلة تكرار حزم.
- `deferLoadingAlpine` **غير مدعوم** في Livewire 3 — جُرّب ولم يؤثر.

**الاستنتاج:** حدث `alpine:init` يُطلق قبل أن تلتقطه مستمعات الحزم المحمَّلة بعد
`support.js`، أو تُفقد التسجيلات عند بدء Livewire لـAlpine مرة ثانية.

**الشيم الحالي** في `resources/views/filament/hooks/alpine-boot.blade.php` يُحقن عبر
`renderHook('panels::body.end')` لكنه يعمل **قبل** `livewire.js` فلا يكفي.

**المطلوب — أحد حلّين، والثاني أفضل:**
1. تشغيل الشيم بعد `livewire:initialized` فعليًا، ثم إعادة تهيئة الشجرة عند كل تحديث Livewire (`livewire:navigated` و`morph.updated`).
2. **حل جذري:** منع البدء المزدوج لـAlpine أصلًا — بضبط ترتيب تحميل الحزم في `vite.config.ts` / `AdminPanelProvider`، أو بترقية/تثبيت نسخ Filament وLivewire المتوافقة.

**التحقق إلزامي في المتصفح الحقيقي، لا بالكود وحده:**
- افتح `http://localhost:8090/admin` وسجّل الدخول.
- تأكد أن ويدجتات الصفحة الرئيسية تعرض أرقامًا (`app/Filament/Widgets/PlatformOverview.php`).
- افتح نموذج إنشاء (مثلًا Student) وتأكد أن الحقول تظهر وتُحفظ.
- افتح جدولًا وجرّب البحث والفلترة والترتيب.
- افتح modal لأي Action وتأكد أنه يفتح ويُنفِّذ.
- **console بلا أخطاء.**

**التقط لقطات شاشة قبل/بعد وضعها في `docs/agent-tasks/evidence-E/`.**

## E2 · مفاتيح ترجمة خام
القائمة الجانبية وبعض الصفحات تعرض مفاتيح مثل `students::navigation.label` بدل النص.
جِد كل المفاتيح الخام واملأها في `modules/*/resources/lang/{ar,en}/`.
**العربية هي الافتراضي وRTL** — تأكد أن الاتجاه سليم ولا يوجد نص إنجليزي مكشوف في واجهة عربية.

## E3–E6 · واجهات الإدارة الناقصة
**نفّذ هذه فقط بعد إصلاح E1** (بلا Alpine لا يمكن التحقق من أي شيء).

هذه الملفات **ملكك وحدك** — الموديولات الأخرى محجوزة لوكلاء آخرين:
- `modules/Sessions/src/Presentation/Filament/**`
- `modules/Scheduling/src/Presentation/Filament/**`
- `modules/Recordings/src/Presentation/Filament/**`

المطلوب:
- **E3 إدارة الحصص:** قائمة بفلاتر (تاريخ · معلم · مجموعة · حالة) · عرض تفصيلي · جدولة · إعادة جدولة.
- **E4 اختيار المدرس البديل:** جدول مرشحين يعرض لكل مرشح **سبب عدم الأهلية صراحةً** (غير مؤهل للمادة · جنس مخالف لقاعدة البرنامج · لديه تعارض · في إجازة). زر تجاوز إداري يفتح modal يطلب **سببًا مكتوبًا إلزاميًا**.
  يعتمد على `SubstituteCandidateFinder` الذي يكتبه مدير المشروع — إن لم تكن جاهزة، ابنِ الواجهة على التوقيع الموثَّق في `docs/phase-1-critical-modules.md` وسجّل ذلك.
- **E5 اعتماد اعتذار المعلم:** قائمة اعتذارات معلّقة · اعتماد/رفض بسبب · وبعد الاعتماد ينتقل مباشرة لاختيار البديل.
- **E6 سجل الإشعارات:** `NotificationOutboxResource` — عرض الحالة وسبب الفشل، وزر **إعادة إرسال يدوي** بصلاحية.

---

## القواعد الملزمة
- **ممنوع نص مكتوب مباشرة في الواجهة.** كل النصوص عبر ملفات الترجمة `resources/lang/{ar,en}/`.
- **الصلاحيات:** كل مورد خلف Policy و`can:`. **ممنوع `if ($user->role === 'admin')`** في أي مكان.
- **ممنوع اختراع أسماء صلاحيات.** استخدم الموجود في `docs/06-permissions-matrix.md`؛ إن نقص اسم، سجّله في تقريرك ولا تخترعه.
- **ممنوع تعديل Domain أو Application لأي موديول.** لو احتجت تغييرًا هناك، سجّله في تقريرك.
- **التواريخ:** تُخزَّن UTC وتُعرض بتوقيت المستخدم.
- **ممنوع Scope Creep:** لا موديول جديد · لا مكتبة جديدة بلا حاجة حقيقية.

## تعريف «خلصت»
اللوحة تعمل فعليًا في المتصفح · النماذج تحفظ · الجداول تبحث وتفلتر · لا أخطاء console ·
لا مفاتيح ترجمة خام · لقطات الشاشة موجودة في `docs/agent-tasks/evidence-E/`.
**وجود ملف Resource وحده لا يعني مكتمل.**

## التقرير النهائي
`docs/agent-tasks/REPORT-E.md`: سبب العطل الحقيقي وكيف حُلّ · الملفات المعدّلة ·
ما تحقّقت منه يدويًا بالضبط (بالخطوات) · أسماء الصلاحيات الناقصة إن وُجدت ·
التغييرات المطلوبة في Domain/Application التي لم تنفّذها · ما لم يُنجَز ولماذا.

**لا `git commit` ولا `git push`.**
