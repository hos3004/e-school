# CODEX TASK 2 — بوابة المعلم الكاملة (AGENT 5)

> اقرأ بالترتيب: `AGENTS.md` ثم `docs/page-completion-matrix.md` (UI Contracts + خريطة الملكية) ثم هذا الملف.
> ممنوع commit/push. كل أوامر PHP/Pest داخل Docker عبر `docker compose exec -T`.

## هويتك وملكيتك الحصرية
- `resources/js/Pages/Teacher/**`
- `app/Http/Controllers/Portal/Teacher*.php` وإضافات قراءة في `PortalData.php`
- مفاتيح ترجمة تحت `teacher.*` فقط في portal.php (ar/en)
- اختبارات جديدة: `tests/Feature/PageCompletion/Teacher/*`
- سجل تسليمك: `docs/page-matrix-updates/AGENT-5.md`

## ممنوعات مطلقة
نفس القواعد العامة: لا لمس routes/web.php / AppLayout / i18n / types / موارد Filament؛ لا mocks؛ لا إعادة كتابة الصفحات العاملة (N1/N6/N7) إلا لنقص محدد؛ كل النصوص عبر t()؛ pest معزول حصرًا بـTEST_AGENT_ID=codex5.

## السياق الجاهز
- APIs جاهزة: `api/me` · `api/sessions` (+start/end/cancel/postpone/excuse/no-show/confirm على الحصة) · `api/sessions/{id}/attendance` و`.../confirm` · `api/session-reports` · `api/staff/availability` (+decision للإدارة) · `api/staff/profiles` · `api/staff/leaves` · `api/notifications*` · `api/groups`.
- حساب معلم: `demo.teacher1@demo.local` / `password` — http://localhost:8090.
- صلاحياتك الحاكمة من المصفوفة: attendance.record ◐assigned · session_report.create ◐assigned · session.postpone.request ◐assigned — نطاقك مجموعاتك فقط، صرامة كاملة.
- مكوّنات النظام وحالات الصفحة الثلاث كما في عقد UI.

## مهامك

### T5.1 My Profile ‏(N2+N3)
`/teacher/profile`: بياناته عبر identity/me + تعديل مسموح له فقط + تغيير كلمة المرور + قسم «موادي وتأهيلي» قراءةً فقط من `api/staff/profiles/{profile}` وما يتاح منه (لا تحرير مؤهلات هنا).

### T5.2 My Groups ‏(N4)
`/teacher/groups`: مجموعاته الفعلية من إسنادات group-teachers، ولكل مجموعة: الطلاب عددًا وقائمة، الجدول الأسبوعي المختصر، رابط تفاصيل حصة قادمة.

### T5.3 My Students ‏(N5)
ضمن صفحة المجموعة (وليس قائمة عامة): طلاب مجموعاته فقط عبر عضوية المجموعة. **اختبار رفض إلزامي**: طالب من مجموعة أخرى لا يظهر ولا يفتح بمعرف مباشر (404).

### T5.4 Availability ‏(N8)
`/teacher/availability`: عرض إتاحته الحالية + إنشاء/تعديل فترات إتاحة عبر `POST api/staff/availability` مع حالة الاعتماد ظاهرة (pending/approved/rejected). لا زر اعتماد للمعلم نفسه — الاعتماد إداري فقط.

### T5.5 Submit Apology ‏(N9)
من تفاصيل حصته القادمة: زر «اعتذار» يفتح نموذج سبب إلزامي ويستدعي postpone request API بنطاق assigned. رسالة توضح أن الاعتذار لا يلغي الحصة بل يحيلها للإدارة لترتيب بديل.

### T5.6 My Apology Requests ‏(N10)
`/teacher/postponements` موجودة حاليًا لعرض ما يحتاج قراره (approve side) — أضف قسمًا/tab منفصلًا «اعتذاراتي» يعرض طلباته وحالتها (requested/alternative_proposed/fulfilled/rejected) بقراءة own-scope.

### T5.7 Notifications ‏(N11)
صفحة `/teacher/notifications` بنفس نمط بوابة الطالب (قائمة + مقروء + deep link).

### T5.8 Session Details إكمالات (N7)
في `Teacher/Sessions/Show.tsx` الموجودة: تأكد من اكتمال — Join BBB بشروط النافذة، قائمة الطلاب مع حالة حضورهم، نموذج تقرير الحصة الفوري (session_report create API) بمهلة واضحة، ورابط التسجيل حسب permission فقط (إن لم يُمنح: أخفِ الرابط لا تعطّل زرًا). أي نقص backend سجلّه ولا تختلق.

### T5.9 Navigation entries
أسطر `{ href, labelKey }` الجاهزة لكل صفحة جديدة → سجل التسليم (المنسق يدمجها).

## قبول صارم
لكل صفحة: 200 كمعلم + رفض 403/404 كطالب أو معلم آخر خارج النطاق (اختبره فعليًا) · Happy path موثق يدويًا · الثلاث حالات · صفر مفاتيح خام · Pest آلي happy+forbidden أخضر معزولًا · tsc نظيف.

## التسليم
`docs/page-matrix-updates/AGENT-5.md`: المنجز، نتائج بأرقام، مقترحات مصفوفة (N2→FUNCTIONAL…)، Known issues، اقتراحات nav/routes. ثم ملخص نهائي.
