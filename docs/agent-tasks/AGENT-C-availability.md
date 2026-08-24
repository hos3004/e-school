# AGENT C — إتاحة المعلم والطالب · الخانات المتوافقة

> **SUPERSEDED — DO NOT EXECUTE.** سجل تاريخي؛ الطابور الحالي في `QUEUE-antigravity.md`.

> مدير المشروع (Claude) يحتفظ بـ Sessions/Scheduling/Substitute/BBB — **لا تقترب منها**.
> أنت تبني **المصدر** الذي يقرأ منه المدير، لا الجدولة نفسها.

## اقرأ أولًا (إلزامي)
1. `CLAUDE.md` — عقد العمل.
2. `docs/client-answers.md` — **قسم `CLIENT UPDATE — 2026-08-22`.** أقسام §د · §هـ · §و تخصك.
3. `docs/phase-1-critical-modules.md` — جدول «3. الإتاحة والمطابقة» صفوف C1–C8.
4. `config/scheduling.php` §availability و§conflicts — **قواعدك هنا. لا تكرّر أرقامها في الكود.**
5. `config/admission.php` §matching — `min_overlap_minutes` و`requires_availability_overlap`.

## البيئة
```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --filter=<Name>
docker compose exec -T app vendor/bin/pint
docker compose exec -T postgres psql -U eschool -d eschool -c "\d teacher_availability"
```
PostgreSQL — لا دوال MySQL. **~212 اختبارًا فاشلًا سابقًا لا علاقة لها بك — لا تصلحها.**

## ملفاتك حصريًا — انتبه، وكيلان آخران داخل نفس الموديولات
- `modules/Staff/**` — **فقط الملفات التي اسمها `TeacherAvailability*`**
- `modules/Students/**` — **فقط الملفات التي اسمها `StudentAvailability*`**
- `modules/Scheduling/src/**` — **فقط الملفات التي اسمها `Availability*`** أو `CompatibleSlot*`

**ممنوع عليك تمامًا:** `StaffProfile.php` · `StudentProfile.php` · أي ملف Sessions · أي ملف Scheduling آخر.

**بادئة هجراتك الحصرية `2026_08_22_13*`:**
```
modules/Staff/database/migrations/2026_08_22_130000_extend_teacher_availability.php
modules/Students/database/migrations/2026_08_22_130100_create_student_availability_table.php
```

---

## C1 · توسعة `teacher_availability`

الجدول الحالي (افحصه بـpsql) فيه: `id` · `staff_profile_id` · `weekday` · `start_time` · `end_time` · `timezone` · `effective_from` · `effective_to` · `created_at`.

أضف:
- `organization_id` char(26) FK
- `status` — `draft` | `pending_approval` | `approved` | `rejected` (enum PHP + عمود string)
  الافتراضي يعتمد على `config('scheduling.availability.teacher_requires_approval')`: إن كانت `false` فالحالة `approved` مباشرة.
- `approved_by` nullable · `approved_at` nullable · `rejection_reason` nullable
- `recurrence` jsonb nullable — قاعدة التكرار (RRULE أو `{"freq":"weekly","interval":1}`)
- `source` — `teacher` | `admin` (من أدخلها)
- `notes` nullable · `updated_at` · softDeletes

جدول الاستثناءات `teacher_availability_exceptions`:
`id` · `staff_profile_id` FK · `date` · `type` (`unavailable` | `extra_slot`) · `start_time` nullable · `end_time` nullable · `reason` · `created_by` · timestamps

`TeacherAvailabilityStatus` enum مع `canTransitionTo()` — اتبع نمط `modules/Enrollments/src/Domain/Enums/EnrollmentStatus.php`.

## C2 · `student_availability`

نفس البنية بالضبط لكن على `student_profile_id`، **بلا حالة اعتماد** (`config('scheduling.availability.student_requires_approval') = false`).
`student_availability_exceptions` بنفس شكل جدول استثناءات المعلم.

## C3 · دمج فترات عدم الإتاحة
جدول `teacher_leaves` موجود بالفعل (`status = approved`). **اقرأ منه ولا تعدّله** — هو ملك وكيل آخر.
حساب الإتاحة الفعلية = الخانات المعتمدة − الاستثناءات − الإجازات المعتمدة.

## C4 · Timezone — النقطة الأخطر
- `start_time`/`end_time` أوقات **محلية** بالنسبة لـ`timezone` الصف.
- التحويل إلى UTC يتم عند **إسقاط الخانة على تاريخ محدد**، لا عند التخزين.
- انتبه للتوقيت الصيفي: استخدم `CarbonImmutable::parse($date.' '.$time, $timezone)->utc()`.
- **اختبر صراحةً** خانة في `Africa/Cairo` مقابل خانة في `Asia/Riyadh` في نفس اليوم.

## C5–C6 · الخانات المتوافقة — المخرَج الأهم

`modules/Scheduling/src/Application/Queries/AvailabilityQueryService.php` ينفّذ عقدًا عامًا
`modules/Scheduling/src/Domain/Contracts/AvailabilityQueries.php`:

```php
/**
 * الخانات التي يتقاطع فيها الطالب مع المعلم فعليًا خلال المدى المطلوب.
 * @return list<CompatibleSlot>
 */
public function compatibleSlots(
    string $studentProfileId,
    string $staffProfileId,
    CarbonImmutable $from,
    CarbonImmutable $to,
    int $minMinutes = 0,
): array;

/**
 * المعلمون الذين تتقاطع إتاحتهم مع الطالب — للترشيح والمطابقة.
 * @param  list<string>  $staffProfileIds  فلتر مسبق (مؤهلون · نفس الجنس · نشطون)
 * @return list<TeacherSlotMatch>
 */
public function teachersMatchingStudent(
    string $studentProfileId,
    array $staffProfileIds,
    CarbonImmutable $from,
    CarbonImmutable $to,
): array;

/**
 * هل هذا الوقت داخل إتاحة المعلم المعلنة؟ يستخدمه المدير في الجدولة والبديل.
 */
public function isWithinTeacherAvailability(
    string $staffProfileId,
    CarbonImmutable $start,
    CarbonImmutable $end,
): bool;
```

`CompatibleSlot` DTO: `date` · `startUtc` · `endUtc` · `minutes` · `staffProfileId`.
`TeacherSlotMatch` DTO: `staffProfileId` · `totalOverlapMinutes` · `slots: list<CompatibleSlot>`.

القواعد:
- `minMinutes` الافتراضي من `config('admission.matching.min_overlap_minutes')`.
- المدى الأقصى من `config('scheduling.availability.compatibility_horizon_days')`.
- **استثنِ الأوقات التي لدى المعلم فيها حصة بالفعل** — اقرأ جدول `sessions` بـ`DB::table()` (حالات `cancelled_*` و`postponed` لا تحجز الوقت). **ممنوع استيراد `Modules\Sessions\Domain\Models\Session`.**
- خانات المعلم غير المعتمدة (`status != approved`) لا تدخل النتيجة عند تفعيل الاعتماد.
- الأداء: تجنّب N+1؛ استعلام واحد لكل جدول ثم دمج في PHP.

**هذا العقد هو ما ينتظره المدير لترشيح المدرس البديل ومنع التعارض. أنجزه أولًا وسجّل توقيعه النهائي في تقريرك.**

## C7 · اعتماد إتاحة المعلم
`ApproveTeacherAvailabilityAction` + `RejectTeacherAvailabilityAction` بصلاحية
`config('scheduling.availability.teacher_approver_permission')`، والرفض بسبب مكتوب.
حدث Domain `TeacherAvailabilityApproved` — الوكيل D يربطه بالإشعارات. **لا ترسل إشعارًا بنفسك.**

## C8 · واجهة الحجز الذاتي المتقدمة
**Deferred to Phase 1.5 — لا تنفّذها.** فقط تأكد أن العقد أعلاه يسمح ببنائها لاحقًا.

## Filament
Relation Managers للإتاحة والاستثناءات فقط، داخل ملفات اسمها `*Availability*` تحت
`modules/Staff/src/Presentation/Filament/**` و`modules/Students/src/Presentation/Filament/**`.
**لا تعدّل `StudentProfileResource.php` ولا `StaffProfileResource.php` نفسيهما** — سجّل في تقريرك السطر المطلوب إضافته وسيضيفه مالكهما.

---

## القواعد الملزمة
- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*`. للقراءة عبر الحدود استخدم `DB::table()` بأسماء الجداول أو عقدًا عامًا.
- **لا أرقام سياسة في الكود** — `config/scheduling.php` و`config/admission.php`.
- **UTC في التخزين** · العرض بتوقيت المستخدم.
- **لا حذف** — SoftDeletes.
- **الترجمات:** `resources/lang/{ar,en}/`. ممنوع نص مباشر.
- **الصلاحيات:** Policy + `can:`.
- `declare(strict_types=1)` · `final` · تعليقات عربية تشرح **لماذا**.

## الاختبارات المطلوبة
- خانة معلم في `Africa/Cairo` وخانة طالب في `Asia/Riyadh` → التقاطع محسوب صحيحًا بالـUTC
- خانة أقل من `min_overlap_minutes` لا تُعاد
- إجازة معتمدة تُسقط الخانة
- استثناء `unavailable` يُسقط الخانة في يومه فقط
- حصة قائمة في نفس الوقت تُسقط الخانة
- خانة `pending_approval` لا تظهر عند تفعيل الاعتماد وتظهر عند تعطيله
- `isWithinTeacherAvailability` يعطي true/false صحيحًا على الحدود `[start, end)`

## تعريف «خلصت»
`migrate --force` ينجح · اختباراتك تمر · pint نظيف · لا خرق حدود · العقد يعمل فعليًا ببيانات حقيقية.

## التقرير النهائي
`docs/agent-tasks/REPORT-C.md`: ما نُفِّذ · الملفات · الاختبارات ونتائجها **الفعلية** · **توقيع `AvailabilityQueries` النهائي بالضبط** · السطور التي يحتاج مالك `StudentProfileResource`/`StaffProfileResource` إضافتها · ما لم يُنجَز ولماذا.

**لا `git commit` ولا `git push`.**
