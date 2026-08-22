# تقرير الحزمة A — الطلاب · التسجيل · الجغرافيا · الحساب

> **التاريخ:** 2026-08-22
>
> **الحالة:** **Partial — النواة منفّذة، وتصحيحات الاعتماد اجتازت تحققها المستهدف، لكن الرحلة والبوابات العامة للمستودع غير مكتملة إنتاجيًا.**
>
> **النطاق:** A1–A14 من `AGENT-A-students-registration-geography.md` و`phase-1-critical-modules.md`.
> التقرير الأصلي كُتب قبل أن يجمع مدير المشروع التعديلات في commit. جولة الاعتماد
> اللاحقة شغّلت Docker وPHPStan العام والموجّه، ونتائجها أدناه.

## ملخص الحالة

| البند | الحالة | ما تحقق |
|---|---|---|
| A1 | Implemented | جدولا `countries` و`regions`، ملف بيانات، بذرة idempotent، 22 دولة عربية و27 محافظة مصرية بثلاث لغات. |
| A2 | Implemented | عقد `GeographyQueries` يعيد DTOs فقط، وتنفيذ قراءة مربوط في Organization. |
| A3 | Implemented | `registration_applications` مع SoftDeletes و`RegistrationStatus` ذي الحالات السبع. |
| A4 | Implemented | إنشاء/تقديم/مراجعة/قبول/رفض عبر Actions وFormRequests وAPI وFilament، مع transitions ومعاملات/أقفال. |
| A5 | Partial | عقد المنع موجود ويعيد true فقط لـ`waiting_assignment`/`assigned`، وAction التوزيع الفعلي رفض طالبًا غير مجاز؛ اختبار Enrollments الكامل محجوب بfixture خارج A. |
| A6 | Implemented | القبول وحده ينشئ `StudentProfile` ثم ينقل الطلب إلى `waiting_assignment` داخل transaction. مسار الإنشاء القديم عُطّل. |
| A7 | Partial | `username` إلزامي وفريد في التسجيل مع بريد أو هاتف؛ شاشة دخول Filament ما زالت بالبريد لأنها ملف read-only مملوك للحزمة E. |
| A8 | Implemented | `UsernameSuggester` يستعمل بادئة المؤسسة عبر عقد عام، مع transliteration، reserved/min/max وفحص uniqueness. |
| A9 | Implemented | كشف التكرار بالبريد/الهاتف وفق الإعدادات مع `flag` في `duplicate_of_application_id`. |
| A10 | Implemented | ربط الدولة والمنطقة بملفي الطالب والمعلم وطلبات/API/Filament، مع إبقاء حقل الطالب القديم deprecated. |
| A11 | Partial | جنس المعلم وتاريخ الميلاد والهاتف موصولة بطبقات Staff، ومسار API يتحقق من مؤسسة الحساب الهدف؛ إنشاء Staff في Filament معطّل مؤقتًا حتى يتوفر اختيار حساب آمن. |
| A12 | Partial | `teacher_courses` وعقد قراءة تأهيل المعلم موجودان مع اختبار الاستعلامات، لكن لا يوجد Action/واجهة إنتاجية تكتب التأهيلات بعد. |
| A13 | Partial | فلاتر الدولة/المنطقة/الحالة موجودة؛ البحث الموحد بالاسم لكل السجلات القديمة يحتاج Query عام من Identity. |
| A14 | Partial | الاستعادة بالبريد موجودة؛ رحلة الهاتف/WhatsApp لاستعادة الحساب غير منفّذة. |

## ما نُفّذ

### الجغرافيا وإعدادات المؤسسة

- هجرة PostgreSQL لجدولي `countries` و`regions` مع القيود والفهارس المطلوبة.
- البيانات المرجعية في ملف مستقل؛ لا أسماء دول أو محافظات hardcoded داخل منطق التطبيق.
- `GeographySeeder` قابل لإعادة التشغيل دون تكرار.
- `CountryData` و`RegionData` بدل تسريب Eloquent خارج Organization.
- منفذ read-only عام لإعدادات المؤسسة استعمله مولّد أسماء المستخدمين.

### التسجيل والقبول

- فصل `User Account` عن `RegistrationApplication` وعن `StudentProfile` وعن التوزيع.
- دورة الحالات: `draft → submitted → under_review → accepted/rejected → waiting_assignment → assigned` وفق الـenum.
- الرفض بلا سبب يفشل في FormRequest والمنطق، والقبول/الرفض/المراجعة مؤمّنة بصلاحيات وسياسات.
- إنشاء ملف الطالب يحدث عند القبول فقط، داخل transaction وقفل للسجل.
- إيقاف endpoint/action القديم الذي كان ينشئ ملف طالب مباشرة؛ يعيد خطأ عمل مترجمًا بدل تجاوز رحلة القبول.
- أحداث `RegistrationSubmitted` و`RegistrationAccepted` و`RegistrationRejected` ترث `DomainEvent` وتحمل معرف المستخدم حيث يتوفر.
- ربط `RegistrationAccepted` بمفتاح `registration.approved` في إعدادات Notifications، مع اختبار تكامل حقيقي للـoutbox.
- عزل المؤسسة في سياسات وقوائم Students/Staff وموارد Filament مع fail-closed عند غياب المؤسسة.

### الحساب وبيانات الطالب والمعلم

- ترحيل آمن للحسابات القائمة قبل فرض `username`، مع `email` اختياري وقيد يفرض وجود بريد أو هاتف.
- تحديث تسجيل المستخدم وموارد API/Filament لاسم المستخدم والهاتف.
- اقتراح أسماء مستخدمين فريدة من اسم عربي، وبادئة قابلة للضبط لكل مؤسسة.
- إضافة جغرافيا الطالب؛ وإضافة جنس/جغرافيا/تاريخ ميلاد/هاتف المعلم وتوصيلها بطلبات الكتابة والعرض.
- إضافة `teacher_courses` وربط عقد التأهيل في `StaffServiceProvider`.

## تصحيحات الاعتماد اللاحقة

- فُصلت رؤية السجلات على مستوى المؤسسة إلى `student.view.any` و`staff.view.any`، مع إبقاء المالك قادرًا على رؤية سجله فقط داخل مؤسسته.
- ضُيّقت قرارات طلبات التسجيل الإدارية إلى `student.create`، مع إبقاء صاحب الطلب قادرًا على رؤية طلبه داخل المؤسسة.
- أزيلت حقول `organization_id` و`user_id` من نموذج الطالب، وجُعل `student_code` للعرض فقط؛ كما مُنع دخول الطالب إلى تحرير Filament بالملكية وحدها.
- أُزيلت إجراءات الحذف/الاستعادة الافتراضية من صفحات الطالب، وأُزيل الحذف الافتراضي وإنشاء Staff من Filament مؤقتًا.
- سُجّل `RegistrationApplicationResource` صراحةً في لوحة الإدارة.
- أصبح إنشاء Staff عبر API يرفض ربط حساب من مؤسسة أخرى عبر `UserQueryService`.
- وُضعت مسارات ملفات الطلاب خلف `auth:sanctum`، وأصبح الحد الأدنى لعمر التسجيل الذاتي مأخوذًا من `config('admission.self_registration.min_self_registration_age')`.

## العقود العامة النهائية

```php
interface GeographyQueries
{
    public function countries(bool $activeOnly = true): array;
    public function regionsOf(string $countryId, bool $activeOnly = true): array;
    public function findCountryByIso2(string $iso2): ?CountryData;
    public function regionExistsIn(string $regionId, string $countryId): bool;
}

interface OrganizationSettingQueries
{
    public function value(string $organizationId, string $key): mixed;
}

interface StudentAdmissionQueries
{
    public function isClearedForAssignment(string $studentProfileId): bool;
}

interface TeacherQualificationQueries
{
    /** @return list<string> */
    public function qualifiedTeacherIdsForCourse(string $courseId): array;
    public function isQualified(string $staffProfileId, string $courseId): bool;
    public function genderOf(string $staffProfileId): ?string;
}
```

## الملفات الرئيسية

- `modules/Organization/database/migrations/2026_08_22_110000_create_countries_and_regions_tables.php`
- `modules/Organization/database/data/geography.php`
- `modules/Organization/database/Seeders/GeographySeeder.php`
- `modules/Organization/src/Domain/Contracts/{GeographyQueries,OrganizationSettingQueries}.php`
- `modules/Organization/src/Application/Queries/{GeographyQueryService,OrganizationSettingQueryService}.php`
- `modules/Students/database/migrations/2026_08_22_110100_create_registration_applications_table.php`
- `modules/Students/database/migrations/2026_08_22_110200_add_geography_to_student_profiles.php`
- `modules/Students/src/Domain/{Models/RegistrationApplication.php,Enums/RegistrationStatus.php}`
- `modules/Students/src/Application/Actions/*RegistrationApplicationAction.php`
- `modules/Students/src/Domain/Events/Registration{Submitted,Accepted,Rejected}.php`
- `modules/Students/src/Domain/Contracts/StudentAdmissionQueries.php`
- `modules/Students/src/Presentation/Filament/Resources/{RegistrationApplicationResource,StudentProfileResource}.php`
- `modules/Staff/database/migrations/2026_08_22_110300_add_profile_fields_to_staff_profiles.php`
- `modules/Staff/database/migrations/2026_08_22_110400_create_teacher_courses_table.php`
- `modules/Staff/src/Domain/Contracts/TeacherQualificationQueries.php`
- `modules/Staff/src/Application/Queries/TeacherQualificationQueryService.php`
- `modules/Identity/database/migrations/2026_08_22_110500_add_username_and_phone_to_users.php`
- `modules/Identity/src/Application/Services/UsernameSuggester.php`
- `modules/Notifications/tests/Feature/RegistrationAcceptedNotificationIntegrationTest.php`

## نتائج التحقق بعد جولة الاعتماد

| الفحص | النتيجة |
|---|---|
| Organization + Identity المستهدف | **10 passed / 170 assertions** |
| Students المستهدف | **32 passed / 70 assertions** |
| Staff المستهدف | **5 passed / 13 assertions** |
| حدث القبول → Notifications outbox | **1 passed / 3 assertions**؛ `email` و`in_app` و`whatsapp` queued |
| اختبارات enum/data غير المعتمدة على DB | **4 passed / 135 assertions** |
| مسارات التسجيل | 7 مسارات خلف `auth:sanctum` ونجح `route:list` |
| جولة Codex الأمنية الموسعة | **186 passed / 1 failed / 580 assertions**؛ الفشل الوحيد كان عداد Seeder قديمًا (68 بدل العدد الحقيقي 74)، وبعد التصحيح نجح منفردًا **1 / 21** |
| قاعدة اختبار نظيفة | على `eschool_testing_pm`: `migrate:fresh --force` **PASS** ثم `migrate:rollback --force` **PASS** ثم إعادة `migrate:fresh` **PASS**؛ لم تُمس قاعدة التطوير |
| Pint للمستودع | **PASS** بعد إصلاح 16 مخالفة تنسيق قديمة في الفرع |
| PHPStan level 6 المستهدف | **0 errors** على 27 ملف إنتاج متأثر عالي المخاطر |
| PHPStan العام | **FAIL: 1000+ errors**؛ الأداة أوقفت العد عند 1000، ومعظمها typing قديم لـEloquent/Pest |
| `git diff --check` | **PASS** |
| الجولة الكاملة | **686 passed / 75 failed / 4502 assertions**، seed `1787412752` |

### فحوص لم تمر بسبب نطاق خارجي

- الجولة الكاملة كشفت fixtures قديمة لا تملأ `sessions.original_teacher_id` أو
  `programs.default_session_minutes`، إضافة إلى صلاحيات HTTP غير مبذورة واختبارات
  تعتمد على قاعدة نظيفة ولا تعزل بياناتها.
- فحص حدود الموديولات: **80 passed / 4 failed / 2451 assertions**. أُزيل اعتماد
  Identity→Organization المخالف عبر منفذ يملكه Identity وAdapter في طبقة تركيب
  التطبيق. المتبقي: Reporting→Payroll، Enrollments→Groups (خرقان)،
  Notifications→Integrations. والجولة الكاملة كشفت كذلك فشلين في خريطة ملكية
  جداول Academics الجديدة.

## المتبقي/المحجوب قبل الإنتاج

1. **الدخول باسم المستخدم:** يلزم تعديل `app/Filament/Pages/Auth/Login.php` في نطاق الحزمة E؛ التسجيل والتخزين جاهزان لكن شاشة الدخول ما زالت بالبريد.
2. **A14:** يلزم تصميم/تنفيذ استعادة عبر الهاتف وWhatsApp؛ البريد فقط يعمل حاليًا.
3. **Audit:** لا يوجد عقد كتابة عام من موديول Audit تستطيع Students استدعاءه دون خرق الحدود؛ تغييرات حالة الطلب لم تُوصل بعد إلى `audit_log`.
4. **مستلمو الإشعار:** القبول/الرفض يصلان إلى حساب المتقدم. إشعار التقديم للإدارة/ولي الأمر يحتاج resolver عام لمعرفات المستلمين؛ الحدث لا يحمل تلك المعرفات حاليًا.
5. **A13:** بحث الاسم الكامل للسجلات القديمة يحتاج Query read-only من Identity، بدل استيراد User أو جدول users داخل Students/Staff.
6. **اختبار B:** إصلاح fixture `default_session_minutes` خارج نطاق A مطلوب لإثبات رحلة التوزيع آليًا من طرف لطرف.
7. **حدود المعمارية:** حُل اعتماد Identity الخاص بالحزمة؛ تبقى أربعة إخفاقات خارج A وفشلا ملكية جداول Academics.
8. **اختبارات يدوية:** لم تُنفّذ رحلة متصفح كاملة، ولم تُستخدم credentials حقيقية لـMeta WhatsApp.
9. **إنشاء Staff في Filament:** معطّل مؤقتًا؛ مسار API موجود، لكن واجهة اختيار الحساب الهدف الآمنة لم تُبنَ بعد.
10. **A12 write path:** لا توجد حتى الآن Action أو FormRequest أو واجهة لإضافة/تعديل `teacher_courses` في التشغيل الفعلي.

## الدين التقني خارج النطاق

- شُغّل PHPStan العام وكشف أكثر من 1000 خطأ قديم؛ لم تُنفّذ حملة تنظيف شاملة خارج ملفات A.
- لم تُصلح أعطال الاختبارات أو ملكية الجداول الخاصة بالحزم B/D/Reporting/Payroll/Groups.
- لم يُجر أي refactor معماري أو Feature جديدة خارج A.
