# سجل تقدّم البناء — e-school Platform

## 2026-09-15 — تحرير نصوص رسائل واتساب من الإعدادات

- قسم الإشعارات في `/manage/settings` صار يعرض كل قوالب واتساب بلغة المدرسة: عنوان ونص قابلان للتحرير، وأزرار تُدرج المتغيرات المتاحة، ومعاينة حيّة للرسالة كما تصل.
- الحفظ ينشئ نسخة خاصة بالمؤسسة عبر مسارات `notification-templates` القائمة؛ القالب العام لا يُمس. «استعادة النص الأصلي» تحذف نسخة المؤسسة فيعود `TemplateRenderer` إلى القالب العام.
- الخادم يرفض أي متغير لا يعرفه القالب العام لنفس الحدث، لأن الحدث لا يوفّر غيره والمتغير الزائد يُسقط الإرسال عند أول حدث حقيقي.
- بلا صلاحية جديدة وبلا هجرات: البوابة `settings.manage` و`NotificationTemplatePolicy` كما هما، والتدقيق عبر تسجيل المتحكّم القائم.

## 2026-09-15 — Green API WhatsApp integration (SCHOOL-WEB)

- Candidate branch codex/green-api-integration-20260915 in an isolated server worktree. Platform admin can enter the Green API shard URL, instance ID and token in /manage/settings; activation verifies the instance and stores the token encrypted with a redacted audit record.
- Sending uses each organization's saved connection. A separate action registers authenticated Green API webhooks for inbound messages, delivery statuses and instance state. Inbound replays are idempotent; delivery status is attached to the notification outbox by provider message ID.
- Tests use an isolated PostgreSQL database and fake provider responses. Live provider verification and test delivery require the administrator to save their token in the UI after deployment. No token was entered into chat or repository.
- Official API references: https://green-api.com/en/docs/api/account/GetStateInstance/ ; https://green-api.com/en/docs/api/account/SetSettings/ ; https://green-api.com/en/docs/api/sending/SendMessage/ ; https://green-api.com/en/docs/api/receiving/technology-webhook-endpoint/ .



## 2026-09-11 — العودة من الفصل المباشر إلى مكان كل دور

- بعد انتهاء الحصة أو الخروج من BigBlueButton يعود الطالب إلى واجهته، وينتقل المعلم إلى صفحة حصته عند نموذج التقرير وأول حقوله يحمل التركيز.
- رابط الانضمام صار يحمل `logoutURL` خاصًا بكل دور، على مستوى `join` لا `create` لأن قيمة `create` واحدة لكل المشاركين.
- `JoinRequest` اكتسب `returnUrl` اختياريًا يمرّره `EnterClassroom`؛ الموديول لا يعرف مسارات الواجهات، وتُشتق الوجهة من اسم المسار الداخل فتخدم بوابة `/learn` والمسارات السابقة معًا.
- وسيط استعلام لا مرساة: المزوّد يُلحق سبب الخروج بالرابط نصيًا فيتلف أي `#fragment`؛ فُحصت الحالتان على الخادم الفعلي.
- رابط الطالب اليدوي بقي بلا وجهة عمدًا لأن صاحبه بلا جلسة على المنصة. بلا صلاحية جديدة وبلا هجرات.
- [فحوص ومعاينة التغيير](uat/2026-09-11-classroom-return-destination.md).

## 2026-09-11 — رابط دخول الطالب اليدوي من صفحة حصة المعلم

- المعلم ينسخ من قائمة حضور الحصة رابطًا لكل طالب ويرسله يدويًا، ليحضر الطالب عند تعذّر دخوله لحسابه.
- الرابط مسار عام موقّع مربوط بحصة ومشارك بعينهما، ويدخل الفصل بمعرّف مستخدم الطالب نفسه فيظل الحضور منسوبًا له.
- تُفرض عند فتحه حالة الحصة ونافذة الدخول وتجميد القيد وسحب الدعوة وعزل المؤسسة، وينتهي التوقيع بانتهاء نافذة الحصة.
- كل استخدام يُسجَّل في `audit_log`، ومفتاح `virtual-classroom.student_link.enabled` يبطل الروابط المنسوخة سلفًا لا التوليد وحده.
- بلا صلاحية جديدة وبلا هجرات؛ استُخرج منطق الدخول المشترك إلى `EnterClassroom` دون تغيير سلوك البوابتين القائمتين.
- [فحوص ومعاينة التغيير](uat/2026-09-11-teacher-student-join-link.md).

## 2026-09-10 — تغيير الموعد الدائم للحصص بطلب المعلم

- صلاحية جديدة للمعلم `schedule.change.request` لاقتراح موعد أسبوعي جديد لقالب جدول مسند إليه، مع سبب إلزامي وتحقق كامل من قواعد الجدولة قبل حفظ الطلب.
- الموعد الجديد لا يسري إلا بقبول **كل** طلاب الكورس (`schedule.change.respond`)؛ رفض طالب واحد أو سحب المعلم أو انقضاء المهلة ينهي الطلب دون تغيير الجدول.
- المشرف والإدارة يُخطَران في قسم الإشعارات عند الطلب وعند النتيجة، ولا يضيف المسار خطوة اعتماد إدارية جديدة.
- عند اكتمال القبول يُطبَّق التغيير عبر `UpdateScheduleAction`: الحصص داخل نافذة القفل تبقى، وما بعدها يُعاد توليده بالموعد الجديد مع التدقيق.
- [فحوص ومعاينة التغيير](uat/2026-09-10-teacher-schedule-change.md).

## 2026-09-09 — إظهار الحسابات المالية لكل معلم

- نُشر على SCHOOL-WEB خيار إداري لكل معلم لإظهار أو إخفاء الحسابات المالية مع سبب مدقق؛ الحماية تشمل الصفحات وواجهات API، وتظل الإدارة قادرة على قراءة الحسابات.
- عدادات مستقلة للحصص في بوابتي المعلم، مع حدود شهره المحلي. لا تغيير في الأجور أو الدفتر.
- [فحوص ومعاينة التغيير](uat/2026-09-09-teacher-financial-visibility.md).

## 2026-09-09 — استكمال الملف وأجور المعلمين والتسكين المعتمد

- نُشر واختُبر فعليًا على SSH SCHOOL-WEB: استكمال إجباري للملف، إعدادات مدد وأجور المعلمين بالجنيه، وحفظ السعر وقت الحصة.
- نُفذ تسكين 18 طالبًا في 49 موعدًا أسبوعيًا بتوقيت إسطنبول؛ 25 دقيقة للكبار و35 للأطفال، وربط أصحاب المواعيد الناقصة بمعلميهم بحالة انتظار.
- تقرير الفحوص والنسخ الاحتياطية: `docs/uat/2026-09-09-profile-pay-placement.md`.


## 2026-09-08 — تبسيط الإتاحة والشعار والدول على SCHOOL-WEB

- نُشرت الإتاحة الفورية دون مراجع بشري، مع الحراس والتدقيق وعدم تغيير الحصص القائمة.
- وُحد عرض شعار تيلي كورس أكاديمي في الموقع والبوابات والطباعة.
- أضيفت الدول المطلوبة وإدخال الدولة بالكتابة، مع الحفاظ على المعرّفات القديمة.
- الاختبارات والبناء والمعاينة على السيرفر في بيئة مستقلة؛ [تفاصيل التحقق والنشر](uat/2026-09-08-ux-simplification.md).

## 2026-09-06 — نشر التحديثات المدمجة

- نُشر الدمج `e4c0866` بنجاح بعد أخذ نسخة احتياطية والتحقق من استعادتها.
- نجحت الهجرات وفحوص التشغيل، وأصبح الموقع متاحًا بالتحديثات المدمجة.
- ملخص الحالة: `docs/uat/2026-09-06-production-deployment.md`.

## 2026-09-06 — دمج تحديثات الخادم مع التسكين المحلي

- دُمج تاريخ `db916795` المحلي مع `ee6bd590` المنشور و`1afaac7a` للحضور،
  مع حفظ commits الجانبين؛ حُل تعارض نصي واحد في هذا السجل.
- أُصلح توافق DTO المشاركين مع المستهلكين السابقين، وتحويل قيمة الحصة من
  الراتب إلى حساب صحيح دون float، مع سبع حالات اختبار للمال.
- نجحت الجولة الشاملة: **1239 اختبارًا و7613 تأكيدًا**، وتشمل المعمارية.
  نجحت الهجرات up/down على بيانات اصطناعية قائمة، وPint وTypeScript والبناء.
- PHPStan على 91 ملف مصدر متغير والاختبار المالي الجديد بلا أخطاء؛ العام
  ما زال يحتوي 848 مشكلة سابقة. لا تجاهلات جديدة.
- كانت هذه نتيجة مراجعة الدمج قبل النشر؛ نُشر الحضور وشُغّلت هجراته لاحقًا
  في دورة النشر الموثقة أعلاه. تقرير المراجعة:
  `docs/uat/2026-09-06-server-merge-review.md`.

## 2026-09-06 — الحضور والاعتذار والتأجيل والبديل

- رُبطت أحداث BBB بفترات حضور مجمعة داخل الموعد الرسمي، مع عتبات 75% و40%
  وإبقاء نافذة فتح الرابط بعد النهاية 15 دقيقة بلا احتساب حضور.
- أُضيف اعتماد كشف المعلم وتجاوزه بسبب مدقق بعد إثبات دخوله الفعلي من أحداث
  BBB، والغياب الثالث في نافذة 30 يومًا
  يجمّد القيد تلقائيًا، بينما غياب الطرفين لا ينشئ مخالفة.
- أضيف اعتذار الطالب بمهلة ساعة؛ الفردي يصبح بعذر، والجماعي يبقى قائمًا حتى لو
  اعتذر جميع الطلاب ويحافظ المعلم على استحقاقه.
- اعتذار المعلم يعتمد فورًا ويطلق بحث بديل فوريًا وكل خمس دقائق، ويُغلق عند
  الإسناد. التأجيل بمهلة ساعة، بين الطالب والمعلم بلا موافقة الإدارة.
- فُصل الأثر المالي حسب نوع العقد، وأضيفت إشعارات للطرفين والإشراف والإدارة.

## 2026-09-04 — رحلة المعلم للحصة والمجموعات والطلاب

- أتيح تقرير الحصة وتقييم الطلاب منذ انتقال الحصة إلى `in_progress`، مع بقاء
  منع التقرير قبل البدء والتحقق من إسناد المعلم في الخلفية.
- يتحول الحضور بعد الحفظ إلى عرض واضح للحالات المحفوظة، مع زر تعديل دائم وسبب
  إلزامي عند تغيير حالة مرصودة وتدقيق التعديل.
- أضيفت تفاصيل مجموعة المعلم بمواعيد الحصص القادمة والطلاب، ومعاينة ملف الطالب
  الأكاديمي من صفحة «طلابي»، وكلتا الرحلتين مقيدتان بالمؤسسة وإسناد المعلم الحالي.
- رُفع `memory_limit` لخدمات PHP إلى 1GB وأعيد بناء خدمات التطبيق وHorizon والجدولة.

## 2026-09-04 — مسار القرآن الفردي وحجز خانات المعلم

- أضيف كورس C-QURAN-IND باسم «القرآن الفردي» عبر بذرة إنتاج محدودة قابلة
  لإعادة التشغيل، مع نقل تأهيل معلمي القرآن الحاليين إليه دون تكرار.
- شاشة الجدولة تميّز التسكين الجماعي من الفردي، وترشّح الكورسات حسب نمط
  الحصة، وتعرض للفردي مدد 25 و35 و55 دقيقة فقط.
- اختيار الوقت يعتمد الإتاحة المعتمدة ويستبعد حجوزات المعلم المتعارضة، مع
  ملخص حي وإشعار نجاح بعدد الحصص ومواعيدها والخانات البديلة.
- يرسل إنشاء الجدول بريدًا وإشعارًا داخل التطبيق للطالب والمعلم بالجدول كاملًا،
  ويعمل تذكير الساعة تلقائيًا كل دقيقة مع زر تشغيل يدوي في لوحة التحكم.
- أضيف مفتاح في الملف الشخصي للطالب والمعلم لتعطيل بريد الجدول والتذكير، مع
  إبقاء إشعار التطبيق فعالًا.
- أضيفت اختبارات للخانات والتعارض ومدد الفردي والبذرة وترشيح الكورس، ورسائل
  الجدول، ومنع تكرار التذكير، وتفضيل تعطيل البريد، وظهور زر الإدارة.
- أضيفت صفحة جدول شبيهة بجداول البيانات لتسكين طلاب القرآن الفردي؛ يُعدّل الموظف
  المعلم والأيام والمدة وفترة الجدول في صف كل طالب، ويظهر بجوار كل يوم محدد اختيار
  ساعة مستقل. يحفظ النظام الأيام والساعات في جدول واحد للطالب، ويستبعد المواعيد
  المشغولة ويعيد فحص كل يوم عند الاعتماد، من دون حقل سبب يدوي.
- أعيد تنظيم الصفحة بعرض كامل ومتجاوب RTL: التبويبات ثم بطاقة إعدادات مشتركة
  واضحة ثم جدول الطلاب، مع فلاتر للحالة والمعلم ومعاينة خضراء كاملة بعد الحفظ.
  ويحافظ تحديث Livewire على ساعات الأيام المختارة بدل مسحها أثناء إعادة التحميل.
- أتيح تسكين القرآن الفردي من طلبات التسجيل المقبولة، وأتيح التسكين الجماعي
  متعدد الطلاب من جدول الطلاب، مع إنشاء قيد القرآن تلقائيًا وصلاحيات صريحة
  واستبعاد الطلبات غير المقبولة.
- تُبقي صفحة تسكين القرآن الفردي الطالب المسكّن ظاهرًا في صف أخضر وبزر تعديل
  واحد لجدوله، وتعطّل تحديده للتسكين الجديد. المعلم بلا ساعات تفرغ معلنة يُعامل
  كمتاح طوال اليوم، وإذا أعلن ساعات معتمدة تُعتمد وحدها.

> **سجل تاريخي للدفعات السابقة، وليس قائمة النطاق أو الطابور الحالي.**
> راجع `docs/phase-1-approved-scope.md` و`docs/agent-tasks/QUEUE-antigravity.md`.

> ملف حالة يقرأه loop البناء كل ١٥ دقيقة. البند المعلّم `[x]` مكتمل — لا يُعاد تنفيذه.
> عند اكتمال كل البنود، أو بعد `2026-08-21 23:28`، يتوقف اللوب.

**بدء البناء:** 2026-08-21 21:28
**الموعد النهائي للّوب:** 2026-08-21 23:28
**معرّف مهمة اللوب:** `5ec08961`

---

## المرحلة 0 — هيكل المستودع

- [x] 0.1 هيكل مجلدات Laravel + 27 module
- [x] 0.2 `composer.json` مع PSR-4 لكل module
- [x] 0.3 ملفات الجذر: `.gitignore` `.gitattributes` `.editorconfig` `.env.example` `README.md` `CLAUDE.md`
- [x] 0.4 هيكل Laravel التنفيذي: `bootstrap/` `public/` `artisan` `routes/`
- [x] 0.5 أدوات الجودة: `pint.json` `phpstan.neon` `phpunit.xml` `rector.php`
- [x] 0.6 بيئة Docker: `docker-compose.yml` + `docker/php/Dockerfile` + nginx
- [x] 0.7 `package.json` + `vite.config.ts` + `tailwind` + `tsconfig.json`
- [x] 0.8 CI: `.github/workflows/ci.yml`

## المرحلة 1 — النواة المشتركة (Shared Kernel)

- [x] 1.1 `shared/src` : `BaseModule`, `ModuleServiceProvider`, اكتشاف الموديولات
- [x] 1.2 Domain Events: `DomainEvent`, `EventBus`, سجل الأحداث
- [x] 1.3 أنواع أساسية: `Money`, `TimeRange`, `Timezone`, `Ulid` traits
- [x] 1.4 ملفات `config/`: `modules` `features` `virtual-classroom` `scheduling` `discipline` `payroll` `notifications` `academic`
- [x] 1.5 Enums الأساسية: SessionStatus · EnrollmentStatus · AttendanceStatus · PayrollPeriodStatus · PostponementStatus (البقية ضمن حزم الوكلاء)
- [x] 1.6 Contracts: VirtualClassroomProvider (البقية ضمن حزم الوكلاء في 20-agent-task-packages)

## المرحلة 2 — وثائق المعمارية (docs/)

- [x] 2.1 `00-README.md` + `client-answers.md` (محضر إجابات العميل)
- [x] 2.2 `01-PRD.md`
- [x] 2.3 `02-scope-and-phases.md`
- [x] 2.4 `03-domain-model.md`
- [x] 2.5 `04-entity-relationship-model.md`
- [x] 2.6 `05-state-machines.md`
- [x] 2.7 `06-permissions-matrix.md`
- [x] 2.8 `07-database-schema.md`
- [x] 2.9 `08-module-boundaries.md`
- [x] 2.10 `09-domain-events.md`
- [x] 2.11 `10-api-contracts.md`
- [x] 2.12 `11-provider-interfaces.md`
- [x] 2.13 `12-notification-architecture.md`
- [x] 2.14 `13-scheduling-rules.md`
- [x] 2.15 `14-payroll-rules.md`
- [x] 2.16 `15-security-model.md`
- [x] 2.17 `16-testing-strategy.md`
- [x] 2.18 `17-coding-standards.md`
- [x] 2.19 `18-ADRs.md`
- [x] 2.20 `19-agent-dependency-graph.md`
- [x] 2.21 `20-agent-task-packages.md`
- [x] 2.22 `21-definition-of-done.md`

## المرحلة 3 — الأساس التنفيذي

- [x] 3.1 هجرات النواة: users, organizations, roles/permissions, audit
- [x] 3.2 هجرات الأكاديمي: programs, courses, groups, enrollments
- [x] 3.3 هجرات التشغيل: schedules, sessions, attendance, classrooms, recordings
- [x] 3.4 هجرات المال: payroll ledger, billing (خلف feature flag)
- [x] 3.5 Seeders: الأدوار والصلاحيات + إعدادات المؤسسة + بيانات تجريبية
- [x] 3.6 اختبارات معمارية (Pest Arch) تحرس حدود الموديولات
- [ ] 3.7 تجهيز الملفات للـ commit الأول — لا تنفّذ git commit، اتركها للمستخدم

---

## سجل التنفيذ

| الوقت | الخطوة | ملاحظة |
|-------|--------|--------|
| 21:28 | 0.1 + 0.2 | 27 موديول، 946 مجلد، composer.json صالح |
| 21:42 | 0.3 – 0.8 | ملفات الجذر · Laravel skeleton · أدوات الجودة · Docker · Vite · CI |
| 21:50 | 1.1 – 1.6 | Shared Kernel · 12 ملف config يحمل قواعد العمل · 5 enums · عقد الفصل المباشر |
| 21:58 | 2.1 – 2.3 | 00-README · client-answers · 01-PRD · 02-scope |
| 22:05 | 2.4 – 2.6 | 03-domain-model · 04-ERD · 05-state-machines |
| 22:15 | 2.7 – 2.8 | 06-permissions-matrix · 07-database-schema |
| 22:22 | 2.9 · 2.10 · 2.15 | 08-module-boundaries · 09-domain-events · 14-payroll-rules |
| 22:32 | 2.14 · 2.19 · 2.22 | 13-scheduling-rules · 18-ADRs · 21-definition-of-done |
| 22:38 | 2.20 | 19-agent-dependency-graph |
| 22:07 | 2.11 | 10-api-contracts |
| 22:12 | 2.12 | 11-provider-interfaces |
| 22:27 | 2.13 | 12-notification-architecture |
| 22:42 | 2.16 | 15-security-model |
| 22:57 | 2.17 | 16-testing-strategy |
| 23:12 | 2.18 | 17-coding-standards |
| 23:27 | 2.21 | 20-agent-task-packages — المرحلة 2 مكتملة |
| 23:39 | إيقاف اللوب | انتهت المهلة (23:28). المهمة 5ec08961 محذوفة. المرحلة 3 لم تبدأ. |
| 08:40 | المراحل أ–هـ | 68 هجرة · 70 جدول · 609 ملف src · 207 مسار · لوحة Filament تعمل بحساب إداري · 9 أدوار و66 صلاحية |
| 08:40 | المرحلة و | 449 اختبار: 249 ناجح · 200 فاشل — إصلاح مستمر بـcodemods |
| 09:30 | الموجة 3 | 23 من 27 موديول مكتمل · 1012 ملف src · Scheduling كُتب يدويًا بعد فشل الوكلاء 10 مرات |
| 11:03 | إيقاف اللوب | انتهت المهلة. 1045 ملف src · 70 جدولًا · 49 مورد Filament · اللوحة تعمل ببيانات تجريبية. المتبقي موثّق في docs/CODEX-TASKS.md |
| 2026-08-22 | اعتماد Codex/OpenCode | صُححت صلاحيات Students/Staff/Attendance والمحادثات، وفُصل اعتماد Identity المعماري، وأُزيلت 86 compiled views من Git. الهجرات fresh/rollback/fresh ناجحة؛ Pint ناجح؛ PHPStan الموجّه صفر. الجولة الكاملة: 686 ناجحًا · 75 فاشلًا · 4502 توكيدًا. التصنيف `NEEDS_REVIEW_BEFORE_PRODUCTION` ولا دمج إلى main. |
| 2026-08-24 | Student onboarding + profile hubs | Wizard إداري ينشئ حسابًا جديدًا أو يربط الموجود داخل معاملة، تسكين آمن عبر المنسق المركزي مع السبب والتدقيق، وStudent/Teacher Hubs عبر DTO Query Contracts. المسارات المتأثرة: 15 اختبارًا ناجحًا · 123 توكيدًا · PHPStan موجّه بلا أخطاء. |
| 2026-08-31 | Flexible registration forms | منشئ Filament متعدد النماذج والأسئلة، روابط slug مستقلة، حفظ مصدر الطلب ولقطة الإجابات، فلتر مصدر وفلاتر أسئلة مضبوطة، وثلاثة قوالب بداية. نجحت 34 اختبارات/90 توكيدًا، وملكية الجداول 3/3، وrollback/reapply، وPint/PHPStan، وTypeScript/Vite؛ بقي QA بصري فقط بسبب bind mount قديم داخل Docker. |
| 2026-09-03 | واجهة Tele Course العامة | أضيفت 11 صفحة تسويقية عربية/إنجليزية، 5 مسارات برامج، 21 صورة محسّنة، SEO وحركات خفيفة ودعم RTL والموبايل. نجح TypeScript والبناء، ونجحت 15 اختبارات Feature بـ130 توكيدًا. |

## 2026-09-06 — بدء التنفيذ الفعلي للوحة المستقلة

- أُنشئ فرع `codex/console-v2` من HEAD مطابق للخادم، وDocker وقاعدة بيانات مستقلان على `localhost:8096`.
- رُبطت المكونات الجديدة بخدمات النظام الفعلية: تجهيز الكورسات والمجموعات، تسكين القرآن الفردي، ملفات الأشخاص وإنشاؤهم، التقارير والإعدادات، بوابات الطالب والمعلم.
- استدركت المراجعة تسريب إذن تعديل بيانات الحساب من إذن الملف الأكاديمي؛ أصبح يحتاج UserPolicy مستقلة مع اختبار دور فعلي.
- عقب ملاحظة المستخدم، أُعيدت مطابقة نماذج الإدخال وتوزيع الصفحات مع الديمو، وأصبحت النسخة الجديدة عربية فقط. لا فرض لأسماء بلغات إضافية.
- النطاق المتبقي وتفاصيل الاختبارات والمراجعة في `console-v2-implementation.md`. لا نشر ولا انتقال من اللوحة الحالية.

- الجولة التالية وزعت التسجيل والتسكين، المتابعة، والمستحقات على ثلاثة وكلاء، مع دمج التصميم والإعدادات والمراجعة اليدوية لدى المسؤول الرئيسي. أُضيفت مراجعة متبادلة للصلاحيات وسلامة البيانات.
- نُفذت رحلة نموذج عام → طلب → قبول، وكشف معلم → اعتماد مكافأة تجريبية. أصلح اختبار حدود الصفحات نقص سجل الغياب، وأصلح اختبار التزامن اعتماد تقويم من شاشة قديمة.
- سجلات التقويم والعطلات محفوظة، لكن محرك التوليد القديم لا يستهلكها؛ الصفحة توضح هذا الحد. ربطها بالجدولة يبقى في خطة الانتقال.

- نتيجة الدمج بعد التصحيحات: 258 اختبارًا و5372 تأكيدًا ناجحة؛ تشمل المعمارية والمسارات القديمة المتأثرة. PHPStan موجّه بلا أخطاء، وPint/TypeScript/ESLint/Vite ناجحة. المعاينة مستمرة، والجاهزية لاستبدال الإنتاج غير معتمدة؛ التفاصيل في console-v2-implementation.md.


## 2026-09-09 — تثبيت هوية بيئة المشروع

أكّد المستخدم أن جميع تعديلات المشروع واختباراته دائمًا على SSH SCHOOL-WEB. ثُبّت القرار في AGENTS.md وPROJECT_MAP.md، مع إلغاء أولوية تعليمات WSL القديمة. بيئة النشر /opt/eschool ونسخ العمل المعزولة /opt/eschool-worktrees/. قاعدة الموقع تحمل بيانات حقيقية منذ انتقال 2026-09-09.


### 2026-09-10 — Schedule-change production deployment

Published `f10ac34` to SCHOOL-WEB with verified database/code backup, both migrations, built assets and 18 missing notification templates. Full isolated regression: 1428 passed / 11801 assertions. Live HTTP/service/data-count checks pass. Static-analysis and browser limitations are recorded in `docs/uat/2026-09-10-schedule-change-deployment.md`.


### 2026-09-12 — دور المشرف `supervisor` وصلاحية `contact.pii.view`

أُضيف دور `supervisor` لإدارة الجودة والمتابعة: قراءة وتصدير تقارير بلا أي صلاحية فعل،
وبلا `session.join` فلا يدخل الاجتماعات الافتراضية وإن رأى بيانات الحصة ومن حضرها.
ثلاث صلاحيات تُمنح للحساب لا للدور حسب حاجة كل مشرف: `payroll.view` للمالي،
`contact.pii.view` لبيانات التواصل، `recording.view` للتسجيلات.

`contact.pii.view` صلاحية جديدة تحكم `phone` و`email` فقط، والحجب على الخادم في
`PeopleController` فلا تصل الحقول للعميل أصلًا. مُنحت لكل دور كان يراها قبل الإضافة
(`platform_admin`، `academic_supervisor`، `finance_supervisor`، `registrar`،
`communications_officer`، `auditor`) فلم يتغير سلوك أي حساب قائم.

الجولة المعزولة الكاملة: 1450 اختبارًا و11905 تأكيدات ناجحة. Pint نظيف، وPHPStan على
الملفات المعدّلة بلا أخطاء (الأساس العام 875 خطأً قائمًا قبل التغيير وبعده بلا زيادة).
التفاصيل في `docs/06-permissions-matrix.md` القسم 7.


### 2026-09-12 — شاشة المفاتيح الاختيارية لحساب المشرف

الصلاحيات الثلاث الاختيارية (`payroll.view` و`contact.pii.view` و`recording.view`) كانت
تُمنح عبر API فقط بلا أي زر، فكانت ميزة المشرف ناقصة عمليًا. أُضيف في صفحة المستخدم
بلوحة الإدارة زر «تعديل الصلاحيات الاختيارية» بمفاتيح تشغيل/إيقاف وسبب إلزامي، وتبويب
«الأدوار والصلاحيات» صار يعرض حالة كل مفتاح.

القائمة في `config('accesscontrol.optional_direct_permissions')` لا في الكود. القناة بين
`Identity` و`AccessControl` عقد `DirectPermissionGateway` الجديد، لأن الموديولين في
الطبقة 0 فلا يجوز استيراد Actions أو Models بينهما.

الحراس: صلاحية `accesscontrol.permissions.grant_direct` · منع المسؤول من تعديل صلاحياته
هو · رفض أي اسم خارج القائمة المعلنة · سبب نصي إلزامي يُكتب في `audit_log`. العملية
idempotent فلا يُسجَّل شيء عند إرسال بلا تغيير.

الجولة المعزولة الكاملة: 1456 اختبارًا و11943 تأكيدًا ناجحة. تُحقق من أن اختبار منع
التصعيد الذاتي يفشل فعلًا عند إزالة حارسه.


## 2026-09-12 — Platform performance (SCHOOL-WEB)

- Candidate branch: codex/platform-performance-20260912, baseline 154080b.
- Added hashed-asset gzip/immutable caching, component-scoped lazy translations,
  correct first-party Sanctum origins, visibility/session-aware notification polling,
  safe request/query timing, and PHP-FPM slow traces with log rotation.
- Validation: frontend build/lint/types and PHP lint passed; 99 component/import
  translation audit passed; 61 focused PHP tests / 786 assertions; 6 polling browser
  tests and 8 real-login browser tests across desktop/mobile passed.
- Full PHP run: 1468 passed, 2 failed due to tests starting before the Vite manifest
  existed. Both affected suites and architecture were rerun after build:
  95 passed / 2733 assertions. No unresolved functional test failures.
- PHPStan has 875 pre-existing errors in both baseline and candidate; zero added
  errors by file/message/identifier comparison. This release does not claim that
  the repository-wide zero-error/CI gate is green.
- Production pre-release measurement: login HTML 531,814 bytes; login JS/CSS
  dependency set 583,526 bytes without gzip or Cache-Control.
- Deployment, runtime controls and rollback: docs/platform-performance-20260912.md.

- Published on SCHOOL-WEB at 2026-09-12 16:32 UTC (code e42de90).
  Backup: /opt/eschool-backups/platform-performance-20260912-1632
  (restricted code/config/assets archive and PostgreSQL dump).
- Live HTTPS verification: login HTML 531,814 -> 64,506 bytes (-87.87%);
  login JS/CSS dependencies 583,526 -> 168,354 bytes (-71.15%).
  All hashed assets carry immutable caching; compressed responses vary by encoding.
  Health/login succeed; private routes redirect guests to login; unauthenticated
  notification count returns 401; missing assets return uncached 404.
- Production FPM and Nginx validated and reloaded; Horizon gracefully restarted.
  Request threshold 1000ms, query threshold 500ms, max 10 query logs per request;
  FPM trace threshold 3 seconds. No PostgreSQL restart or data migration.
