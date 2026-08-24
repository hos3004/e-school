# AGENT B — البرامج · الكورسات · التصنيفات · الأهلية · المجموعات

> **SUPERSEDED — DO NOT EXECUTE.** سجل تاريخي؛ الطابور الحالي في `QUEUE-antigravity.md`.

> مدير المشروع (Claude) يحتفظ بـ Sessions/Scheduling/Substitute/BBB — **لا تقترب منها**.

## اقرأ أولًا (إلزامي)
1. `CLAUDE.md` — عقد العمل. غير قابل للتفاوض.
2. `docs/client-answers.md` — **قسم `CLIENT UPDATE — 2026-08-22` هو المرجع الأحدث.** أقسام §ب · §ز · §ح تخصك.
3. `docs/phase-1-critical-modules.md` — جدول «2. الأكاديمي» صفوف B1–B12.
4. `config/admission.php` §eligibility و§matching — **قواعدك هنا. لا تكرّر أرقامها في الكود.**
5. `config/academic.php` — أنواع الحصص وسعة المجموعات.

## البيئة
**كل أوامر PHP/Composer/Artisan داخل Docker** (الحاويات تعمل):
```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --filter=<Name>
docker compose exec -T app vendor/bin/pint
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T postgres psql -U eschool -d eschool -c "\d courses"
```
PostgreSQL — لا دوال MySQL.

**تنبيه:** المستودع فيه ~212 اختبارًا فاشلًا سابقًا لا علاقة لها بك. **لا تحاول إصلاحها.** شغّل اختباراتك بـ`--filter` فقط.

## ملفاتك حصريًا
- `modules/Academics/**`
- `modules/Groups/**`
- `modules/Enrollments/**`

**بادئة هجراتك الحصرية `2026_08_22_12*`:**
```
modules/Academics/database/migrations/2026_08_22_120000_create_program_taxonomy_tables.php
modules/Academics/database/migrations/2026_08_22_120100_extend_programs_and_courses.php
modules/Academics/database/migrations/2026_08_22_120200_create_program_eligibility_table.php
```

## اعتماديات على وكلاء يعملون بالتوازي الآن
**الوكيل A** ينشئ `countries` و`regions` بالبادئة `2026_08_22_11*` — أي أن هجراتك (`12*`) تعمل **بعدها**، فالـFK إلى `regions` آمن. **لا تنشئ الجدولين بنفسك.**

الوكيل A يوفّر عقدين ستستدعيهما:
- `Modules\Students\Domain\Contracts\StudentAdmissionQueries::isClearedForAssignment(string $studentProfileId): bool`
- `Modules\Staff\Domain\Contracts\TeacherQualificationQueries::{qualifiedTeacherIdsForCourse, isQualified, genderOf}`

إن لم يكونا موجودين وقت كتابتك، **اكتب الكود مقابل الواجهة (interface) واستخدم حقن التبعية** — لا تنسخ منطقهما ولا تنشئ نسخة ثانية منهما.

---

## B1–B2 · تصنيف مرن — لا أعمدة ثابتة

**ممنوع** جعل التصنيف عمودًا نصيًا داخل `courses`.

- `program_categories`: `id` · `organization_id` · `program_id` nullable (تصنيف عام أو تابع لبرنامج) · `parent_id` nullable · `code` · `name` jsonb (ar/en/fr) · `description` jsonb · `is_active` · `sort_order` · softDeletes
- `course_category` (pivot): `course_id` · `category_id` · unique معًا — **كورس ينتمي لأكثر من تصنيف/تخصص**

`parent_id` الذاتي هو ما يمثّل Categories → Subcategories → Tracks بلا جداول مكرَّرة.
**أسماء التقسيمات حرة تمامًا** (jsonb) — لا enum لأسماء التصنيفات.

## B3–B5 · حقول البرنامج والكورس

`programs` أضف:
- `program_type` — `fixed_duration` | `ongoing`
- `start_date` nullable · `end_date` nullable
- **CHECK constraint في PostgreSQL:** `fixed_duration` يستلزم `start_date` و`end_date`؛ `ongoing` يستلزم `end_date IS NULL`
- `target_gender` — `male` | `female` | `all` (افتراضي `all`)
- `age_from` smallint nullable · `age_to` smallint nullable
- `objectives` jsonb nullable · `language` nullable

`courses` أضف:
- `session_mode` — `individual` | `group` | `both`
- `age_from` · `age_to` · `target_gender` (تخصيص على مستوى الكورس يتجاوز البرنامج عند وجوده)
- `default_duration_minutes` nullable · `sessions_per_week` nullable · `prerequisites` jsonb nullable

Enums جديدة في `modules/Academics/src/Domain/Enums/`: `ProgramType` · `SessionMode` · `TargetGender` — backed string مع `label()` يقرأ من ملف الترجمة.

**التسعير:** لا تنفّذ Billing إطلاقًا. فقط تأكد أن النموذج لا يمنع مستقبلًا: `fixed_duration` → مبلغ واحد، `ongoing` → اشتراك شهري أو بالحصة. اكتب ذلك تعليقًا في الهجرة ولا تضف أعمدة سعر جديدة.

## B6–B8 · أهلية البرنامج

`program_eligibility`: `id` · `program_id` FK unique · `countries` jsonb (list of country_id — **فارغ = بلا قيد**) · `regions` jsonb (**فارغ = بلا قيد**) · `age_from` · `age_to` · `gender` · `manual_approval_required` boolean default true · `teacher_gender_rule` (`same`|`any`، الافتراضي من `config('admission.matching.default_gender_rule')`) · `requires_individual_sessions` boolean · timestamps

**قواعد المطابقة عامة — ممنوع منعًا باتًا** أن يظهر اسم «Quran» أو أي اسم برنامج في شرط داخل الكود.
قاعدة «الرجال مع مدرسين رجال» تُمثَّل بـ`teacher_gender_rule = same` على أي برنامج.

`modules/Academics/src/Application/Services/EligibilityEvaluator.php`:
```php
public function evaluate(string $programId, ApplicantFacts $facts): EligibilityResult;
```
- `ApplicantFacts` DTO: `dateOfBirth` · `gender` · `countryId` · `regionId` — **لا Eloquent models عابرة للحدود**
- `EligibilityResult` DTO: `eligible: bool` · `violations: list<string>` (مفاتيح ترجمة) · `requiresManualApproval: bool` · `blocking: list<string>` · `warnings: list<string>`
- شدة كل مخالفة من `config('admission.eligibility.on_violation')` — `block` مقابل `warn`. **لا تكتب الشدة في الكود.**
- **القوائم الفارغة تعني «بلا قيد» لا «لا أحد مؤهل»** — اختبر هذه الحالة صراحةً.

عقد عام في `modules/Academics/src/Domain/Contracts/ProgramRulesQueries.php`:
```php
public function eligibilityOf(string $programId): ?ProgramEligibilityData;   // DTO
public function teacherGenderRule(string $programId): string;               // 'same' | 'any'
public function programIdsOfCourse(string $courseId): array;                // list<string>
public function sessionModeOfCourse(string $courseId): ?string;
```
**المدير يحتاج `teacherGenderRule` و`programIdsOfCourse` لترشيح المدرس البديل — أنجزهما أولًا.**

## B11–B12 · التوزيع وصحة العلاقات

- `AssignStudentToProgramAction` و`AssignStudentToGroupAction`: **يجب أن يستدعيا `StudentAdmissionQueries::isClearedForAssignment()` ويفشلا إن كان false.** لا توزيع قبل قبول طلب التسجيل. اختبر هذا صراحةً.
- كلاهما يستدعي `EligibilityEvaluator`؛ مخالفة `block` تمنع التوزيع إلا بصلاحية `config('admission.eligibility.override_permission')` **وسبب مكتوب** يدخل `audit_log`.
- قواعد صحة العلاقات:
  - معلم المجموعة يجب أن يكون مؤهلًا للمادة (`TeacherQualificationQueries`)
  - سعة المجموعة من `config('academic.groups')`
  - `session_mode = individual` يمنع مجموعة بأكثر من طالب

## Filament
داخل `modules/{Academics,Groups,Enrollments}/src/Presentation/Filament/**` فقط:
Programs · Courses · Categories (شجرة parent/child) · Levels · Groups · Program Eligibility (Relation Manager على البرنامج).
مع بحث وفلاتر وvalidation وصلاحيات وترجمات.

---

## القواعد الملزمة
- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*` من موديول آخر. أحداث Domain أو عقود عامة أو Query Services تعيد DTOs فقط. `tests/Architecture` تفرضها.
- **لا join عابر للحدود** في كود التطبيق.
- **لا أرقام سياسة في الكود** — `config/admission.php` و`config/academic.php`.
- **Enums لا strings** لكل دورة حياة، مع `canTransitionTo()`.
- **لا حذف** — SoftDeletes.
- **الترجمات:** `resources/lang/{ar,en}/` لكل موديول. ممنوع نص مباشر في الواجهة.
- **الصلاحيات:** Policy + `can:`. ممنوع فحص اسم الدور.
- **ممنوع Scope Creep:** لا موديول جديد. لا abstraction/table/service جديد بلا حاجة Domain حقيقية. الأولوية للتكامل لا لزيادة الملفات.

## الاختبارات المطلوبة
- كورس ينتمي لتصنيفين ويظهر تحتهما
- `fixed_duration` بلا `end_date` يُرفض على مستوى القاعدة
- الأهلية: قائمة دول فارغة = الجميع مؤهل · منطقة غير مدرجة = `block` · عمر خارج المدى = `warn`
- `teacher_gender_rule = same` يمنع إسناد معلم من جنس مختلف
- **توزيع طالب لم يُقبل طلبه يفشل**
- التجاوز بلا سبب يفشل؛ وبسبب ينجح ويكتب في `audit_log`

## تعريف «خلصت»
`migrate --force` ينجح · اختباراتك تمر · pint و phpstan نظيفان على ملفاتك · لا خرق حدود · تعمل **من الواجهة حتى قاعدة البيانات**.

## التقرير النهائي
اكتبه في `docs/agent-tasks/REPORT-B.md`: ما نُفِّذ · الملفات · أسماء الاختبارات ونتائجها **الفعلية** · **توقيع `ProgramRulesQueries` النهائي بالضبط** · ما لم يُنجَز ولماذا.

**لا `git commit` ولا `git push`.**
