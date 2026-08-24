# خريطة مشروع e-school

> آخر تحديث: 2026-08-22. هذه الخريطة تشغيلية مختصرة؛ العقود التفصيلية تحت `docs/`.
> قاعدة البيانات الحالية وبياناتها تجريبية بالكامل، لكن ضوابط الصلاحيات والمال
> والتكاملات تُعامل كضوابط إنتاج لأن الكود نفسه مرشح للنشر لاحقًا.

## مدخل التنفيذ الحالي

- مصدر نطاق المرحلة الأولى الوحيد: `docs/phase-1-approved-scope.md`.
- طابور Antigravity الوحيد: `docs/agent-tasks/QUEUE-antigravity.md`، وفيه أربع مهام
  متتابعة تُنفّذ كل واحدة في محادثة جديدة.
- ملفات `AGENT-A`…`AGENT-H` و`docs/20-agent-task-packages.md` تاريخية وليست أوامر حالية.
- المرحلة الأولى تشمل التشغيل الأكاديمي وBBB والتسجيلات والحضور والتجميد والتقارير
  والتواصل والتكليفات؛ المالية وZoom خارجها.

## صورة النظام

- تطبيق Laravel واحد بنمط **Modular Monolith**، وواجهة Filament للإدارة وInertia/React
  لبوابات الطالب والمعلم وولي الأمر.
- PostgreSQL هو مخزن البيانات، وRedis للطوابير/الكاش، والتشغيل المحلي عبر Docker Compose.
- نقطة اكتشاف الموديولات: `shared/src/Module/ModuleRegistry.php`، وترتيبها في
  `config/modules.php`. الحدود الآلية في `tests/Architecture/`.
- هوية المستخدم الفعلية: `Modules\Identity\Domain\Models\User`؛ لا يوجد
  `App\Models\User`.

## طبقات الموديولات واتجاه الاعتماد

1. النواة: Organization, Identity, AccessControl, Audit, Integrations, Notifications.
2. الأشخاص: Students, Guardians, Staff.
3. الأكاديمي: Academics, Groups, Enrollments, Content.
4. التشغيل: Scheduling, Sessions, Attendance, VirtualClassroom, Recordings.
5. التعلّم: Assignments, Assessments, AcademicReports, Certificates.
6. الانضباط والتواصل: Discipline, Messaging.
7. المال: Payroll وBilling موجودان معماريًا لكنهما مؤجلان بالكامل خارج قبول المرحلة الأولى.
8. القراءة: Reporting.

التواصل العابر للموديولات يكون بأحداث المجال أو العقود العامة أو Query Services
تعيد DTOs. يمنع استيراد نماذج Eloquent أو الربط بين جداول موديولين في كود الموديول.

## التدفقات الحساسة

### الدخول والصلاحيات

`web auth` → مستخدم Identity → Laravel Gate →
`AccessControlQuerier::modelHasPermission()` → الأدوار/المنح المباشرة في PostgreSQL.
مسارات البوابات خلف `auth` و`can:`، ولا توجد تجاوزات بأسماء الأدوار. بيانات العرض
تسند أدوار student/teacher/guardian إلى حسابات الديمو عبر البذرة الجذرية.

### بوابات المستخدم

`routes/web.php` → متحكمات `app/Http/Controllers/Portal/` → خدمة قراءة DB فقط →
صفحات `resources/js/Pages/{Student,Teacher,Guardian}`. يشارك
`HandleInertiaRequests` بيانات المستخدم واللغة والقواميس. نطاق ولي الأمر يخفي الطالب
غير المرتبط بإرجاع 404، ونافذة دخول الحصة مصدرها `config/virtual-classroom.php`.

### التسجيل والقبول والجغرافيا

`Identity User` (username + بريد أو هاتف) → `RegistrationApplication` → تقديم ومراجعة
→ قبول ينشئ `StudentProfile` داخل معاملة واحدة → `waiting_assignment` →
`Enrollments` يستدعي `StudentAdmissionQueries` قبل أي توزيع. التسجيل لا يعني قبولًا،
والقبول لا يعني توزيعًا.

الدول والمناطق مملوكة لـOrganization وتصل إلى بقية الموديولات عبر
`GeographyQueries` وDTOs فقط. بادئة اسم المستخدم تُقرأ عبر
`OrganizationSettingQueries`. تأهيل المعلم للكورس مملوك لـStaff ويُقرأ عبر
`TeacherQualificationQueries` لا عبر جدول أو نموذج عابر للحدود.

### الإشعارات

حدث/خدمة تطبيق → `NotificationDispatcher` → `notification_outbox` → أمر توزيع
المستحق → `SendQueuedNotification` → بوابة القناة من Integrations → سجل محاولات
التسليم. الصندوق هو حاجز الإرسال، مع idempotency، ساعات هدوء، backoff وحالات Enum.

### الفصل المباشر

`VirtualClassroomProvider` → `NullProvider` للاختبار أو
`BigBlueButtonProvider` للإنتاج → BBB API. الأسرار والمهل وإعادة المحاولة وقاطع
الدائرة من `config/virtual-classroom.php` وبيئة التشغيل. Webhook لا يُفسّر قبل
التحقق من توقيعه.

### المال — مؤجل خارج المرحلة الأولى

`payroll_entries` و`billing_entries` دفاتر append-only. التصحيح بقيد تسوية جديد؛
لا تعديل أو حذف لقيد إنتاجي، ولا حسابات مالية بـ float.

## البيانات والحماية

- كل التواريخ التشغيلية UTC وتُعرض بتوقيت المستخدم.
- بيانات البشر تستخدم SoftDeletes؛ التعليق/الأرشفة بدل الحذف.
- تغييرات الحضور والحالة الأكاديمية والمال والصلاحيات والتسجيلات يجب أن تدخل
  `audit_log` مع السبب والقيم قبل/بعد.
- لا أسرار في المستودع. مفاتيح BBB ومزوّدي الإشعار تأتي من `.env`.
- بيانات قاعدة التطوير الحالية وهمية ومسموح إعادة بذرها؛ هذا لا يخفف قواعد كود
  الإنتاج أو يسمح بجعل الحذف الصلب جزءًا من مسارات التطبيق.

## أوامر التحقق والتشغيل

كل أوامر PHP/Composer/Artisan داخل Docker:

> **مؤقتًا قبل إغلاق Task 01:** لا تشغّل `php artisan test` أو `composer check` الخام؛
> قاعدة الاختبار المشتركة تصادمت سابقًا. استخدم أمر البيئة المعزولة الذي سيوثقه
> `REPORT-ANTIGRAVITY-01.md`. الأوامر غير الكاتبة التالية فقط آمنة للجرد الحالي.

```bash
docker compose exec -T app php artisan route:list --json
docker compose exec -T app vendor/bin/pint --test
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G
```

## حالة الجاهزية والمخاطر المعروفة

- نواة حزمة التسجيل والجغرافيا A منفّذة: البيانات المرجعية، طلبات القبول، إنشاء ملف
  الطالب عند القبول، أسماء المستخدمين، جغرافيا الطالب/المعلم وتأهيل المعلم للكورس.
  تقرير الإثبات والنتائج في `docs/agent-tasks/REPORT-A.md`.
- رحلة A ليست مكتملة إنتاجيًا بعد: دخول Filament لا يقبل username، واستعادة الحساب
  عبر الهاتف/WhatsApp غير منفّذة، وتغييرات حالة طلب التسجيل لم تُوصل إلى Audit بعقد
  عام، وإشعار التقديم يحتاج resolver لمستلمي الإدارة/ولي الأمر.
- فلاتر A موجودة، لكن البحث الموحد بأسماء الحسابات القديمة يحتاج Query read-only من
  Identity. اختبار التوزيع الكامل محجوب بfixture خارج A في بيانات البرنامج.
- اعتماد Identity على Organization فُصل بمنفذ يملكه Identity وAdapter في طبقة
  تركيب التطبيق؛ اختبارات أسماء المستخدمين ما زالت خضراء، وإخفاق الحدود الخاص به زال.
- العمل الجاري الآخر يغطي: الترجمات الناقصة، توحيد صلاحيات موارد Filament، بوابات
  Inertia، محرّك الإشعارات، وموفّر BigBlueButton.
- لا يعتمد هذا التوزيع القديم كطابور. التنفيذ الجديد مقسّم إلى المهام الأربع في
  `docs/agent-tasks/QUEUE-antigravity.md`، ولا تُقبل حزمة من تقرير المنفّذ وحده.
- توجد عقود كتابة قديمة في بعض صفحات المعلم لا تطابق APIs الحالية، ولا يوجد بعد
  مسار `/locale` أو مسارات HTTP لاعتماد/اقتراح التأجيل؛ لا ينبغي اختراع URLs وهمية.
- بعض فحوص `can()` القديمة خارج موارد Filament ما زالت تستخدم أسماء غير موجودة في
  مصفوفة الصلاحيات وتحتاج موجة توحيد منفصلة مع Policies واختبارات.
- آخر جولة كاملة مستقلة: **686 ناجحًا و75 فاشلًا و4502 توكيدًا**. التحقق
  الموجّه لملفات الإنتاج المتأثرة خرج بصفر أخطاء PHPStan، لكن PHPStan العام تجاوز
  1000 خطأ، وحدود الموديولات ما زالت 80 ناجحًا و4 فاشلة.
- لا تُصنّف النسخة جاهزة للإنتاج قبل نجاح الفحص الشامل، اختبار الهجرات على قاعدة
  نظيفة ومعبأة مع rollback، وتجربة رحلة البوابات وBBB يدويًا بإعدادات حقيقية.

## مراجع القرار

- `AGENTS.md`: عقد العمل داخل المستودع.
- `docs/phase-1-approved-scope.md`: مصدر حقيقة نطاق المرحلة الأولى.
- `docs/agent-tasks/QUEUE-antigravity.md`: ترتيب التنفيذ الحالي.
- `docs/06-permissions-matrix.md`: مصفوفة الصلاحيات.
- `docs/08-module-boundaries.md` و`docs/09-domain-events.md`: الحدود والتواصل.
- `docs/12-notification-architecture.md`: تدفق الإشعارات.
- `docs/14-payroll-rules.md`: سلامة دفتر المستحقات.
- `docs/15-security-model.md`: نموذج الأمان.
- `docs/21-definition-of-done.md`: بوابة الإنجاز.
