# CODEX FINAL BATCH — إنهاء ما تبقى من Page Completion Milestone

> **لمن يقرأ:** أنت Codex، تكمل موجة إكمال الصفحات. التكامل السابق (routes + navigation + bell)
> نفّذه المنسّق وتم التحقق منه حيًا (11 مسارًا جديدًا يعمل 200، PortalRoutesTest ‏22✓).
>
> **اقرأ بالترتيب قبل أي فعل:**
> 1. `AGENTS.md` (العقد الكامل)
> 2. `docs/page-completion-matrix.md` كاملًا — خاصة «قاعدة الصفحة المكتملة» و«UI Contracts» و«خريطة ملكية الملفات» و«قاعدة Backend Gap»
> 3. سجلات التسليم السابقة في هذا المجلد (`AGENT-4..8.md`) لتفادي إعادة عمل
>
> **قواعد غير قابلة للتفاوض:**
> - لا commit/push. لا هجرات قواعد بيانات (إن احتجت واحدة: اقترحها في سجلك ولا تنفذ).
> - كل PHP/Pest داخل Docker عبر `docker compose exec -T -e TEST_AGENT_ID=codexF app php scripts/test-isolated.php <path>` حصرًا.
> - ممنوع لمس: `routes/web.php` · `resources/js/Layouts/AppLayout.tsx` · `docs/page-completion-matrix.md` نفسه · `i18n.ts` · `types/index.d.ts`. أي حاجة فيها = مقترح diff في سجل تسليمك.
> - لا نص hardcoded إطلاقًا؛ مفاتيح ترجمة ar/en تحت بادئة مساحتك في portal.php.
> - لا Mock ولا زر بلا فعل حقيقي ولا placeholder. صفحة بدون backend أساسها = Known issue موثقة وليست DONE.
> - نفّذ المهام بالترتيب A→F. الملكيات بينها منفصلة، لكن التنفيذ المتسلسل أسلم للدمج.
>
> **حسابات الفحص** (http://localhost:8090): admin@demo.local · student1@demo.local · demo.teacher1@demo.local · guardian.*@demo.local — كلمة المرور `password`.

---

## TASK A — Scheduling / Sessions / Apology (المجموعات H + I + J)

الملكية: `modules/Sessions/src/Presentation/Filament/**` · `modules/Scheduling/src/Presentation/Filament/**` · `modules/VirtualClassroom/src/Presentation/Filament/**`
اقرأ أولًا: `docs/05-state-machines.md` — كل زر يغير حالة يجب يحترم `canTransitionTo()` ويظهر بحسب الحالة فقط.

| # | المطلوب | تفاصيل |
|---|---|---|
| A1 | **Session Operations Hub** ‏(H6) | طوّر ViewSession ليعرض: Program · Course · Group · Students · Original Teacher · Actual Teacher · Date/time + timezone · Status badge · BBB block · Recording status · Attendance metadata · Changes/Audit (من audit_log إن توفر). أي قسم بلا backend حقيقي = Known issue |
| A2 | Reschedule + Cancel actions (H7/H8) | داخل ViewSession: نموذج سبب إلزامي + موعد جديد يستدعي **نفس Action class** الذي تستخدمه postpone API (لا نسخ منطق). Cancel بنفس الأسلوب. الأزرار visible() حسب الحالات المسموحة من آلة الحالة |
| A3 | Conflict display (H9) | عند Reschedule/Create: استعلام تعارض المعلم والمجموعة والطالب وعرض قائمة التعارضات قبل الحفظ (استعلم بنفس منطق منع التعارض الموجود في الـAPI) |
| A4 | Main Calendar (H1) | صفحة Filament مخصصة ضمن Sessions navigation: تقويم شهر/أسبوع للحصص بلا اعتماديات CDN خارجية (شبكة HTML/Livewire محلية مقبولة). نقر حصة = ViewSession. فلاتر: مجموعة/معلم/حالة |
| A5 | Create Session + Recurring + Preview (H3-H5) | صفحة إنشاء فوق منطق API الحالي: حقول النموذج الحقيقية + تكرار أسبوعي (أيام الأسبوع + نطاق تاريخي) + **معاينة الحصص الناتجة قبل الحفظ** + عرض تعارضات A3 داخل المعاينة |
| A6 | Apology Admin (I1/I2) | view page لـPostponementRequestResource: تفاصيل الطلب ومَن طلبه والمتأثرين + Approve/Reject بسبب إلزامي. **الاعتماد لا يلغي الحصة** — يفتح مسار البديل فقط |
| A7 | Substitute picker + history (I3/H11) | picker معلمين مؤهلين (TeacherQualificationQueries إن قرأت منها، وإلا معلمو المجموعة) داخل Assign substitute الموجود؛ Section في ViewSession يسرد تاريخ البدلاء (original vs actual) |
| A8 | BBB block داخل Hub (J1-J4) | Create classroom / Retry / Join links حسب الدور / Guest link generate+revoke **فقط إذا كانت العقود موجودة فعليًا** في VirtualClassroom — افحص قبل بناء زر. Meeting/Recording status عرضًا |
| A9 | اختبارات آلية | Feature tests جديدة في modules/Sessions/tests/Feature: أدمن يرى Hub ‏200 · معلم آخر 403 · Reschedule بلا سبب 422 · Cancel بسبب يعمل. شغّلها معزولة وأرفق النتيجة |

## TASK B — People & Geography admin (المجموعتان C + D + E)

الملكية: `modules/{Students,Staff,Organization}/src/Presentation/Filament/**`

| # | المطلوب | تفاصيل |
|---|---|---|
| B1 | Countries + Regions management (E1/E2) | موردان Filament بسيطان CRUD على الجداول المرجعية الموجودة، navigation ضمن مجموعة الإعدادات، صلاحية settings.manage تحكم الظهور والتعديل (Policy حقيقية لا إخفاء فقط) |
| B2 | Create Teacher (D2) | سجّل getPages بـcreate page لـStaffProfileResource: ملف + ربط حساب مستخدم + عقد معلم بالحقول الموجودة فعلًا في teacher_contracts. إن نقص endpoint/action أنشئ الحد الأدنى داخل Staff module مع Policy واختبار |
| B3 | Student Profile Hub Tabs (C5, C8-C10) | ViewStudentProfile يصبح Hub بعلاقات/tabs: Programs (enrollments) · Groups (عضوياته) · Sessions (مشاركاته) — كل Tab قراءة من جداوله الفعلية بفلاتر وبحث |
| B4 | Teacher Profile Hub Tabs (D3, D7, D8, D11-D13) | نفس المنهج: Account · Programs · Groups · Availability (مع حالة الاعتماد) · Sessions · Substitute history · Apology history |
| B5 | Assign Student action (C12) | داخل Hub الطالب أو Groups: زر تسكين يستدعي منطق التسكين الحقيقي الموجود (Groups API actions) مع Modal اختيار + عرض رسائل الرفض (ازدواج/تعارض) كما يعيدها الـbackend |
| B6 | Qualifications عرض (D6) | Tab مؤهلات ومواد المعلم من بيانات موجودة (ابحث staff qualifications/specializations). إن لم يوجد جدول أساسًا: Known issue ولا تخترع |
| B7 | Freeze/status flow (C13) | تأكد أن تجميد/إعادة تنشيط الطالب من الواجهة يمر بأفعال Discipline/reactivations الحقيقية بالسبب والتدقيق — لا تبديل status مباشر يتجاوز القواعد |
| B8 | اختبارات آلية | لكل مورد جديد: happy + forbidden (دور بلا صلاحية = 403) معزولة أخضر |

## TASK C — Academics & Groups details (المجموعتان F + G)

الملكية: `modules/{Academics,Groups}/src/Presentation/Filament/**`

| # | المطلوب | تفاصيل |
|---|---|---|
| C1 | Program Details كامل (F3) | basic info · fixed/ongoing · dates · age rules · gender · geography eligibility · courses · levels · groups · teachers · admission rules — كل قسم من أعمدة/علاقات موجودة فعلًا؛ ما لا يوجد أساسه = Known issue |
| C2 | Course Details كامل (F7) | description · objectives · curriculum/topics · categories · levels · individual/group · duration · عدد الحصص · language · eligible teachers · groups · recording configuration |
| C3 | Categories/Specializations (F9) | افحص الـdomain: إن وُجدت taxonomy ابنِ إدارتها البسيطة؛ إن لم توجد Known issue دون اختراع |
| C4 | Group Details Hub + Actions (G3/G5) | Program/Course/Level/Students/Teachers/Capacity/Schedule/Upcoming/Status + Actions حقيقية: Add/Remove Student · Assign/Change Teacher · Schedule sessions — كل زر يستدعي المسارات/الأفعال الموجودة ويعرض أخطاءها مترجمة |
| C5 | اختبارات آلية | عرض التفاصيل لأدمن 200 + forbidden لدور خاطئ + Group actions happy/رفض |

## TASK D — بقايا البوابات والمحتوى

الملكية: `resources/js/Pages/Teacher/**` (N9/N10 فقط) · `resources/js/Pages/Shared/Messaging/**`

| # | المطلوب | تفاصيل |
|---|---|---|
| D1 | Submit Apology ‏(N9/I4) | في Teacher/Sessions/Show وزر من Schedule: نموذج سبب إلزامي يستدعي postpone request API الحقيقي، برسالة توضيح أن الاعتذار لا يلغي الحصة |
| D2 | My Apology Requests ‏(N10) | tab جديد في Teacher/Postponements يعرض طلباته هو (own scope) وحالاتها — منفصل عن قائمة القرارات الإدارية الموجودة |
| D3 | Messaging UX pass (P1-P3) | الصفحات المشتركة الموجودة تحت Shared/Messaging: نقل كل نصوصها إلى مفاتيح `messaging.*` ar/en، توحيد المكوّنات والحالات الثلاث، وتجهيز قائمة routes مقترحة للمدمج (لا تعدل web.php) |
| D4 | Password change wiring | تأكد أن Profile pages (student/teacher) مربوطة فعليًا بـPUT api/me/password مع validation errors معروضة — إن كانت شكلية أكملها |

## TASK E — UI QA sweep نهائي (AGENT 8 §8.2)

- مرور على **كل** صفحات البوابات الثلاث + الموارد الجديدة: RTL سليم، responsive من 375px، حالات Loading/Empty/Error ظاهرة، صفر مفاتيح خام (grep منهجي)، صفر نصوص hardcoded، أزرار كلها لها أفعال، لا 500 (جولة HTTP على قائمة المسارات كاملة بكل دور وسجل النتائج).
- أي كسر تكتشفه في ملكية غيرك: سجله Known issue بدقة file:line ولا تصلحه إلا إن كان ضمن ملكيتك.

## TASK F — اختبارات الصفحات والتقرير (AGENT 9)

- أنشئ `tests/Feature/PageCompletion/**`: لكل صفحة جديدة من هذه الدفعة اختبار happy + forbidden/404 (dataset لكل دور حيث يناسب).
- شغّل كامل: `docker compose exec -T -e TEST_AGENT_ID=codexF app php scripts/test-isolated.php tests/Feature/PageCompletion modules/*/tests` وأرفق الأرقام.
- حدّث سجل تسليمك بأسطر المصفوفة المقترحة لكل ID (Status جديد + Automated Test name).
- أنشئ مسودة `docs/page-completion-report.md`: إجمالي المطلوب، عدّ كل حالة من المصفوفة الحالية بعد تسليمك، نسبة Page coverage = DONE/REQUIRED، وقائمة الحرجة التي تحتاج تحققًا يدويًا قبل DONE. **لا تعلن 100% بنفسك** — المنسق يتحقق يدويًا ثم يقفل.

---

## صيغة سجل التسليم الإلزامية

اكتب في نهاية العمل ملفًا واحدًا `docs/page-matrix-updates/AGENT-FINAL.md`:
1. لكل Task (A-F): ما نُفذ فعليًا · ملفات · نتائج HTTP/اختبارات **بأرقام** · Known issues بمراجع دقيقة.
2. جدول مقترحات المصفوفة: ID | Status المقترح | الدليل (اختبار/HTTP).
3. اقتراحات routes/nav للمدمج إن وجدت.
