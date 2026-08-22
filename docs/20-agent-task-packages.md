# 20 — حزم عمل الوكلاء

كل حزمة **مكتفية بذاتها**: وكيل يقرأ حزمته والوثائق المشار إليها فيها،
ويبدأ العمل بلا أسئلة.

**قبل أي حزمة، اقرأ:** [`08`](08-module-boundaries.md) ·
[`17`](17-coding-standards.md) · [`21`](21-definition-of-done.md)

**ترتيب التشغيل والتوازي:** [`19-agent-dependency-graph.md`](19-agent-dependency-graph.md)

---

## نموذج الحزمة

```
المُعرّف · الموديول · الموجة
يملك        : الجداول
يعتمد على   : موديولات + عقود
يُسلِّم      : عقود · أحداث · واجهات
يقرأ        : الوثائق الملزِمة
التسليمات   : قائمة محددة
بوابة الخروج: ما يجب أن يعمل
مزالق       : أخطاء متوقعة تحديدًا
```

---

# الموجة 0 — الأساس (متسلسلة)

## W0-1 · Shared Kernel + اختبارات المعمارية

**يملك:** `shared/src` · `tests/Architecture`
**يعتمد على:** لا شيء
**يقرأ:** [`08`](08-module-boundaries.md) · [`16`](16-testing-strategy.md)

**التسليمات**
1. `ModuleRegistry` · `ModuleServiceProvider` · `BaseModuleServiceProvider` — **مُسلَّمة**
2. `DomainEvent` · `RecordsDomainEvents` — **مُسلَّمة**
3. `Money` · `TimeRange` · `HasUlid` · `BusinessRuleViolation` — **مُسلَّمة**
4. `OrganizationScope` + `BelongsToOrganization` trait
5. `AuditableAction` trait يكتب في سجل التدقيق
6. `tests/Architecture/ModuleBoundariesTest.php` بالفحوص الستة في [`08 §7`](08-module-boundaries.md)
7. الفحوص النصية: رقم سياسة في الكود · فحص اسم دور · نص بلا ترجمة
8. `Shared\Testing\SchoolScenario` builder

**بوابة الخروج:** خرق حدود مصطنع يُسقط CI.

**مزالق**
- كتابة اختبارات المعمارية بعد الموديولات — عندها تُعطَّل بدل أن تُصلَح.
- `Money` بـ `float` — استخدم `int` فقط.

---

## W0-2 · Organization

**يملك:** `organizations` · `academic_calendars` · `holidays` · `organization_settings`
**يُسلِّم:** `SettingsReader` · `AcademicCalendar` · `FeatureFlags`

**التسليمات**
1. هجرات الجداول الأربعة
2. `SettingsReader` — يقرأ `config/` ثم يطبّق تجاوز المؤسسة
3. `AcademicCalendar::isHoliday(date)` · `workingDaysBetween()`
4. تكامل `FeatureFlags` مع `config/features.php` + تجاوز المؤسسة
5. لوحة Filament للإعدادات والعطلات ومفاتيح الميزات
6. Seeder: مؤسسة واحدة بإعدادات العميل (Africa/Cairo · EGP · السبت)

**بوابة الخروج:** تغيير مهلة الإلغاء من اللوحة يغيّر السلوك بلا نشر كود.

**مزلق:** قراءة `config()` مباشرة في الموديولات بدل `SettingsReader` —
عندها لن يعمل تجاوز المؤسسة.

---

## W0-3 · Identity

**يملك:** `users` · `user_devices` · `password_reset_tokens` · `sessions`
**يُسلِّم:** `Modules\Identity\Domain\Models\User` · `UserDirectory`
**يقرأ:** [`15 §2`](15-security-model.md)

**التسليمات**
1. نموذج `User` (مضبوط في `config/auth.php` بالفعل)
2. Fortify: دخول · تسجيل · استعادة · تحقق بريد · 2FA
3. اسم مستخدم بديلًا عن البريد — **لطلاب دون 13 سنة**
4. إدارة الأجهزة + إنهاء الجلسات
5. حدود المحاولات حسب [`15`](15-security-model.md)
6. `SetLocale` مربوط بتفضيل المستخدم — **مُسلَّم**
7. أحداث: `user_registered` · `login_succeeded` · `login_failed` · `password_changed`

**بوابة الخروج:** حساب إداري بلا 2FA لا يستطيع الوصول للوحة الإدارة.

**مزالق**
- طلب بريد إلزامي من طفل دون 13 — ممنوع.
- عدم تدوير معرّف الجلسة عند الدخول.

---

## W0-4 · AccessControl

**يملك:** `roles` · `permissions` · جداول الربط
**يُسلِّم:** `Gate` مضبوط · `PermissionRegistry`
**يقرأ:** [`06`](06-permissions-matrix.md) كاملة

**التسليمات**
1. `spatie/laravel-permission` + `organization_id` + `module` على الصلاحيات
2. **Seeder يولّد كل صلاحية وكل دور من مصفوفة [`06`](06-permissions-matrix.md) حرفيًا**
3. `Gate::before` لـ `platform_admin` فقط — مع تسجيل أفعاله
4. `HasScopedPermissions` trait يفهم `own` · `assigned` · `children`
5. لوحة Filament لإدارة الأدوار
6. اختبار: كل مسار × كل دور مقارنًا بالمصفوفة

**بوابة الخروج:** إضافة صلاحية لدور من اللوحة تسري فورًا بلا نشر.

**مزلق:** نسيان `payroll.adjustment.propose` منفصلة عن `.approve` —
هذا الفصل هو جوهر طلب العميل.

---

## W0-5 · Audit

**يملك:** `audit_log`
**يُسلِّم:** `AuditLogger` · `AuditableAction`
**يقرأ:** [`15 §7`](15-security-model.md)

**التسليمات**
1. هجرة `audit_log` بالفهارس والتقسيم الشهري
2. مستمع عام يكتب كل `DomainEvent`
3. `AuditLogger::record()` للتغييرات المباشرة
4. دعم `acting_for_user_id` — ولي الأمر ينوب عن ابنه
5. واجهة استعراض في Filament بمُصفّيات (الفاعل · النوع · المدة · `correlation_id`)
6. منع التعديل والحذف على مستوى المستودع

**بوابة الخروج:** سؤال "ماذا حدث بعد ضغط زر الاعتماد؟" يُجاب بـ
`correlation_id` واحد.

---

# الموجة 1 — الأشخاص (خمسة بالتوازي)

## W1-A1 · Students

**يملك:** `student_profiles`
**يُسلِّم:** `StudentDirectory` — **في الساعة الأولى**

1. هجرة + نموذج + Factory
2. `StudentDirectory` يُرجع `StudentSummary` DTO (**لا نموذج**)
3. ملف الطالب في Filament + الخط الزمني الموحّد
4. بحث تقريبي بالعربية عبر `pg_trgm`
5. استيراد Excel بمعاينة وتحقق قبل الحفظ
6. حساب العمر ووسم القاصر

**مزلق:** إرجاع `StudentProfile` من `StudentDirectory` — يكسر الحدود.

## W1-A2 · Guardians

**يملك:** `guardian_profiles` · `guardian_links`
**يعتمد على:** A1 (واجهة)
**يقرأ:** [`ADR-004`](18-ADRs.md) · [`06 §4`](06-permissions-matrix.md)

1. الجدولان + النماذج
2. ربط ولي أمر بعدة أبناء · `is_primary` · `can_act_for`
3. **تبديل السياق** بين الأبناء في واجهة ولي الأمر
4. `can_act_for = true` تلقائيًا لمن دون 13 سنة
5. **كل فعل بالنيابة يُسجَّل باسم ولي الأمر** مع `acting_for_user_id`
6. Policy: ولي الأمر يرى أبناءه فقط — ولا يرى محادثاتهم الخاصة

**بوابة الخروج:** ولي أمر لثلاثة أبناء يبدّل بينهم ويرى تقرير كل واحد،
ولا يرى طالبًا رابعًا بأي مسار.

## W1-A3 · Staff

**يملك:** `staff_profiles` · `teacher_contracts` · `teacher_rates` · `teacher_availability` · `teacher_leaves`
**يُسلِّم:** **`TeacherRateResolver`** · `TeacherAvailabilityChecker` — **في الساعة الأولى**
**يقرأ:** [`14 §2`](14-payroll-rules.md)

1. الجداول الخمسة + قيد `EXCLUDE` يمنع تداخل عقدين
2. **`TeacherRateResolver`** ينفّذ الترتيب السداسي بالسريان بالتاريخ
3. `TeacherAvailabilityChecker::isAvailable(teacher, TimeRange)`
4. طلبات الإجازة باعتماد
5. لوحة العقود والأسعار في Filament
6. اختبار: تغيير السعر لا يؤثر على تاريخ سابق

**مزلق:** استنباط السعر بتاريخ اليوم بدل **تاريخ الحصة**.
هذا يفسد كل حساب تاريخي.

## W1-A4 · Integrations

**يملك:** مزوّدو الخدمات
**يُسلِّم:** `WhatsAppGateway` · `EmailGateway` · `PushGateway` · `ObjectStorage` · `RecordingArchive`
**يقرأ:** [`11`](11-provider-interfaces.md) كاملة

1. الواجهات الخمس + تنفيذ `Null` لكل واحدة
2. `MetaCloudApiGateway` — **بلا دالة إرسال حر وبلا دالة رد**
3. `SesGateway` · `FcmGateway` · `R2Storage` · `GoogleDriveArchive`
4. مهلة + إعادة محاولة + قاطع دائرة موحّد
5. `healthCheck()` لكل مزوّد + لوحة حالة
6. حمولات ويب هوك حقيقية في `tests/Fixtures/`

## W1-A5 · Notifications

**يملك:** `notification_outbox` · `notification_delivery_attempts` · `notification_preferences`
**يعتمد على:** A4
**يقرأ:** [`12`](12-notification-architecture.md) كاملة

1. الجداول الثلاثة
2. `NotificationDispatcher` بالخطوات الست
3. جدول المستلمين من [`12 §3`](12-notification-architecture.md) — بما فيه **المستلم بالصلاحية**
4. ساعات الهدوء بتوقيت المستلم
5. `idempotency_key` وسلّم إعادة المحاولة
6. البث اللحظي عبر Reverb
7. لوحة الإشعارات الفاشلة بإعادة إرسال يدوي

**مزلق:** إرسال مباشر يتجاوز Outbox "لأنه أسرع" — يفقد ضمان التسليم.

---

# الموجة 2 — البنية الأكاديمية (أربعة بالتوازي)

## W2-B1 · Academics
`programs` · `levels` · `courses` — CRUD كامل بالعربية والإنجليزية،
والأسعار الافتراضية للبرنامج.

## W2-B2 · Groups
**حرج:** `group_programs` و `group_teachers` **كثير لكثير** من اليوم الأول
([`04 §2`](04-entity-relationship-model.md)). `group_teachers` تحمل `course_id`.
سعة قصوى 25 بقيد `CHECK`. استيراد المجموعات الحالية بمواعيدها وأعضائها.

**مزلق:** `program_id` مفرد في `groups` — يُكسر عند أول برنامج مشترك.

## W2-B3 · Enrollments
`EnrollmentStatus` — **مُسلَّمة**. سجل الانتقالات إلزامي.
**لا يوجد مسار من `Frozen` إلى `Active` مباشرة.**
التجميد الاختياري بموعد عودة وباستئناف آلي.

## W2-B4 · Content
مكتبة المواد بصلاحيات حسب البرنامج · روابط موقّعة فقط ·
الطالب المجمّد لا يصل.

---

# الموجة 3 — التشغيل

## W3-C1 · Scheduling
**يقرأ:** [`13`](13-scheduling-rules.md) كاملة

1. `schedules` بـ RRULE + `start_time` و `timezone` (**لا لحظة UTC**)
2. توليد الحصص قبل 60 يومًا مع تخطي العطلات
3. كشف التعارض حسب [`13 §2`](13-scheduling-rules.md)
4. `postponement_requests` + `PostponementStatus` — **مُسلَّمة**
5. دورة التأجيل الكاملة بالتصعيد بعد 12 ساعة
6. FullCalendar بسحب وإفلات

**مزالق**
- تخزين لحظة UTC في `schedules` — يكسر التوقيت الصيفي.
- إنشاء حصة تلافي عند الإلغاء — **الإلغاء لا تلافي له**.

## W3-C2 · Sessions
`SessionStatus` — **مُسلَّمة**. قيد `EXCLUDE` لمنع ازدواج حجز المعلم.
`sessions.finalized` بالحمولة الكاملة و `occurredOn` = **تاريخ الحصة**.
الإقفال الآلي بعد 30 دقيقة. المعلم البديل.

**مزلق:** `occurredOn` = تاريخ الإقفال بدل تاريخ الحصة → قيدة في الشهر الخطأ.

## W3-C3 · VirtualClassroom
`VirtualClassroomProvider` — **العقد مُسلَّم**. المطلوب `BigBlueButtonProvider`.
إنشاء الفصل **قبل 20 دقيقة** لا عند ضغط الطالب. نافذة الدخول.
الطالب المجمّد ممنوع. ويب هوك بتوقيع مُتحقَّق.

## W3-C4 · Attendance
`AttendanceStatus` — **مُسلَّمة** مع `deriveFromMinutes()`.
الاحتساب من `classroom_events` ثم اعتماد المعلم.
**تعديل يخالف المشتق يتطلب `override_reason`** بقيد `CHECK`.

## W3-C5 · Recordings
احتفاظ 30 يومًا · أرشفة للدرايف · **`exists()` قبل الحذف** ·
روابط موقّعة 120 دقيقة · كل مشاهدة في التدقيق.

---

# الموجة 4 — التعلّم (أربعة بالتوازي)

| الوكيل | الموديول | ملاحظة حرجة |
|--------|----------|--------------|
| W4-D1 | Assignments | تسليم متأخر بنسبة خصم قابلة للضبط |
| W4-D2 | Assessments | **يجب دعم نوع `reactivation`** — الانضباط يعتمد عليه |
| W4-D3 | AcademicReports | تقرير الحصة إلزامي بمهلة 24 ساعة · الشهري مسوّدة يعتمدها المشرف |
| W4-D4 | Certificates | **إصدار يدوي فقط** — التلقائي للدورات المسجَّلة غير المتاحة · بادچات متدرجة |

---

# الموجة 5 — الانضباط والمال

## W5-E1 · Discipline
**يقرأ:** [`05 §5`](05-state-machines.md) · `config/discipline.php`

1. `violation_events` بـ `window_key` شهري
2. **العدّاد يُحسب من السجلات ولا يُخزَّن رقمًا**
3. محرّك القواعد يقرأ السلّم من الإعدادات — **لا رقم في الكود**
4. التجميد الآلي عند الثالثة → `enrollments.frozen`
5. مسار فك التجميد عبر `Assessments` بنوع `reactivation`
6. تصفير شهري لا يحذف السجلات

**مزلق:** كتابة `>= 3` في الكود — سبب رفض فوري.

## W5-E2 · Messaging
مراسلات تحت إشراف الإدارة · حائط الصف · الوارد من واتساب
لأصحاب `messaging.inbound.view` فقط.

## W5-E3 · Payroll — **أخطر وكيل**

**يقرأ:** [`14`](14-payroll-rules.md) **كاملة قبل أي كود**

1. **ابدأ بكتابة الأمثلة الثلاثة كاختبارات فاشلة** ثم اجعلها تنجح
2. الجداول الأربعة · `payroll_entries` بلا `updated_at` ولا `deleted_at`
3. `rate_snapshot` إلزامي في كل قيدة
4. مصفوفة النتائج من `config/payroll.php` — لا منطق مكرر
5. الأسس الأربعة: `per_session` · `monthly_with_deductions` · `course_fixed` · `salaried`
6. البديل: أجره هو + خصم حصة من الأساسي
7. `CHECK (approved_by <> proposed_by)`
8. `PayrollPeriodStatus` — **مُسلَّمة** · أيام السماح الثلاثة
9. كشف حساب PDF بالعربية عبر Browsershot

**بوابة الخروج:** الأمثلة الثلاثة تعطي أرقامها بالضبط، وإعادة التشغيل
مرتين لا تضاعف قيدة واحدة.

---

# الموجة 6

## W6-F1 · Reporting
Read Models من الأحداث · **لا استيراد نموذج من أي موديول** ·
اللوحات الأربع (أكاديمي · معلمون · تشغيل · مالي) · تصدير CSV/Excel/PDF.

## W6-F2 · Billing
**هجرات فقط.** الموديول مطفأ في `config/modules.php`.
الجداول تُنشأ فارغة حتى لا نهاجر بيانات حية لاحقًا ([`ADR-012`](18-ADRs.md)).

---

## قواعد مشتركة لكل وكيل

| القاعدة | المرجع |
|---------|--------|
| سلّم عقدك في الساعة الأولى | [`19`](19-agent-dependency-graph.md) |
| لا تعدّل جدولًا لا تملكه — اطلبه من مالكه | [`08 §4`](08-module-boundaries.md) |
| حدث غير موجود؟ أضفه في السجل أولًا | [`09`](09-domain-events.md) |
| انحراف عن الخطة؟ سجّل ADR | [`18`](18-ADRs.md) |
| قبل "خلصت" | [`21`](21-definition-of-done.md) |
| حدّث حالتك | [`PROGRESS.md`](PROGRESS.md) |
