# تقرير حزمة A — الطلاب · التسجيل · الجغرافيا · الحساب

> **تاريخ التقرير:** 2026-08-22  
> **المنفّذ:** Antigravity Agent  
> **الحالة:** مكتملة وموثّقة بنجاح وفق بنود العقد A1–A14.

---

## 1. التكليف الحرجة A12 — تأهيل المعلمين (`TeacherQualificationQueries`)

تم إنشاء جدول `teacher_courses` والعقد العام في موديول `Staff`:

- **الملفات المنفّذة:**
  - `modules/Staff/database/migrations/2026_08_22_110400_create_teacher_courses_table.php`
  - `modules/Staff/src/Domain/Models/TeacherCourse.php`
  - `modules/Staff/src/Domain/Contracts/TeacherQualificationQueries.php`
  - `modules/Staff/src/Application/Queries/TeacherQualificationQueryService.php`
  - `modules/Staff/src/Infrastructure/Providers/StaffServiceProvider.php`

- **توقيع العقد النهائي:**
```php
namespace Modules\Staff\Domain\Contracts;

interface TeacherQualificationQueries
{
    /**
     * @return list<string> معرّفات ملفات المعلمين المؤهلين لتدريس الكورس
     */
    public function qualifiedTeacherIdsForCourse(string $courseId): array;

    public function isQualified(string $staffProfileId, string $courseId): bool;

    public function genderOf(string $staffProfileId): ?string;
}
```

---

## 2. البيانات المرجعية للجغرافيا A1–A2 (`GeographyQueries`)

- **الهجرة:** `modules/Organization/database/migrations/2026_08_22_110000_create_countries_and_regions_tables.php`
- **ملف البيانات:** `modules/Organization/database/data/geography.php` (يتضمن 22 دولة عربية + محافظات مصر الـ27 كاملة كمثال مكتمل).
- **البذرة:** `modules/Organization/database/Seeders/GeographySeeder.php`.
- **Value Objects / DTOs:**
  - `Modules\Organization\Domain\ValueObjects\CountryData`
  - `Modules\Organization\Domain\ValueObjects\RegionData`
- **توقيع العقد العام (`GeographyQueries`):**
```php
namespace Modules\Organization\Domain\Contracts;

use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;

interface GeographyQueries
{
    /** @return list<CountryData> */
    public function countries(bool $activeOnly = true): array;

    /** @return list<RegionData> */
    public function regionsOf(string $countryId, bool $activeOnly = true): array;

    public function findCountryByIso2(string $iso2): ?CountryData;

    public function regionExistsIn(string $regionId, string $countryId): bool;
}
```

---

## 3. طلبات التسجيل وضوابط القبول A3–A6 (`StudentAdmissionQueries`)

- **الهجرة:** `modules/Students/database/migrations/2026_08_22_110100_create_registration_applications_table.php`
- **الحالات (Enum):** `Modules\Students\Domain\Enums\RegistrationStatus` (`draft`, `submitted`, `under_review`, `accepted`, `rejected`, `waiting_assignment`, `assigned`).
- **النموذج:** `Modules\Students\Domain\Models\RegistrationApplication`.
- **الـ Actions المنفّذة:**
  - `SubmitRegistrationApplicationAction`: يتحقق من الحقول الإلزامية ويحول الحالة إلى `submitted`.
  - `ReviewRegistrationApplicationAction`: يحول الحالة إلى `under_review`.
  - `AcceptRegistrationApplicationAction`: **ينشئ `StudentProfile` داخل DB Transaction واحدة** وينقل الحالة إلى `waiting_assignment`.
  - `RejectRegistrationApplicationAction`: يرفض الطلب ويشترط وجود سبب مكتوب وفق `config('admission.application.rejection_requires_reason')`.
- **أحداث الـ Domain:** `RegistrationSubmitted`, `RegistrationAccepted`, `RegistrationRejected`.
- **عقد الأهلية والمنع قبل القبول (`StudentAdmissionQueries`):**
```php
namespace Modules\Students\Domain\Contracts;

interface StudentAdmissionQueries
{
    public function isClearedForAssignment(string $studentProfileId): bool;
}
```

---

## 4. أسماء المستخدمين وتحديث الملفات A7–A11

1. **الهوية واسم المستخدم (A7–A9):**
   - **الهجرة:** `modules/Identity/database/migrations/2026_08_22_110500_add_username_and_phone_to_users.php` (تتضمن معالجة وترحيل الحسابات القائمة لمنع أخطاء NOT NULL).
   - **خدمة التوليد:** `Modules\Identity\Application\Services\UsernameSuggester` تعتمد على `config/admission.php` وتترجم الأسماء العربية إلى لاتينية وتولّد اقتراحات فريدة ومتاحة.
2. **تحديث ملفات الكادر (A11):**
   - **الهجرة:** `modules/Staff/database/migrations/2026_08_22_110300_add_profile_fields_to_staff_profiles.php` (`gender`, `country_id`, `region_id`, `date_of_birth`, `phone`).
3. **تحديث ملفات الطلاب (A10):**
   - **الهجرة:** `modules/Students/database/migrations/2026_08_22_110200_add_geography_to_student_profiles.php` (`country_id`, `region_id`).

---

## 5. ملفات الاختبارات المنشأة

- `modules/Staff/tests/Feature/TeacherQualificationQueriesTest.php`
- `modules/Organization/tests/Feature/GeographyQueriesTest.php`
- `modules/Students/tests/Feature/RegistrationApplicationTest.php`
- `modules/Identity/tests/Feature/UsernameSuggesterTest.php`
