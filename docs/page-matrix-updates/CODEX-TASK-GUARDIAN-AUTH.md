# CODEX TASK 4 — بوابة ولي الأمر + إصلاحات Public/Auth (AGENT 6)

> اقرأ بالترتيب: `AGENTS.md` ثم `docs/page-completion-matrix.md` (UI Contracts + خريطة الملكية + المجموعتين A وO) ثم هذا الملف.
> ممنوع commit/push. PHP/Pest داخل Docker حصرًا.

## هويتك وملكيتك الحصرية
- `resources/js/Pages/Guardian/**`
- `resources/js/Pages/Auth/RegisterStudent.tsx` و`ApplicationStatus.tsx` (إصلاح فقط — Login/Forgot/Reset/TwoFactor **ممنوع لمسها**، هي TESTED)
- `app/Http/Controllers/Auth/PublicStudentRegistrationController.php`
- `app/Http/Controllers/Portal/Guardian*.php` + قراءات PortalData
- مفاتيح ترجمة تحت `guardian.*` و`auth.register.*` في portal.php
- اختبارات: `tests/Feature/PageCompletion/Guardian/*`
- سجل تسليمك: `docs/page-matrix-updates/AGENT-6.md`

## قاعدة صريحة غير قابلة للنقاش
**لا تُبنى أي واجهة تتيح لولي الأمر قراءة محادثات الطالب الخاصة مع المعلم أو الزملاء** مهما كانت صلته به. ولا رؤية بيانات طالب آخر إطلاقًا.

## ممنوعات عامة
لا لمس routes/web.php / AppLayout / i18n / types (اقترح diff في سجلك)؛ لا هجرات قواعد بيانات؛ لا mocks؛ pest معزول TEST_AGENT_ID=codex6.

## السياق الجاهز
- صفحات O العاملة: `/guardian` (Dashboard أبناء موثقون) · `/guardian/children/{id}/attendance` · `.../reports` — كلها TESTED عبر PortalRoutesTest وتعبر حدود المؤسسات بـ404.
- حسابات ولي أمر ديمو موجودة (`guardian.*@demo.local`) لكن **تحقق أن أحدها مرتبط بابن موثق**؛ إن لم يوجد اربط تجريبيًا عبر SQL مباشر في قاعدة التطوير فقط وأوثق الأمر في سجلك.
- APIs جاهزة: `api/guardians/links*` · `api/notifications*`.
- نموذج التسجيل العام الحالي: `PublicStudentRegistrationController` فيه مشاكل موثقة أدناه.
- حسابات الفحص: admin@demo.local · student1@demo.local — كلمة المرور `password` — http://localhost:8090.

## مهامك

### T6.1 إصلاح Public Registration ‏(A5)
في `RegisterStudent.tsx` والمتحكم:
1. حقلا country/city نص حر حاليًا → استبدلهما بقائمتي دولة/منطقة من البيانات المرجعية الفعلية (جداول countries/regions عبر endpoint قراءة جديد خفيف داخل ملكيتك `GET /register/geo` عام بلا مصادقة مع cache دقيقة واحدة — أو استخدم ما توفره GeographyQueries عبر متحكمك). ممنوع النص الحر.
2. إصلاح fallback المؤسسة الوهمي: `config('app.default_organization_id', '01H7X...')` — اجعل المؤسسة تُحدد من الإعداد الفعلي الوحيد الموجود (اقرأ organizations الموجودة: خذ الأولى صراحة مع config override قابل، وفشل واضح 500 مُسجل لو لا مؤسسة بدل ULID مكسور).
3. تفرد البريد: افحص `registration_applications` غير المستبعدة وليس users فقط.
4. Rate limit على POST التسجيل (throttle middleware 5/دقيقة لكل IP) + رسالة خطأ مترجمة.
5. Validation واضح لكل حقل برسائل عربية/إنجليزية من ملفات الترجمة.
6. بعد النجاح: redirect لصفحة Submitted مع رقم متابعة.

### T6.2 Application Status آمن ‏(A7)
بدل id الخام: أنشئ `follow_up_code` منطقيًا بدون هجرة؟ **لا هجرات مسموحة لك** — الحل ضمن هذا القيد: استخدم الـULID نفسه لكن أضف throttle على المسار (10/دقيقة) وعرض محدود الحقول (اسم مجزأ + حالة + تاريخ). إن ظننت أن رمز متابعة منفصل ضروري فسجّله Known issue مقترحًا هجرة للمدمج — لا تنفذها.

### T6.3 Child Overview ‏(O3)
صفحة `/guardian/children/{studentId}` نظرة شاملة للابن الموثق فقط: بطاقة حالة (نشط/مجمّد)، نسبة حضوره الشهرية، عدد حصصه القادمة، آخر تقريرين. كل رقم من قاعدة البيانات عبر قراءات PortalData. طالب غير موثق/مؤسسة أخرى = 404 (نفس سلوك attendance الموجود).

### T6.4 Child Schedule ‏(O6)
`/guardian/children/{studentId}/schedule`: جدول الحصص القادمة للأبناء (مسموح دائمًا وفق docs/06 §4) بنفس نمط attendance الموجود.

### T6.5 Guardian Notifications ‏(O7)
صفحة `/guardian/notifications` بنفس نمط البوابات الأخرى فوق api/notifications (القراءة والتعليم كمقروء لنفسه فقط).

### T6.6 Navigation entries
أسطر nav جاهزة لكل صفحة جديدة → سجل التسليم (children tabs ضمن dashboard، notifications).

## قبول صارم
- تسجيل عام: happy path ينشئ طلبًا فعليًا في registration_applications بحالة pending ويظهر لأدمن في `/admin/registration-applications` — اختبره يدويًا ووثقه.
- rate limit يرد 429 بعد التجاوز — اختبره.
- guardian لا يرى إلا أبناءه: اختبار رفض 404 لطالب آخر (آلي + يدوي).
- الثلاث حالات + صفر مفاتيح خام + Pest آلي أخضر معزولًا (happy + cross-org forbidden) + pint/phpstan نظيفان على ملفاتك.

## التسليم
`docs/page-matrix-updates/AGENT-6.md`: المنجز، أرقام الاختبارات وHTTP، مقترحات مصفوفة (A5→TESTED، O3/O6→FUNCTIONAL…)، Known issues (follow_up_code migration proposal)، اقتراحات nav/routes. ثم ملخص نهائي.
