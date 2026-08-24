# CODEX TASK 3 — Notifications + Recordings + Messaging UI (AGENT 7)

> اقرأ بالترتيب: `AGENTS.md` ثم `docs/page-completion-matrix.md` (UI Contracts + خريطة الملكية + المجموعات K/L/P) ثم هذا الملف.
> ممنوع commit/push. PHP/Pest داخل Docker حصرًا.

## هويتك وملكيتك الحصرية
- `modules/Notifications/src/Presentation/Filament/**`
- `modules/Recordings/src/Presentation/Filament/**`
- `modules/Messaging/src/Presentation/Filament/**`
- مكوّن الجرس المشترك `resources/js/Components/NotificationBell.tsx` (جديد — ملكك وحيدًا)
- صفحة إشعارات مشتركة `resources/js/Pages/Shared/Notifications.tsx` (جديدة لك) تستخدمها البوابات
- مفاتيح ترجمة تحت `notifications.*` و`recordings.*` و`messaging.*` في portal.php
- اختبارات: `tests/Feature/PageCompletion/Comms/*`
- سجل تسليمك: `docs/page-matrix-updates/AGENT-7.md`

## ممنوعات مطلقة
لا لمس routes/web.php / AppLayout / i18n / types؛ لا تعديل backend للموديولات إلا الحد الأدنى لتسجيل view page أو ربط Action موجود؛ لا WhatsApp جديد ولا قنوات جديدة؛ pest معزول TEST_AGENT_ID=codex7.

## السياق الجاهز
- APIs جاهزة: `api/notifications` (+unread-count/mark-as-read/mark-all-as-read/retry/cancel/attempts) · `api/recordings` (+views) · `api/conversations` (+messages) · `api/wall/*`.
- موارد Filament حالية: NotificationOutboxResource ‏(index فقط، getPages معلن) · RecordingResource (**بلا getPages → لا يولّد routes إطلاقًا** — اكتشاف موثق) · Conversation/Message/ClassWallPost/WhatsappInbound (CRUD افتراضي خام).
- جدول منح الوصول `recording_access_grants` + `GrantRecordingAccessAction` موجودان (عمل سابق) — استهلكهما ولا تعيد بناءهما.
- صلاحيات التسجيلات من المصفوفة: recording.view/view.any/grant/download/delete — الطالب وولي الأمر **بلا وصول افتراضيًا**.
- حساب أدمن للفحص: admin@demo.local / password — http://localhost:8090.

## مهامك

### T7.1 NotificationBell (L1)
مكوّن React واحد يُركّب في AppLayout لاحقًا من قبل المنسق: يستعلم unread-count، شارة عدد، dropdown بأحدث 10 مع زر «عرض الكل» يشير لصفحة الإشعارات. props نظيفة وقابلة لإعادة الاستخدام لكل بوابات.

### T7.2 صفحة الإشعارات المشتركة (L2+L3)
`Pages/Shared/Notifications.tsx`: قائمة كاملة مقروء/غير مقروء + أزرار فردية وجميع + deep link بحسب نوع الإشعار (استخدم حقل target/type إن وجد؛ إن غاب اجعل النقر يعلم كمقروء فقط وسجل Known issue). مساراتها `/student|teacher|guardian/notifications` تُقترح في سجل التسليم (المنسق يضيفها).

### T7.3 Delivery Log إداري (L4+L5+L6)
سجّل view page لـNotificationOutboxResource يعرض: القناة (in_app/email/whatsapp) · status · attempt count · last error · external id · محاولاتها بالتسلسل الزمني (attempts API). أضف فلاتر حالة وقناة، وبادج ألوان للحالة.

### T7.4 Manual Retry/Cancel (L7)
Actions داخل index/view تنادي retry/cancel APIs الحقيقية بتأكيد Modal + رسالة نجاح/فشل + تحديث فوري للصف. صلاحية الظهور وفق system.alerts.

### T7.5 Recordings إعادة هيكلة (K1-K6)
1. عرّف getPages لـRecordingResource: index + view فقط (ألغِ create/edit الافتراضي — الإنشاء يتم نظاميًا من انتهاء الحصص لا يدويًا).
2. View page = تفاصيل التسجيل + حالته وسجل مشاهداته + Actions: Grant access (Modal اختيار مستلم + مدة انتهاء عبر GrantRecordingAccessAction) + Delete بسبب إلزامي (recording.delete policy) + عرض قائمة المنح النشطة مع إلغاء منح.
3. Player/View: صفحة تشغيل محمية تمرّ بالسياسة وتنشئ رابطًا موقعًا مؤقتًا إن كانت البنية تدعم ذلك؛ إن كان الرابط الموقت غير متوفر backend-side سجل Known issue بدقة ولا تصنع رابطًا عامًا.
4. سجّل كل مشاهدة عبر `POST api/recordings/{id}/views`.

### T7.6 Messaging UX ‏(P1-P3)
صفحات Inertia مشتركة للبوابات: Inbox (`conversations/index`) · Conversation view برسائلها وإرسال جديد · New conversation بقائمة المخاطبين المسموحين حسب الدور (اعتمد على رفض الـAPI كمصدر الحقيقة واعرض الخطأ بلغة المستخدم). المسارات المقترحة `/student|teacher/guardian/messages*` → سجل التسليم. ممنوع فتح محادثة بمعرف مباشر خارج العضوية (الـAPI يرفض — تأكد أن UX يتعامل 404 بلطف).

### T7.7 Admin Monitoring تنظيم (P4+P5)
حوّل ConversationResource/MessageResource/ClassWallPostResource من CRUD خام إلى عرض إشرافي: view pages قراءة-غالبًا + Actions إشرافية الموجودة فعليًا فقط (moderate/flag إن وجدت). احترم `messaging.inbound.view`. WhatsappInbound اتركه كما هو مع فلتر حالة فقط.

## قبول صارم
لكل صفحة: 200 بالأدوار الصحيحة + رفض فعلي للأدوار الخاطئة (طالب على recordings = 403/404 مثلا) · الثلاث حالات · صفر نصوص مباشرة · Pest آلي happy+forbidden أخضر معزولًا · pint/phpstan نظيفان على ملفاتك.

## التسليم
`docs/page-matrix-updates/AGENT-7.md`: المنجز، أرقام، مقترحات مصفوفة (K1→FUNCTIONAL…)، Known issues (خصوصًا signed URLs)، اقتراحات nav/routes. ثم ملخص نهائي.
