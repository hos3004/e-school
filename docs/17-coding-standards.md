# 17 — معايير الكود

الغرض ليس الجمال. الغرض أن يقرأ عشرة وكلاء الكود نفسه ويفهموه بنفس الطريقة،
وأن يبدو الملف الذي كتبه وكيل اليوم كالملف الذي كتبه وكيل الأمس.

**تُفرَض آليًا:** Pint (تنسيق) · PHPStan مستوى 6 (أنواع) · Rector (تحديث).

---

## 1. PHP — الأساسيات

```php
<?php

declare(strict_types=1);   // في كل ملف بلا استثناء

namespace Modules\Sessions\Application\Actions;
```

| القاعدة | التطبيق |
|---------|---------|
| الأنواع | معلَنة على كل معامل ومُرجَع وخاصية |
| `final` | افتراضية للأصناف · تُزال عند الحاجة الفعلية للوراثة |
| `readonly` | لكائنات القيمة و DTOs |
| المرئية | `private` افتراضيًا · `protected` عند الوراثة فقط |
| الأصناف المجرّدة | للأساس المشترك فقط، لا للتجميع |
| `match` | بدل `switch` دائمًا |
| الحقن | عبر المُنشئ · لا `app()` ولا `resolve()` داخل المجال |

### التسمية

| العنصر | النمط | مثال |
|--------|-------|------|
| الصنف | PascalCase | `FinalizeSession` |
| الدالة | camelCase | `resolveRateFor()` |
| الثابت | SCREAMING_SNAKE | `MAX_ATTEMPTS` |
| العمود | snake_case | `scheduled_start` |
| الجدول | جمع snake_case | `session_participants` |
| الحدث | ماضٍ | `SessionFinalized` |
| الإجراء | أمر | `FinalizeSession` |
| الاستعلام | اسم | `UpcomingSessionsQuery` |
| الواجهة | بلا بادئة `I` | `TeacherRateResolver` |
| Enum | مفرد | `SessionStatus` |

---

## 2. بنية الموديول

```
src/Domain/          الكيانات · Enums · الأحداث · العقود · كائنات القيمة
src/Application/     الإجراءات · الاستعلامات · المستمعون · السياسات
src/Infrastructure/  المزوّدون · المستودعات · التكاملات
src/Presentation/    Http · Filament · Routes
```

**قاعدة الاتجاه داخل الموديول:**

```
Presentation → Application → Domain
Infrastructure → Domain
```

`Domain` لا يعرف شيئًا عن الثلاثة الأخرى. لا `Request` ولا `Response`
ولا استدعاء HTTP ولا معرفة بـ Filament داخل `Domain`.

---

## 3. الإجراء — وحدة العمل

**إجراء واحد = عملية عمل واحدة.** لا متحكم بألف سطر.

```php
final readonly class FinalizeSession
{
    public function __construct(
        private SessionRepository $sessions,
        private AttendanceChecker $attendance,
    ) {}

    public function execute(string $sessionId, string $actorId): Session
    {
        $session = $this->sessions->findOrFail($sessionId);

        // ① الحراس أولًا — الفشل مبكرًا وبرسالة مفهومة
        if (! $this->attendance->isCompleteFor($session)) {
            throw BusinessRuleViolation::make(
                'session.attendance_incomplete',
                'sessions::errors.attendance_incomplete',
            );
        }

        $target = SessionStatus::Completed;

        if (! $session->status->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'session.invalid_transition',
                'sessions::errors.invalid_transition',
                ['from' => $session->status->value, 'to' => $target->value],
            );
        }

        // ② التغيير داخل معاملة
        return DB::transaction(function () use ($session, $target, $actorId) {
            $session->transitionTo($target, $actorId);
            $this->sessions->save($session);

            // ③ الأحداث بعد نجاح المعاملة
            $session->publishRecordedEvents();

            return $session;
        });
    }
}
```

**الترتيب ثابت:** حراس → معاملة → أحداث. لا تُنشر أحداث عن تغيير قد يُلغى.

---

## 4. المتحكم رفيع

```php
final class FinalizeSessionController
{
    public function __invoke(
        FinalizeSessionRequest $request,
        string $sessionId,
        FinalizeSession $action,
    ): SessionResource {
        return new SessionResource(
            $action->execute($sessionId, $request->user()->id)
        );
    }
}
```

**ممنوع في المتحكم:** منطق عمل · استعلام Eloquent · تحقق يدوي ·
استدعاء أكثر من إجراء.

**التحقق في FormRequest دائمًا:**

```php
final class FinalizeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('session.finalize', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

---

## 5. النماذج

النموذج يحمل: العلاقات · التحويلات · النطاقات · انتقالات الحالة.
**لا يحمل:** استدعاء خدمات · إرسال إشعارات · حسابات مالية.

```php
final class Session extends Model
{
    use HasUlid, SoftDeletes, RecordsDomainEvents;

    protected $fillable = ['course_id', 'staff_profile_id', 'scheduled_start'];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_start' => 'immutable_datetime',
            'title' => 'array',
        ];
    }

    public function transitionTo(SessionStatus $target, string $actorId): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw BusinessRuleViolation::make('session.invalid_transition', ...);
        }

        $from = $this->status;
        $this->status = $target;

        $this->recordEvent(new SessionStatusChanged($this->id, $from, $target, $actorId));
    }

    public function timeRange(): TimeRange
    {
        return new TimeRange($this->scheduled_start, $this->scheduled_end);
    }
}
```

- `$fillable` صريح — **لا `$guarded = []`**.
- `casts()` كدالة لا كخاصية (نمط Laravel 11+).
- التواريخ `immutable_datetime` — التعديل العرضي مستحيل.

---

## 6. الممنوعات

| ممنوع | البديل |
|-------|--------|
| `if ($user->role === 'admin')` | `$user->can('permission.name')` |
| `if ($absences >= 3)` | `config('discipline.ladder')` |
| `$session->status = 'completed'` | `$session->transitionTo(SessionStatus::Completed, $actor)` |
| `'الحصة أُلغيت'` مباشرة | `__('sessions::messages.cancelled')` |
| `$amount = $rate * 1.15` | `Money::of($rate)->multipliedBy(1.15)` |
| `float` في حساب مالي | `int` بالوحدة الصغرى |
| `now()` داخل المجال | `CarbonImmutable` مُمرَّرة أو `Date::now()` |
| `Model::all()` | استعلام بنطاق وترقيم |
| `$model->relation` في حلقة | `with()` مسبقًا |
| `DB::raw($userInput)` | معاملات مربوطة |
| `dd()` · `dump()` | `logger()` |
| `use Modules\A\Domain\Models\X` في B | عقد أو حدث |

---

## 7. الأخطاء

```php
// خرق قاعدة عمل → 422 · رسالة مترجَمة · لا يُرسل لـ Sentry
throw BusinessRuleViolation::make(
    'postponement.notice_not_met',
    'scheduling::errors.postponement_notice',
    ['required' => 15, 'actual' => 8],
);

// خطأ تقني → 500 · يُرسل لـ Sentry
throw new ClassroomProviderException('BBB returned malformed XML');
```

`code` ثابت لا يُترجم (الواجهة تتفرع عليه) · `message` مترجَم للعرض.

---

## 8. الواجهة الأمامية

```tsx
// TypeScript صارم — لا any
interface SessionCardProps {
    session: Session;
    onJoin: (id: string) => void;
}

export function SessionCard({ session, onJoin }: SessionCardProps) {
    const { t } = useTranslation();
    // ...
}
```

| القاعدة | التطبيق |
|---------|---------|
| المكوّنات | دوال · PascalCase · ملف واحد لكل مكوّن |
| الحالة | خادمية عبر Inertia props · محلية بـ `useState` فقط |
| النصوص | من ملفات الترجمة — **لا نص مكتوب في JSX** |
| RTL | خصائص منطقية: `ms-4` لا `ml-4` |
| التواريخ | تُنسَّق بتوقيت المستخدم في العرض فقط |
| المسارات | `route()` من Ziggy — لا مسار نصي |
| الوصولية | `aria-label` لكل زر بأيقونة · تركيز مرئي |
| `any` | ممنوع · `unknown` ثم تضييق |

**كل مكوّن يعرض بيانات يغطي أربع حالات:** تحميل · فارغ · خطأ · نجاح.

---

## 9. الهجرات

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('status', 32)->index();
            $table->timestampTz('scheduled_start');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('organization_id')
                  ->references('id')->on('organizations')
                  ->restrictOnDelete();      // صريح دائمًا

            $table->index(['organization_id', 'status', 'scheduled_start']);
        });

        // ما لا يدعمه Blueprint يُكتب صراحةً
        DB::statement("ALTER TABLE sessions ADD CONSTRAINT sessions_no_teacher_overlap
            EXCLUDE USING gist (staff_profile_id WITH =, time_range WITH &&)
            WHERE (deleted_at IS NULL)");
    }

    public function down(): void { Schema::dropIfExists('sessions'); }
};
```

- هجرة كل موديول في مجلده.
- `down()` تعمل فعلًا — تُختبر في CI.
- **ممنوع** تعديل هجرة نُفِّذت على الإنتاج.

---

## 10. الترجمة

```
modules/<Name>/resources/lang/{ar,en}/
    messages.php   errors.php   status.php   validation.php
```

```php
// الاستخدام
__('sessions::status.completed')
__('sessions::errors.attendance_incomplete', ['count' => 3])
```

**المفتاح يصف المعنى لا النص:** `errors.notice_not_met`
لا `errors.cannot_cancel_less_than_60_minutes`.

**مفتاح موجود في `ar` وناقص في `en` يُسقط الاختبار.**

---

## 11. التعليقات

اكتب التعليق لتشرح **لماذا**، لا **ماذا**:

```php
// سيئ
// نزيد العداد بواحد
$count++;

// جيد
// نحسب العدّاد من السجلات في كل مرة ولا نخزّنه رقمًا،
// حتى لا يوجد مسار يعدّل عدد مخالفات الطالب مباشرة.
$count = $this->violations->countInWindow($enrollmentId, $window);
```

**قواعد العمل تُوثَّق دائمًا** مع الإشارة لمصدر القرار:

```php
// قرار العميل: الطالب المتغيّب لا يُعفي المعلم من أجره — المعلم حضر.
// المرجع: docs/14-payroll-rules.md · config/payroll.php outcomes
```

---

## 12. Git

```
feat(sessions): إضافة إقفال آلي للحصة بعد 30 دقيقة
fix(payroll): منع ازدواج القيدة عند إعادة تشغيل المهمة
docs(scheduling): توضيح مهلة التأجيل
test(discipline): تغطية سُلَّم المخالفات الثلاث
```

**النطاق = اسم الموديول بحروف صغيرة.**

- فرع لكل مهمة: `feat/sessions-auto-finalize`
- الفرع يعالج موديولًا واحدًا كلما أمكن
- `composer check` يمر قبل الدفع
- الرسالة بالعربية أو الإنجليزية — لكن بثبات داخل الفرع الواحد

---

## 13. قبل فتح طلب الدمج

- [ ] `composer check` يمر (Pint + PHPStan + Pest)
- [ ] `vendor/bin/pest --testsuite=Architecture` يمر
- [ ] لا رقم سياسة في الكود
- [ ] لا نص ظاهر بلا ترجمة
- [ ] لا فحص على اسم دور
- [ ] الهجرة قابلة للتراجع
- [ ] الوثيقة المعنية محدَّثة
- [ ] [`21-definition-of-done.md`](21-definition-of-done.md) مُستوفاة
