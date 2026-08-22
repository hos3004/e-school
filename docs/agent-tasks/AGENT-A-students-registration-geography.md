# AGENT A — الطلاب · التسجيل · الجغرافيا · الحساب

> مدير المشروع (Claude) يحتفظ بـ Sessions/Scheduling/Substitute/BBB — **لا تقترب منها**.

## اقرأ أولًا (إلزامي)
1. `CLAUDE.md` — عقد العمل. غير قابل للتفاوض.
2. `docs/client-answers.md` — **قسم `CLIENT UPDATE — 2026-08-22` في آخر الملف هو المرجع الأحدث.** أقسام §أ · §ب · §ج تخصك.
3. `docs/phase-1-critical-modules.md` — جدول «1. الطلاب والتسجيل والجغرافيا» صفوف A1–A14.
4. `config/admission.php` — **قواعد عملك موجودة هنا بالفعل. اقرأها ولا تكرّر أرقامها في الكود.**

## البيئة
PHP على الجهاز قديم (5.6) — **كل أوامر PHP/Composer/Artisan داخل Docker** (الحاويات تعمل):
```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --filter=<Name>
docker compose exec -T app vendor/bin/pint
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T postgres psql -U eschool -d eschool -c "\d student_profiles"
```
PostgreSQL — لا دوال MySQL.

**تنبيه:** المستودع فيه ~212 اختبارًا فاشلًا سابقًا لا علاقة لها بك. **لا تحاول إصلاحها.** شغّل اختباراتك بـ`--filter` فقط ولا تشغّل المجموعة كاملة.

## ملفاتك حصريًا (وكلاء آخرون يعملون بالتوازي على بقية المستودع)
- `modules/Organization/**` — الجغرافيا فقط. لا تلمس Holiday/AcademicCalendar.
- `modules/Students/**` — **عدا** أي ملف اسمه `StudentAvailability*` (ملك الوكيل C)
- `modules/Staff/**` — **عدا** أي ملف اسمه `TeacherAvailability*` (ملك الوكيل C)
- `modules/Identity/**`

**بادئة هجراتك الحصرية `2026_08_22_11*`.** الأسماء المطلوبة بالضبط:
```
modules/Organization/database/migrations/2026_08_22_110000_create_countries_and_regions_tables.php
modules/Students/database/migrations/2026_08_22_110100_create_registration_applications_table.php
modules/Students/database/migrations/2026_08_22_110200_add_geography_to_student_profiles.php
modules/Staff/database/migrations/2026_08_22_110300_add_profile_fields_to_staff_profiles.php
modules/Staff/database/migrations/2026_08_22_110400_create_teacher_courses_table.php
modules/Identity/database/migrations/2026_08_22_110500_add_username_and_phone_to_users.php
```

---

## A1–A2 · الجغرافيا كبيانات مرجعية

جدولان في موديول Organization:
- `countries`: `id` char(26) · `iso2` char(2) unique · `iso3` · `name` jsonb (ar/en/fr) · `phone_code` · `is_active` · `sort_order`
- `regions`: `id` char(26) · `country_id` FK · `code` · `name` jsonb · `is_active` · `sort_order` · unique(country_id, code)

بذرة **من ملف بيانات لا من كود**: كل الدول العربية (مصر · السعودية · الإمارات · الكويت · قطر · البحرين · عُمان · الأردن · فلسطين · لبنان · سوريا · العراق · اليمن · السودان · ليبيا · تونس · الجزائر · المغرب · موريتانيا · الصومال · جيبوتي · جزر القمر) + **محافظات مصر الـ27 كاملة** كمثال مكتمل لبلد واحد.
البيانات في `modules/Organization/database/data/geography.php` ويقرأها الـSeeder.

**ممنوع منعًا باتًا** كتابة اسم دولة أو محافظة داخل أي كلاس منطق.

عقد القراءة العام — المسار الوحيد المسموح للموديولات الأخرى:
`modules/Organization/src/Domain/Contracts/GeographyQueries.php` يعيد **DTOs لا Eloquent models**:
```php
public function countries(bool $activeOnly = true): array;                     // list<CountryData>
public function regionsOf(string $countryId, bool $activeOnly = true): array;  // list<RegionData>
public function findCountryByIso2(string $iso2): ?CountryData;
public function regionExistsIn(string $regionId, string $countryId): bool;
```
مع `CountryData`/`RegionData` في `src/Domain/ValueObjects/` وتنفيذ في `src/Application/Queries/GeographyQueryService.php` مربوط في الـServiceProvider.

## A3–A6 · طلب التسجيل — فصل التسجيل عن القبول عن التوزيع

**القاعدة الحاكمة: Sign Up ليس قبولًا. لا توزيع على برنامج أو مجموعة قبل اعتماد الطلب.**

`registration_applications` في موديول Students:
`id` · `organization_id` · `user_id` nullable · `student_profile_id` nullable (**يُملأ عند القبول فقط**) · `status` · `full_name` · `date_of_birth` · `gender` · `country_id` FK · `region_id` FK · `email` nullable · `phone` nullable · `preferred_program_id` nullable (**بلا FK — حدود موديولات**) · `notes` · `submitted_at` · `reviewed_by` · `reviewed_at` · `decision_reason` text nullable · `duplicate_of_application_id` nullable · timestamps · softDeletes

`RegistrationStatus` enum في `src/Domain/Enums/` بالحالات السبع:
`draft` `submitted` `under_review` `accepted` `rejected` `waiting_assignment` `assigned`
مع `allowedTransitions()` و`canTransitionTo()` و`isTerminal()` — **اقرأ `modules/Enrollments/src/Domain/Enums/EnrollmentStatus.php` واتبع نفس الأسلوب حرفيًا.**

الانتقالات المسموحة:
```
draft              -> submitted
submitted          -> under_review | accepted | rejected
under_review       -> accepted | rejected
accepted           -> waiting_assignment
waiting_assignment -> assigned
rejected · assigned = نهائيتان
```

Actions في `src/Application/Actions/`:
| Action | ما يفعله |
|--------|----------|
| `SubmitRegistrationApplicationAction` | draft→submitted، يتحقق من `config('admission.self_registration.required_fields')` |
| `ReviewRegistrationApplicationAction` | submitted→under_review |
| `AcceptRegistrationApplicationAction` | **ينشئ `StudentProfile` هنا وفقط هنا** ثم ينقل إلى `waiting_assignment` — داخل transaction واحدة |
| `RejectRegistrationApplicationAction` | **السبب إلزامي** (`config('admission.application.rejection_requires_reason')`) ويُرفض بدون سبب على مستوى FormRequest |

أحداث Domain في `src/Domain/Events/`: `RegistrationSubmitted` · `RegistrationAccepted` · `RegistrationRejected` — على نمط `modules/Enrollments/src/Domain/Events/`.
الوكيل D سيربطها بالإشعارات. **لا ترسل إشعارات بنفسك.**

**A5 — منع التوزيع قبل القبول.** عقد عام في `modules/Students/src/Domain/Contracts/StudentAdmissionQueries.php`:
```php
public function isClearedForAssignment(string $studentProfileId): bool;
```
يعيد `true` فقط إذا كان للطالب طلب في `waiting_assignment` أو `assigned`.
الوكيل B سيستدعيه قبل أي توزيع — وثّق ذلك في الـPHPDoc.

## A7–A9 · اسم المستخدم والحساب

- أضف `users.username` (unique · not null بعد ترحيل الموجود) · `users.phone` nullable · `users.phone_verified_at`.
  **مهم:** الجدول فيه صفوف — املأ `username` للموجودين **قبل** فرض NOT NULL (من الجزء قبل `@` في البريد + رقم عند التصادم).
- `modules/Identity/src/Application/Services/UsernameSuggester.php`:
  - البادئة من `organization_settings` بالمفتاح `config('admission.username.organization_setting_key')`، وتسقط إلى `config('admission.username.fallback_prefix')` عند غيابها
  - يطبّق `config('admission.username.patterns')` بالترتيب
  - **يترجم الاسم العربي إلى لاتيني** (أحمد → ahmed)
  - يعيد `config('admission.username.suggestions_count')` اقتراحًا **متاحًا فعليًا** (فحص uniqueness على القاعدة)
  - يرفض `config('admission.username.reserved')` ويحترم min/max length
- الدخول بـusername (والبريد إن وُجد). `app/Filament/Pages/Auth/Login.php` **للقراءة فقط** — إن لزم تعديله سجّله في تقريرك بدل تعديله (ملك الوكيل E).
- كشف التكرار عند التقديم حسب `config('admission.self_registration.duplicate_detection')` → املأ `duplicate_of_application_id` (سلوك `flag` لا `block`).

## A10–A12 · بيانات الطالب والمعلم

- `student_profiles`: أضف `country_id` FK · `region_id` FK. **أبقِ العمود `country` char(2) القديم** واملأ `country_id` منه في نفس الهجرة حيثما أمكن، وعلّم القديم `@deprecated` في الموديل.
- `staff_profiles`: أضف `gender` (نفس قيم `modules/Students/src/Domain/Enums/StudentGender.php`) · `country_id` · `region_id` · `date_of_birth` nullable · `phone` nullable.
  **الجنس شرط مطابقة حقيقي** — بدونه لا يعمل «قرآن فردي: طالبة ↔ معلمة».
- **A12 حرج — `teacher_courses`:** `id` · `staff_profile_id` FK · `course_id` char(26) (**بلا FK — حدود موديولات**، فهرس فقط) · `qualified_at` · `qualified_by` · `notes` · unique(staff_profile_id, course_id).
  **هذا Dependency حرج لاختيار المدرس البديل عند المدير.** عقد عام `modules/Staff/src/Domain/Contracts/TeacherQualificationQueries.php`:
```php
public function qualifiedTeacherIdsForCourse(string $courseId): array;   // list<string>
public function isQualified(string $staffProfileId, string $courseId): bool;
public function genderOf(string $staffProfileId): ?string;
```
**أنجز هذا العقد أولًا قبل أي شيء آخر** — المدير ينتظره.

## A13 · البحث والفلاتر
فلاتر الدولة والمنطقة والحالة + بحث بالاسم والكود، داخل
`modules/Students/src/Presentation/Filament/**` و`modules/Staff/src/Presentation/Filament/**` فقط.

---

## القواعد الملزمة
- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*` من موديول آخر. التواصل بأحداث Domain أو عقود عامة أو Query Services تعيد DTOs. `tests/Architecture` تُسقط CI عند الخرق.
- **لا أرقام سياسة في الكود** — كلها من `config/admission.php`.
- **لا حذف** — `SoftDeletes` على كل جدول يحمل بيانات بشرية.
- **الترجمات:** `resources/lang/{ar,en}/` لكل موديول. ممنوع نص مكتوب مباشرة في واجهة أو رسالة خطأ. العربية افتراضي RTL.
- **الصلاحيات:** Policy لكل مورد + `can:` middleware. ممنوع `if ($user->role === ...)`.
- **التواريخ UTC** في التخزين دائمًا.
- **التدقيق:** أي تغيير في الحالة الأكاديمية يدخل `audit_log` مع (من · ماذا · قبل · بعد · متى · السبب).
- نمط الملفات المجاورة: `declare(strict_types=1)` · `final` · readonly حيث يناسب · تعليقات عربية تشرح **لماذا** لا ماذا.
- **ممنوع Scope Creep:** لا موديول جديد. لا abstraction/table/service جديد بلا حاجة Domain حقيقية.

## الاختبارات المطلوبة
تحت `modules/<Module>/tests/`، تغطي على الأقل:
- طلب مقبول ينشئ StudentProfile · طلب مرفوض لا ينشئه
- **محاولة توزيع طالب قبل القبول تفشل**
- الرفض بلا سبب يفشل على مستوى FormRequest
- مولّد اسم المستخدم يعيد أسماء فريدة ولا يعيد اسمًا محجوزًا
- عقد الجغرافيا يعيد DTOs لا Eloquent models
- `qualifiedTeacherIdsForCourse` صحيح

## تعريف «خلصت»
`migrate --force` ينجح · اختباراتك تمر · `pint` و`phpstan` نظيفان على ملفاتك · لا خرق حدود · الميزة تعمل **من الواجهة حتى قاعدة البيانات**.
وجود Model أو Route وحده **لا يعني مكتمل**.

## التقرير النهائي
اكتبه في `docs/agent-tasks/REPORT-A.md`: ما نُفِّذ · الملفات الأساسية · أسماء الاختبارات ونتائجها **الفعلية** · **توقيع العقود العامة بالضبط** · ما لم يُنجَز ولماذا.

**لا `git commit` ولا `git push`.**
