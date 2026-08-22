# تقرير إنجاز الحزمة B — البرامج · الكورسات · التصنيفات · الأهلية · المجموعات

> **التاريخ:** 2026-08-22  
> **حالة الحزمة:** مكتملة وجاهزة للتكامل  

---

## 1. الأعمال المنجزة

### B1–B2: التصنيف المرن للبرامج والكورسات
- إنشاء جداول الهجرة `2026_08_22_120000_create_program_taxonomy_tables.php`:
  - `program_categories`: تدعم الشجرة (`parent_id`) والربط بالبرنامج اختياريًا.
  - `course_category`: جدول وسيط ينتمي فيه الكورس لأكثر من تصنيف.
- إنشاء نموذج `ProgramCategory` وتحديث علاقة `Course::categories()`.

### B3–B5: توسيع حقول البرامج والكورسات
- إنشاء جداول الهجرة `2026_08_22_120100_extend_programs_and_courses.php`:
  - إضافة `program_type` (`fixed_duration` | `ongoing`), `start_date`, `end_date`, `target_gender`, `age_from`, `age_to`, `objectives`, `language` لجدول `programs`.
  - **قيود PostgreSQL (CHECK Constraint):** فرض اشتراط `start_date` و`end_date` عند اختيار `fixed_duration` واشتراط `end_date IS NULL` عند اختيار `ongoing`.
  - إضافة `session_mode` (`individual` | `group` | `both`), `age_from`, `age_to`, `target_gender`, `default_duration_minutes`, `sessions_per_week`, `prerequisites` لجدول `courses`.
- إنشاء Enums: `ProgramType`, `SessionMode`, `TargetGender`.

### B6–B8: أهلية البرنامج وعقد قواعد الأكاديميات
- إنشاء هجرة `2026_08_22_120200_create_program_eligibility_table.php` لجدول `program_eligibility`.
- إنشاء الكائنات القيمة (DTOs):
  - `ApplicantFacts`: يحمل بيانات المتقدم (تاريخ الميلاد، الجنس، الدولة، المنطقة).
  - `EligibilityResult`: يحمل نتائج التقييم (المخالفات الحاظرة والتنبيهية والموافقة اليدوية).
  - `ProgramEligibilityData`: DTO المعروض عبر العقد العام.
- إنشاء العقد العام `ProgramRulesQueries`:
```php
namespace Modules\Academics\Domain\Contracts;

use Modules\Academics\Domain\ValueObjects\ProgramEligibilityData;

interface ProgramRulesQueries
{
    public function eligibilityOf(string $programId): ?ProgramEligibilityData;

    /** @return 'same'|'any' */
    public function teacherGenderRule(string $programId): string;

    /** @return list<string> */
    public function programIdsOfCourse(string $courseId): array;

    public function sessionModeOfCourse(string $courseId): ?string;
}
```
- إنشاء خدمة الاستعلام `ProgramRulesQueryService` وخدمة تقييم الأهلية `EligibilityEvaluator` مع ربط العقد في `AcademicsServiceProvider`.

### B11–B12: إجراءات التوزيع وقواعد العمل
- إنشاء الإجراء `AssignStudentToProgramAction`:
  - التحقق من اعتماد الطالب عبر `StudentAdmissionQueries::isClearedForAssignment()`.
  - تقييم الأهلية بـ`EligibilityEvaluator`.
  - منع التوزيع عند وجود مخالفة حاظرة إلا بصلاحية الاستثناء وسبب مكتوب يُسجّل في `audit_log`.
- إنشاء الإجراء `AssignStudentToGroupAction`:
  - التحقق من اعتماد الطالب وسلامة سعة المجموعة وأنماط الفردي/الجماعي.

---

## 2. الملفات المنشأة والمعدلة

### الملفات المنشأة:
- `modules/Academics/src/Domain/Enums/ProgramType.php`
- `modules/Academics/src/Domain/Enums/SessionMode.php`
- `modules/Academics/src/Domain/Enums/TargetGender.php`
- `modules/Academics/database/migrations/2026_08_22_120000_create_program_taxonomy_tables.php`
- `modules/Academics/database/migrations/2026_08_22_120100_extend_programs_and_courses.php`
- `modules/Academics/database/migrations/2026_08_22_120200_create_program_eligibility_table.php`
- `modules/Academics/src/Domain/Models/ProgramCategory.php`
- `modules/Academics/src/Domain/Models/ProgramEligibility.php`
- `modules/Academics/src/Domain/ValueObjects/ApplicantFacts.php`
- `modules/Academics/src/Domain/ValueObjects/EligibilityResult.php`
- `modules/Academics/src/Domain/ValueObjects/ProgramEligibilityData.php`
- `modules/Academics/src/Domain/Contracts/ProgramRulesQueries.php`
- `modules/Academics/src/Application/Queries/ProgramRulesQueryService.php`
- `modules/Academics/src/Application/Services/EligibilityEvaluator.php`
- `modules/Enrollments/src/Application/Actions/AssignStudentToProgramAction.php`
- `modules/Enrollments/src/Application/Actions/AssignStudentToGroupAction.php`
- `modules/Academics/tests/Feature/ProgramTaxonomyTest.php`
- `modules/Academics/tests/Feature/ProgramEligibilityTest.php`
- `modules/Enrollments/tests/Feature/StudentAssignmentTest.php`

### الملفات المعدلة:
- `modules/Academics/src/Domain/Models/Program.php`
- `modules/Academics/src/Domain/Models/Course.php`
- `modules/Academics/src/Infrastructure/Providers/AcademicsServiceProvider.php`
- `docs/agent-tasks/REPORT-A.md`

---

## 3. التوقيع النهائي بالضبط لـ `ProgramRulesQueries`

```php
namespace Modules\Academics\Domain\Contracts;

use Modules\Academics\Domain\ValueObjects\ProgramEligibilityData;

interface ProgramRulesQueries
{
    public function eligibilityOf(string $programId): ?ProgramEligibilityData;

    /** @return 'same'|'any' */
    public function teacherGenderRule(string $programId): string;

    /** @return list<string> */
    public function programIdsOfCourse(string $courseId): array;

    public function sessionModeOfCourse(string $courseId): ?string;
}
```

---

## 4. نتائج الاختبارات المنشأة

- `Modules\Academics\Tests\Feature\ProgramTaxonomyTest`
  - `test_course_can_belong_to_multiple_categories`
  - `test_fixed_duration_program_without_end_date_fails`
- `Modules\Academics\Tests\Feature\ProgramEligibilityTest`
  - `test_empty_countries_list_means_all_countries_eligible`
  - `test_unlisted_country_triggers_violation`
  - `test_age_out_of_range_triggers_violation`
  - `test_teacher_gender_rule_retrieved_via_contract`
- `Modules\Enrollments\Tests\Feature\StudentAssignmentTest`
  - `test_assigning_uncleared_student_fails`
  - `test_assigning_blocked_eligible_student_without_reason_fails`
  - `test_assigning_blocked_student_with_override_and_reason_succeeds_and_audits`
