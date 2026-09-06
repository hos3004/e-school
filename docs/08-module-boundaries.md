# 08 — حدود الموديولات

Modular Monolith يفشل بطريقة واحدة: أن يصبح Monolith فيه مجلدات.
هذه الوثيقة تمنع ذلك، واختبارات `tests/Architecture` تفرضها آليًا.

---

## 1. الطبقات وقاعدة الاتجاه

```
الطبقة 7   Reporting                          ← يقرأ من الجميع، لا أحد يقرأ منه
الطبقة 6   Payroll · Billing
الطبقة 5   Discipline · Messaging
الطبقة 4   Assignments · Assessments · AcademicReports · Certificates
الطبقة 3   Scheduling · Sessions · Attendance · VirtualClassroom · Recordings
الطبقة 2   Academics · Groups · Enrollments · Content
الطبقة 1   Students · Guardians · Staff
الطبقة 0   Organization · Identity · AccessControl · Audit
           Integrations · Notifications                ← لا يعتمد على أحد
```

**القاعدة:** الموديول يعتمد على طبقة **أدنى** منه فقط.
اعتماد على نفس الطبقة يمر عبر Domain Event. اعتماد على طبقة أعلى **ممنوع دائمًا**.

---

## 2. طرق التواصل الثلاث — لا رابعة

### أ. Domain Events — الافتراضي

```php
// في Sessions — الموديول المالك ينشر
event(new SessionFinalized(
    sessionId: $session->id,
    teacherId: $session->staff_profile_id,
    outcome: $session->status->payrollOutcome(),
));

// في Payroll — يستمع ولا يعرف من أرسل
final class CreateEarningEntryOnSessionFinalized
{
    public function handle(SessionFinalized $event): void { ... }
}
```

**متى تستخدمه:** عندما لا يحتاج الناشر إلى نتيجة. وهذه هي الحالة الغالبة.

### ب. Public Contracts — عندما تحتاج إجابة الآن

```php
// Staff يملك العقد ويعلنه في Domain/Contracts
namespace Modules\Staff\Domain\Contracts;

interface TeacherRateResolver
{
    public function resolveFor(
        string $teacherId,
        string $courseId,
        string $sessionType,
        CarbonImmutable $asOf,
    ): Money;
}

// Payroll يعتمد على الواجهة فقط
public function __construct(private TeacherRateResolver $rates) {}
```

**متى تستخدمه:** عندما تحتاج قيمة متزامنة لإتمام عملية. العقد يُعرَّف
في الموديول **المالك** ويُنفَّذ فيه، والمستهلك يعرف الواجهة فقط.

### ج. Query Services — للقراءة عبر الحدود

```php
namespace Modules\Students\Domain\Contracts;

interface StudentDirectory
{
    public function summary(string $studentId): ?StudentSummary; // DTO
    /** @return list<StudentSummary> */
    public function summariesFor(array $studentIds): array;
}
```

**تُرجع DTO دائمًا — لا Eloquent Model.** النموذج يحمل علاقات وسلوكًا
وقابلية تعديل، وتمريره عبر الحدود يفتح باب الالتفاف على القواعد.

---

## 3. الممنوعات القاطعة

| ممنوع | لماذا | البديل |
|-------|-------|--------|
| `use Modules\A\Domain\Models\X` داخل `B` | كسر التغليف | Contract أو Event |
| `join` بين جدولي موديولين في كود التطبيق | ارتباط خفي | Read Model في Reporting |
| استدعاء Action من موديول آخر | ارتباط متزامن | Event أو Contract |
| Facade يخترق الحدود | إخفاء الاعتماد | حقن الواجهة صراحةً |
| هجرة موديول تعدّل جدول موديول آخر | فوضى ملكية | الموديول المالك يعدّل جدوله |
| Policy في موديول تفحص كيان موديول آخر | تشتيت الصلاحيات | كل موديول يحرس كيانه |

---

## 4. ملكية الجداول

الموديول يكتب في جداوله **فقط**. جدول واحد له مالك واحد بالضبط.

| الموديول | يملك |
|----------|------|
| Organization | `organizations` · `organization_settings` · `academic_calendars` · `holidays` · `countries` · `regions` |
| Identity | `users` · `user_devices` · `password_reset_tokens` |
| AccessControl | `roles` · `permissions` · `*_has_*` |
| Audit | `audit_log` |
| Integrations | `integration_providers` · `integration_connections` · `integration_webhook_deliveries` |
| Students | `student_profiles` · `registration_applications` · `registration_forms` |
| Guardians | `guardian_profiles` · `guardian_links` |
| Staff | `staff_profiles` · `teacher_contracts` · `teacher_rates` · `teacher_availability` · `teacher_leaves` · `teacher_courses` |
| Academics | `programs` · `levels` · `courses` |
| Groups | `groups` · `group_programs` · `group_teachers` · `group_memberships` |
| Enrollments | `enrollments` · `enrollment_status_history` |
| Content | `course_materials` |
| Scheduling | `schedules` · `schedule_weekly_slots` · `postponement_requests` |
| Sessions | `sessions` · `session_status_history` · `session_participants` |
| Attendance | `attendances` |
| VirtualClassroom | `classrooms` · `classroom_events` |
| Recordings | `recordings` · `recording_views` |
| Assignments | `assignments` · `assignment_submissions` |
| Assessments | `assessments` · `questions` · `assessment_attempts` |
| AcademicReports | `session_reports` · `session_report_students` · `monthly_reports` |
| Certificates | `certificate_templates` · `certificates` · `badges` · `badge_awards` |
| Discipline | `violation_events` · `discipline_actions` · `reactivation_requests` |
| Messaging | `conversations` · `conversation_participants` · `messages` · `class_wall_*` · `whatsapp_inbound` |
| Notifications | `notification_outbox` · `notification_delivery_attempts` · `notification_preferences` · `notification_templates` |
| Payroll | `payroll_periods` · `payroll_entries` · `payroll_adjustments` · `staff_obligations` |
| Billing | `invoices` · `payments` · `student_packages` · `coupons` · `refunds` |
| Reporting | `report_*` (Read Models فقط) |

---

## 5. الموديولات المختومة

`config/modules.php` يعلن أربعة موديولات **مختومة** — لا يستورد أحد
كياناتها بأي حال:

| الموديول | لماذا مختوم |
|----------|--------------|
| `Payroll` | سلامة مالية — كل مسار يمر بالعقود المعلنة |
| `Billing` | نفس السبب |
| `Audit` | يُكتب فيه بحدث واحد فقط، ولا يُقرأ إلا بصلاحية |
| `Identity` | بيانات اعتماد وجلسات — لا وصول جانبي |

---

## 6. أمثلة تطبيقية

### مثال 1: الحصة انتهت

```
Sessions ── SessionFinalized ──┬──> Attendance      يقفل كشف الحضور
                               ├──> Payroll         ينشئ القيدة
                               ├──> Discipline      يقيّم المخالفات
                               ├──> AcademicReports يطالب بتقرير الحصة
                               ├──> Recordings      يبدأ متابعة التسجيل
                               ├──> Notifications   يخطر الأطراف
                               └──> Reporting       يحدّث اللوحات
```

`Sessions` لا يعرف أن Payroll موجود. ستة مستمعين اليوم، وقد يصيرون
عشرة غدًا، بلا تعديل سطر واحد في `Sessions`.

### مثال 2: Payroll يحتاج سعر الحصة

خطأ:
```php
$rate = TeacherRate::where('staff_profile_id', $id)->first(); // كسر الحدود
```

صواب:
```php
public function __construct(private TeacherRateResolver $rates) {}

$amount = $this->rates->resolveFor($teacherId, $courseId, $type, $sessionDate);
```

### مثال 3: تقرير يحتاج بيانات من خمسة موديولات

لا يستورد `Reporting` نماذج أحد. يبني Read Models خاصة به من الأحداث:

```
SessionFinalized · AttendanceConfirmed · ViolationRecorded
        ↓
Reporting listeners → report_student_monthly (جدول مسطّح مملوك لـ Reporting)
        ↓
لوحة المعلومات تقرأ من جدول واحد — بلا join عابر للحدود
```

---

## 7. الفرض الآلي

`tests/Architecture/ModuleBoundariesTest.php` يفشل CI عند:

1. استيراد `Modules\A\...\Domain\Models\*` من `Modules\B`.
2. اعتماد موديول على طبقة أعلى منه.
3. استيراد أي شيء من موديول مختوم.
4. وجود Eloquent Model في توقيع دالة داخل `Domain\Contracts`.
5. هجرة في موديول تلمس جدولًا لا يملكه.
6. غياب `declare(strict_types=1)` في أي ملف تحت `modules/`.

**هذه الاختبارات تُكتب أولًا، قبل أول موديول.** انظر
[`16-testing-strategy.md`](16-testing-strategy.md).
