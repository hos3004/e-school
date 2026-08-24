# المرحلة الأولى الحرجة — مصفوفة التنفيذ

> يُحدَّث أثناء العمل لا في النهاية.
> **هذه مصفوفة حالة تاريخية/تشغيلية وليست مصدر نطاق.** أي حصر أو تأجيل أدناه يخالف
> `docs/phase-1-approved-scope.md` يُعدّ مستبدلًا. لا يستخدم Antigravity هذا الملف
> كطابور؛ الطابور الحالي في `docs/agent-tasks/QUEUE-antigravity.md`.
> **قاعدة الحالة:** لا يُكتب `Implemented` إلا إذا عملت الميزة **من الواجهة حتى قاعدة
> البيانات**: حُفظت البيانات، وعملت الصلاحيات، وعُولجت الأخطاء. وجود Model أو Route
> أو API وحده **لا يعني شيئًا**. و`Tested` تتطلب اختبارًا آليًا **واختبارًا يدويًا**
> موثَّقًا حيث يمكن التحقق يدويًا.

**الحالات المسموحة:** `Missing` · `Partial` · `Implemented` · `Tested` · `Blocked` ·
`Deferred to Phase 1.5`

**النطاق الحالي:** المسارات السبعة وكل إضافات العميل في
`docs/phase-1-approved-scope.md`. أصبحت Assignments والمراسلات وشات الصف والانضباط
والتجميد والتقارير التشغيلية جزءًا إلزاميًا. المالية وZoom هما المستبعدان صراحةً.

الجداول A–H أدناه تحتفظ بما أُنجز وما كان مفقودًا وقت كتابتها؛ لا تمنح وكيلًا حق
تأجيل بند معتمد الآن، ولا تعني حالة `Implemented` قبولًا دون إعادة تحقق.

---

## CLIENT UPDATE — 2026-08-22

دفعة معلومات رسمية جديدة من العميل غيّرت قواعد عمل كانت موثقة سابقًا.
**المصدر الكامل والمُلزِم:** `docs/client-answers.md` §CLIENT UPDATE — 2026-08-22.
هذا ملخص تنفيذي لما تغيّر:

| # | التغيير | الأثر |
|---|---------|-------|
| 1 | **القبول قبل التوزيع** — Sign Up ليس قبولًا. حساب / طلب تسجيل / ملف طالب / قيد / عضوية مجموعة خمسة كيانات منفصلة. ممنوع التوزيع قبل اعتماد الطلب. | كيان `registration_applications` جديد. `EnrollmentStatus` يبقى لقيد البرنامج ولا يُكرَّر. |
| 2 | **الجغرافيا قاعدة عمل** — الدولة والمنطقة بيانات مرجعية منظمة، تُستخدم في أهلية البرامج لا في العرض فقط. | جدولا `countries` و`regions` + `program_eligibility`. ممنوع اسم دولة داخل الكود. |
| 3 | **أهلية البرنامج** — دول · مناطق · عمر · جنس · اعتماد يدوي. البرامج المجانية: الاعتماد اليدوي هو الافتراضي. | نموذج قابل للتوسع بلا Rules Engine ضخم. |
| 4 | **اسم المستخدم إلزامي** ومقترح آليًا ببادئة المؤسسة من الإعدادات. الربط ببريد أو هاتف. | `users.username` + مولّد اقتراحات + استعادة عبر القناة المتاحة. |
| 5 | **إتاحة المعلم والطالب** — أيام · أوقات · timezone · تكرار · استثناءات · اعتماد اختياري. | Dependency للجدولة ومنع التعارض واختيار البديل. |
| 6 | **مطابقة الخانات** — Query يعيد الخانات المتوافقة بين الطالب والمعلم. واجهة الحجز الذاتي **مؤجلة للمرحلة 1.5**. | |
| 7 | **مرونة التصنيف الأكاديمي** — Categories · Tracks · Levels كيانات وعلاقات، والكورس قد ينتمي لأكثر من تصنيف. عمر وجنس ونوع حصة ونوع برنامج. | ممنوع أعمدة ثابتة داخل `courses`. |
| 8 | **نوع البرنامج** — `fixed_duration` (بداية ونهاية) أو `ongoing` (بلا نهاية). | لا Billing الآن، لكن النموذج لا يمنع التسعير المستقبلي. |
| 9 | **قواعد المطابقة عامة** — تطابق الجنس والعمر والفردية قواعد برنامج، **ممنوع** ربطها باسم «Quran» في الكود. | |
| 10 | **طلب تغيير المعلم** — الطالب يطلب، الإدارة تعتمد. Contract + Status الآن، الواجهة في 1.5. | ممنوع أن تفترض Architecture أن الإسناد ثابت. |
| 11 | **اعتذار المعلم** — بسبب مكتوب، يعتمده المشرف، و**لا يُلغي الحصة**. يبدأ البحث عن بديل. | `teacher_apologies` + ربطها بمسار البديل. |
| 12 | **متابعة الاعتذارات** — نافذة متحركة 30 يومًا. الثانية: تحذير. الثالثة: تصعيد. **لا Suspend ولا Termination آلي إطلاقًا.** | `config/discipline.php` → `teacher`. |
| 13 | **قاعدة الطالب rolling 30 days** — العدّاد لا يُصفَّر أول الشهر. | `counter_window = rolling`. القاعدة الشهرية القديمة **بطلت**. |
| 14 | **حضور الطالب من BBB** — تخزين join/leave لكل مشارك واحتساب المدة والانقطاعات. **Join واحد لا يعني حاضرًا.** | Phase 1 V2: توصيل الأحداث وتطبيق سياسات الحضور الكاملة للطالب والمعلم. |
| 15 | **التزام المعلم بالمدة** — first_join · last_leave · total_connected · late · early_leave. مؤشرات مراقبة **بلا عقوبات آلية**. | |
| 16 | **مهلة تقرير الحصة 60 دقيقة** قابلة للضبط، وبعدها `late`. | التقرير الفوري والتقارير التشغيلية ضمن Phase 1 V2؛ Advanced BI فقط خارجها. |
| 17 | **خصوصية المراسلات** — **ولي الأمر ممنوع** من رؤية محادثة الطالب مع المعلم حتى لو مرتبطًا به. | اختبار تفويض يمنع الوصول عبر URL/API مباشرة. |
| 18 | **صلاحيات التسجيلات** — الطالب وولي الأمر **لا يريان** التسجيل افتراضيًا. المعلم يرى حصته بلا تنزيل. الإتاحة بمنحة يدوية لها انتهاء. | `recording_access_grants`. القيم القديمة في `config/recordings.php` **بطلت**. |
| 19 | **دخول الإدارة والمشرف** لأي حصة بصلاحية server-side. `classroom.observe` · `classroom.moderate`. | لا اعتماد على ظهور زر. |
| 20 | **ضيف بدعوة آمنة** — token لحصة واحدة، له انتهاء، قابل للإلغاء، Attendee افتراضيًا، والرابط يُولَّد ديناميكيًا. | ممنوع moderator password في الرابط. |
| 21 | **أحداث إشعار جديدة** (21 حدثًا) عبر ثلاث قنوات، وفشل القناة لا يُفشل العملية. | Outbox الموجود، لا إرسال داخل Controllers. |

**ما تم تنفيذه من هذه الدفعة حتى الآن (إعدادات وقواعد عمل):**

| الملف | التغيير | الحالة |
|-------|---------|--------|
| `docs/client-answers.md` | قسم CLIENT UPDATE المُلزِم | Implemented |
| `config/discipline.php` | `counter_window = rolling` + 30 يومًا · سُلَّم اعتذارات المعلم + أقفال «لا عقاب آلي» | Implemented |
| `config/recordings.php` | إغلاق الرؤية الافتراضية + قسم `grants` | Implemented |
| `config/scheduling.php` | `apology` · `substitute` · `session_report` · `teacher_duration_compliance` · `availability` | Implemented |
| `config/admission.php` | **جديد** — التسجيل الذاتي · حالات الطلب · الأهلية · المطابقة · اسم المستخدم | Implemented |
| `Discipline\...\DisciplineWindow` | دعم النافذة المتحركة عبر `rangeEndingAt()` | Implemented |

---

## توزيع المسؤوليات — لا يعدّل وكيلان نفس الملف

| الوكيل | يملك هذه المسارات حصريًا | ممنوع عليه |
|--------|---------------------------|------------|
| **A** الطلاب والتسجيل والجغرافيا | `modules/Organization/**` (الجغرافيا) · `modules/Students/**` عدا الإتاحة · `modules/Staff/**` عدا الإتاحة · `modules/Identity/**` | Scheduling · Sessions |
| **B** الأكاديمي | `modules/Academics/**` · `modules/Groups/**` · `modules/Enrollments/**` | Sessions · Availability |
| **C** الإتاحة | `modules/Staff/.../TeacherAvailability*` · `modules/Students/.../StudentAvailability*` · `modules/Scheduling/.../Availability*` | `StaffProfile.php` · `StudentProfile.php` · Sessions |
| **D** الإشعارات | `modules/Notifications/**` · `modules/Integrations/**` | منطق Sessions — يستخدم الأحداث والعقود فقط |
| **E** واجهة الإدارة | عطل Alpine · `resources/views/**` · `app/Providers/Filament/**` · `modules/Sessions/.../Presentation/Filament/**` | Domain و Application لأي موديول |
| **F** الصلاحيات والخصوصية | `modules/AccessControl/**` · `modules/Messaging/.../Policies/**` · بذرة الصلاحيات · `docs/06-permissions-matrix.md` | تعديل منطق الأعمال لإخفاء فشل |
| **G** الاختبارات | `tests/Feature/Scenarios/**` (السيناريوهات الـ12) | تعديل كود الإنتاج لتمرير اختبار |
| **Claude** | `modules/Sessions/{Domain,Application}` · `modules/Scheduling/{Domain,Application}` · `modules/VirtualClassroom/**` · `modules/Recordings/{Domain,Application}` · التكامل النهائي | — |

**تخصيص أسماء الهجرات لمنع التصادم:** A = `2026_08_22_11*` · B = `2026_08_22_12*` ·
C = `2026_08_22_13*` · D = `2026_08_22_14*` · Claude = `2026_08_22_15*`.

---

## 1. الطلاب والتسجيل والجغرافيا — الوكيل A

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| A1 | جدولا `countries` و`regions` + بذرة الدول العربية ومحافظات مصر | Implemented | `modules/Organization` | GeographyData + migrate/rollback | لم يُفتح في المتصفح |
| A2 | عقد قراءة عام للجغرافيا (`GeographyQueries`) يعيد DTOs | Implemented | `GeographyQueries` + DTOs | GeographyQueriesTest | — |
| A3 | `registration_applications` + `RegistrationStatus` enum بحالاته السبع | Implemented | `modules/Students` | RegistrationApplicationTest | — |
| A4 | تدفق التقديم: draft → submitted → under_review → accepted/rejected → waiting_assignment | Implemented | Actions + API + Filament | Students: 32/70 ضمن المجموعة | لم تُنفّذ رحلة متصفح كاملة |
| A5 | **منع التوزيع قبل القبول** — فحص في Action وقيد في القاعدة | Partial | `StudentAdmissionQueries` + Enrollments Action | الرفض قبل الكتابة مثبت؛ fixture B يحجب الاختبار الكامل | — |
| A6 | إنشاء `StudentProfile` عند القبول فقط | Implemented | `AcceptRegistrationApplicationAction` | قبول/رفض + تعطيل المسار القديم | — |
| A7 | `users.username` إلزامي + فريد + بريد أو هاتف | Partial | Identity migration/request/action | RegisterUserTest | دخول Filament ما زال بالبريد |
| A8 | مولّد اقتراحات اسم المستخدم ببادئة من `organization_settings` | Implemented | `UsernameSuggester` + `OrganizationSettingQueries` | UsernameSuggesterTest | — |
| A9 | كشف الطلبات المكررة لنفس الشخص | Implemented | Registration Actions | اختبار البريد/الهاتف والـflag | — |
| A10 | الدولة والمنطقة على `student_profiles` و`staff_profiles` | Implemented | Students + Staff + Filament | اختبارات موجهة + migration | لم يُفتح في المتصفح |
| A11 | جنس المعلم على `staff_profiles` (شرط المطابقة) | Implemented | Staff profile/request/action/query | Staff: 5/13 ضمن المجموعة | — |
| A12 | **تأهيل المعلم للمواد** (`teacher_courses`) — Dependency لاختيار البديل | Implemented | `TeacherQualificationQueries` | TeacherQualificationQueriesTest | — |
| A13 | بحث وفلترة بالدولة والمنطقة والحالة | Partial | Students/Staff Filament | فلاتر منفذة | بحث الأسماء القديمة يحتاج Identity Query |
| A14 | استعادة الحساب عبر القناة المتاحة | Partial | `modules/Identity` | البريد فقط | الهاتف/WhatsApp غير منفّذ |

## 2. الأكاديمي — الوكيل B

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| B1 | `program_categories` + `program_tracks` كيانات مستقلة بأسماء حرة | Missing | — | — | — |
| B2 | كورس ينتمي لأكثر من تصنيف (many-to-many) | Missing | — | — | — |
| B3 | نوع البرنامج `fixed_duration` / `ongoing` + `start_date` / `end_date` | Missing | `programs` | — | — |
| B4 | نوع الحصة على الكورس: `individual` / `group` / `both` | Missing | `courses` | — | — |
| B5 | العمر الأدنى والأقصى + الجنس المستهدف على البرنامج/الكورس | Missing | — | — | — |
| B6 | `program_eligibility` — دول · مناطق · عمر · جنس · اعتماد يدوي | Missing | — | — | — |
| B7 | مقيِّم الأهلية `EligibilityEvaluator` + تجاوز بصلاحية وسبب | Missing | — | — | — |
| B8 | قاعدة تطابق جنس المعلم والطالب كإعداد برنامج لا اسم برنامج | Missing | — | — | — |
| B9 | تفاصيل الكورس الكاملة (~25 حقلًا) | Partial | `modules/Academics` | — | — |
| B10 | المجموعات ككيان مستقل + الهيكل الهرمي | Partial | `modules/Groups` | — | — |
| B11 | قواعد صحة العلاقات + تسجيل التجاوز | Missing | — | — | — |
| B12 | توزيع الطالب على برنامج/مجموعة بعد القبول فقط | Missing | `modules/Enrollments` | — | — |

## 3. الإتاحة والمطابقة — الوكيل C

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| C1 | توسعة `teacher_availability`: تكرار · استثناءات · حالة اعتماد | Partial | `modules/Staff` | — | — |
| C2 | `student_availability` بنفس البنية | Missing | — | — | — |
| C3 | فترات عدم الإتاحة (إجازات) مدمجة في حساب الإتاحة | Partial | `teacher_leaves` | — | — |
| C4 | معالجة Timezone صحيحة (تخزين UTC · عرض محلي) | Partial | — | — | — |
| C5 | **Query الخانات المتوافقة** طالب × معلم | Missing | — | — | — |
| C6 | العقد العام `AvailabilityQueries` لاستخدام Scheduling والبديل | Missing | — | — | — |
| C7 | اعتماد إتاحة المعلم عند تفعيل الإعداد | Missing | — | — | — |
| C8 | واجهة الحجز الذاتي المتقدمة | **Deferred to Phase 1.5** | — | — | — |

## 4. الحصص والجدولة والبديل وBBB — **Claude**

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| S1 | `original_teacher_id` + `actual_teacher_id` على الحصة | Missing | `modules/Sessions` | — | — |
| S2 | `session_substitutions` سجل دائم لكل استبدال | Partial | هجرة مطبَّقة | — | — |
| S3 | `AssignSubstituteTeacherAction` + التجاوز الإداري بسبب | Partial | مكتوب وغير مختبَر | — | — |
| S4 | `SubstituteCandidateFinder` بكل شروط الترشيح | Partial | ينقص الجنس والبرنامج والإتاحة | — | — |
| S5 | `teacher_apologies` + اعتماد المشرف | Missing | — | — | — |
| S6 | **الاعتذار لا يُلغي الحصة** — يفتح البحث عن بديل | Missing | — | — | — |
| S7 | سُلَّم الاعتذارات بنافذة 30 يومًا متحركة (تحذير ثم تصعيد بلا عقاب آلي) | Missing | — | — | — |
| S8 | حالات الحصة وآلة الانتقال | Implemented | `SessionStatus` | — | — |
| S9 | إنشاء حصص متكررة من RRULE + Preview | Partial | `modules/Scheduling` | — | — |
| S10 | منع تعارض المعلم | Implemented | قيد EXCLUDE | — | — |
| S11 | منع تعارض الطالب والمجموعة | Missing | — | — | — |
| S12 | منع الحجز المزدوج المتزامن (قفل/قيد) | Missing | — | — | — |
| S13 | صلاحية دخول ديناميكية للفصل (لا روابط دائمة) | Missing | — | — | — |
| S14 | دخول الإدارة/المشرف: `classroom.observe` · `classroom.moderate` | Missing | — | — | — |
| S15 | دعوة ضيف آمنة (token · حصة واحدة · انتهاء · إلغاء) | Missing | — | — | — |
| S16 | معالجة أحداث join/leave من BBB وتخزينها | Partial | `classroom_events` | — | — |
| S17 | مؤشرات حضور الطالب (first_join · last_leave · مدة · انقطاعات) | Missing | — | — | — |
| S18 | مؤشرات التزام المعلم (تأخر · خروج مبكر · مدة متصلة) | Missing | — | — | — |
| S19 | `recording_access_grants` + منع الرؤية الافتراضية | Missing | — | — | — |
| S20 | تكامل BBB من طرف لطرف (Skippable بلا credentials) | Partial | `BigBlueButtonProvider` | — | — |
| S21 | مهلة تقرير الحصة 60 دقيقة + حدث `report.due`/`report.late` | Missing | — | — | — |
| S22 | عقد طلب تغيير المعلم (بيانات + حالات) | Missing | — | — | — |
| S22b | واجهة طلب تغيير المعلم | **Deferred to Phase 1.5** | — | — | — |

## 5. الإشعارات — الوكيل D

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| D1 | محرّك موحّد + Outbox | Partial | `OutboxDispatcher` | — | — |
| D2 | داخل النظام (جرس · غير مقروء · صفحة) | Missing | — | — | — |
| D3 | البريد عبر Laravel Mail/SMTP مستقل عن المزوّد | Missing | — | — | — |
| D4 | **اختبار حقيقي يثبت وصول الإشعار إلى Mail transport** | Missing | — | — | — |
| D5 | **WhatsApp Cloud API outbound** — قوالب · لغة · بارامترات | Missing | — | — | — |
| D6 | تطبيع رقم الهاتف | Missing | — | — | — |
| D7 | `external_message_id` · status · failure reason | Partial | — | — | — |
| D8 | إعادة المحاولة + إعادة إرسال يدوي للإدارة | Partial | `RetryNotificationAction` | — | — |
| D9 | القوالب متعددة اللغات | Missing | — | — | — |
| D10 | الأحداث الـ21 المحدَّثة | Missing | — | — | — |
| D11 | فشل القناة لا يُفشل العملية الأصلية | Partial | — | — | — |
| D12 | WhatsApp inbound إلى صندوق الإدارة فقط | Partial | `whatsapp_inbound` | — | — |

## 6. الصلاحيات والخصوصية — الوكيل F

| # | المتطلب | الحالة | ملفات | آلي | يدوي |
|---|---------|--------|-------|-----|------|
| F1 | **ولي الأمر ممنوع من محادثة الطالب مع المعلم** | Missing | `modules/Messaging` | — | — |
| F2 | اختبار تفويض يمنع الوصول المباشر عبر URL/API | Missing | — | — | — |
| F3 | `classroom.observe` · `classroom.moderate` في المصفوفة والبذرة | Missing | — | — | — |
| F4 | صلاحيات التسجيلات ومنح الوصول | Missing | — | — | — |
| F5 | صلاحية إنشاء دعوة الضيف | Missing | — | — | — |
| F6 | توحيد أسماء الصلاحيات المخترعة | Partial | 10 أسماء صُوِّبت بالبذر | — | — |
| F7 | اختبارات عزل المؤسسات | Missing | — | — | — |

## 7. واجهة الإدارة — الوكيل E

| # | المتطلب | الحالة | ملاحظات |
|---|---------|--------|---------|
| E1 | **عطل Alpine/Filament — الويدجتات والنماذج فارغة** | **Blocker** | التشخيص أدناه |
| E2 | مفاتيح ترجمة خام في القائمة | Missing | |
| E3 | إدارة الحصص: عرض · جدولة · إعادة جدولة | Partial | |
| E4 | **اختيار المدرس البديل من الواجهة** مع عرض سبب عدم الأهلية | Missing | يعتمد على S4 |
| E5 | اعتماد اعتذار المعلم من الواجهة | Missing | يعتمد على S5 |
| E6 | سجل الإشعارات وإعادة الإرسال اليدوي | Missing | يعتمد على D8 |

### تشخيص خلل Alpine — للوكيل E

ترتيب السكربتات الفعلي في الصفحة:
```
0-2  inline (darkMode · collapsedGroups · filamentData)
3    actions.js      4  notifications.js   5  schemas.js
6    support.js      7  tables.js          8  echo.js       9  app.js
10   inline loadDarkMode()                11 shim           12 livewire.js
```

**ما أُثبت بالتجربة في المتصفح:**
- `window.Alpine` موجود **قبل** تنفيذ `livewire.js` → `support.js` يبدأ Alpine بنفسه.
- المستمعات مُسجَّلة فعلًا: إعادة إطلاق `alpine:init` يدويًا تُسجّل
  `filamentSchema` و`filamentSchemaComponent` و`filamentActionModals` و`filamentTable`
  ثم `Alpine.initTree(document.body)` **تجعل اللوحة تعمل بالكامل**.
- نسخة Alpine واحدة فقط على `window`.
- `deferLoadingAlpine` غير مدعوم في Livewire 3.

**الاستنتاج:** الحدث يُطلق قبل أن تلتقطه مستمعات الحزم المحمَّلة بعد `support.js`،
أو تُفقد التسجيلات عند بدء Livewire لـAlpine مرة ثانية.

**الشيم الحالي** في `resources/views/filament/hooks/alpine-boot.blade.php` يُحقن عبر
`renderHook('panels::body.end')` لكنه يعمل قبل `livewire.js` فلا يكفي.
**المطلوب:** تشغيله بعد `livewire:initialized` ثم إعادة تهيئة الشجرة عند كل تحديث
Livewire، أو حل جذري يمنع البدء المزدوج لـAlpine.

## 8. السيناريوهات الحرجة — الوكيل G

لا تُعتبر المرحلة الأولى مكتملة قبل مرور هذه الاثني عشر:

| # | السيناريو | الحالة |
|---|-----------|--------|
| 1 | تسجيل عام → جغرافيا → اقتراح اسم مستخدم → تقديم → مراجعة → قبول → **غير موزَّع بعد** → توزيع → الحساب يعمل | Missing |
| 2 | متقدم من منطقة غير مؤهلة → يظهر للإدارة → لا تفعيل تلقائي → رفض بسبب | Missing |
| 3 | إنشاء «فصول إلكترونية للأطفال» → لغة عربية → مستوى → مجموعة → معلم → طلاب | Missing |
| 4 | قرآن فردي: طالبة > 12 → إتاحة → **معلمات مؤهلات فقط** → بلا تعارض | Missing |
| 5 | إتاحة معلم + إتاحة طالب → خانات متوافقة → جدولة → منع التعارض | Missing |
| 6 | المعلم A يعتذر → المشرف يعتمد → **الحصة لا تُلغى** → مرشحون → B يُختار → original=A · actual=B → إشعارات → B مشرف BBB | Missing |
| 7 | اعتذاران في 30 يومًا متحركة → تحذير. الثالث → تصعيد. **المعلم يبقى نشطًا.** | Missing |
| 8 | المعلم Moderator · الطالب Attendee · المشرف المصرَّح يدخل · غير المصرَّح ممنوع · token الضيف لحصة واحدة وينتهي | Missing |
| 9 | تسجيل: Admin يرى · المشرف المصرَّح يرى · المعلم يرى حصته بلا تنزيل · الطالب لا يرى · منحة يدوية تتيح · الإلغاء/الانتهاء يمنع | Missing |
| 10 | تغيير حصة → in-app + email + WhatsApp. فشل WhatsApp: **الحصة محفوظة** · التسليم `failed` · إعادة المحاولة تعمل | Missing |
| 11 | محادثة طالب↔معلم: ولي الأمر عبر URL/API → **ممنوع**. المشرف المصرَّح → مسموح | Missing |
| 12 | دخول وخروج متكرر للطالب · معلم يتأخر ويخرج مبكرًا → المؤشرات محسوبة ومخزَّنة صحيحة | Missing |

---

---

## سجل التحقق — ما أثبته مدير المشروع بنفسه

> **قاعدة:** لا يُسجَّل بند هنا بناءً على تقرير منفّذ. كل سطر أدناه أعاد المدير
> تشغيله أو فتحه في المتصفح بنفسه، والناتج المذكور هو الناتج الحقيقي.

| البند | المنفّذ | كيف تُحقِّق منه | الناتج الفعلي | الحالة |
|-------|---------|------------------|----------------|--------|
| E1 عطل Alpine/Filament | Antigravity | فتح `/admin` في المتصفح | 29/29 مكوّن مهيّأ · صفر خطأ console · الويدجتات تعرض أرقامًا حقيقية | **Tested** |
| E2 إزالة النصوص المباشرة | Antigravity | `grep` على `navigationGroup` | 0 نص مباشر متبقٍّ · 45 موردًا يستخدم `getNavigationGroup()` | **Implemented** |
| E3/E4 مسارات الحصص | Antigravity | `route:list --path=admin` | `admin/sessions` و`admin/sessions/{record}` و`admin/session-participants` مسجَّلة وترد 302 | **Implemented** |
| F6 توحيد أسماء الصلاحيات | Antigravity | فتح `/admin/students` | الصفحة تفتح وتعرض 5 طلاب بجدول وبحث — **الـ403 الذي كان يقفل المشروع زال** | **Tested** |
| F1/F2 خصوصية ولي الأمر | Antigravity | تشغيل الاختبار | ولي أمر يملك `guardian.view` يطلب المحادثة عبر HTTP → **403** · مشرف بـ`message.moderate` → مسموح | **Tested** |
| F7 عزل المؤسسات | جولة الاعتماد | اختبارات HTTP موجهة | Students وStaff يمنعان cross-tenant وتفلتر القوائم؛ ما زالت بقية الموارد غير مغطاة | **Partial** |
| D الإشعارات كاملة | Codex | تقرير + تحقق يدوي عبر Mailpit | 86 اختبارًا · 433 توكيدًا · Pint نظيف · PHPStan صفر على ملفاته | **Tested** (WhatsApp بـHttp::fake لا بحساب Meta حقيقي) |
| S1 المعلم الأصلي | Claude | psql بعد الهجرة | العمود موجود · صفر صف فارغ · التعبئة الرجعية صحيحة | **Tested** |
| S5/S6/S7 اعتذار المعلم | Claude | تشغيل الاختبار | 7 اختبارات · 23 توكيدًا — **الاعتماد لا يُلغي الحصة** · اعتذار عمره 31 يومًا لا يُحتسب · التصعيد بلا عقوبة آلية | **Tested** |
| جولة Codex/OpenCode المستهدفة | مدير المشروع | Docker + PostgreSQL معزول | 186 ناجحًا وفشل عداد Seeder واحد، ثم نجح الاختبار المصحح بـ21 توكيدًا · Audit/Attendance/AcademicReports 78/227 | **Verified with limits** |
| الجولة الكاملة | مدير المشروع | `pest --configuration=phpunit.agent-pm.xml --compact` | **686 ناجحًا · 75 فاشلًا · 4502 توكيدًا** | **Blocked for merge** |
| ملكية الجداول | مدير المشروع | الجولة الكاملة | جدولان من Sessions مسجلان؛ ثلاثة جداول Academics الجديدة ما زالت خارج الخريطة | **Partial** |

### عوائق مفتوحة مُثبتة

| العائق | الأثر | المالك |
|--------|-------|--------|
| موديول Notifications يستورد موديول Integrations | خرق حدود — اختبار معمارية فاشل | يحتاج قرارًا: نقل ChannelGateway إلى shared أو استثناء موثَّق |
| موديول Reporting يستورد موديول Payroll | خرق حدود — اختبار معمارية فاشل | سابق لهذه الدفعة |
| Enrollments يستورد Group model/action مباشرة | خرق نموذج وحدود طبقات — إخفاقان معماريان | يحتاج Contract/DTO يملكه Groups بدل Eloquent/action عابر الموديولات |
| Attendance بلا tenant/object scope كامل | قد يكشف أو يعدّل سجلات حضور خارج النطاق عند منح الصلاحيات الرسمية | يلزم query scope + policy scope واختبارات أدوار حقيقية |
| Assessments يفشل 6 مسارات بـ403 بعد إزالة Gates الوهمية | أسماء الصلاحيات وobject-level ownership غير موحدين | قرار تصنيف صلاحيات ثم تفويض attempt/submit/grade |
| أحداث الحصة لا تحمل معرّفات المستلمين | يمنع تشغيل إشعارات الحصة الحقيقية | Claude — بدأ بإضافة teacherUserId في أحداث الاعتذار |


## سجل التحديثات

| الوقت | التغيير |
|-------|---------|
| 12:05 | إنشاء المصفوفة · توزيع المسؤوليات · توثيق تشخيص Alpine |
| 16:10 | سجل التحقق — الـ403 زال واللوحة تعمل · F1/F2 مثبتتان · D مكتملة · اعتذار المعلم مختبَر |
| 13:20 | **CLIENT UPDATE 2026-08-22** — إعادة بناء المصفوفة على المتطلبات الجديدة · تحديث أربعة ملفات إعداد وإنشاء `config/admission.php` · دعم النافذة المتحركة · توزيع سبعة وكلاء بأسماء هجرات مخصصة |
| 18:45 | جولة اعتماد مستقلة — إصلاح تفويض Codex/OpenCode · migrate/rollback ناجحان · 686/75 في الجولة الكاملة · منع الدمج للإنتاج |
