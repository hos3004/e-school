# 16 — استراتيجية الاختبار

**Pest 3** لكل اختبارات PHP · **Playwright** للرحلات الحرجة في المتصفح.

القاعدة الحاكمة:

> **الاختبار الذي لا يفشل عند حذف القاعدة التي يحرسها ليس اختبارًا.**

---

## 1. الهرم

```
        ▲   Playwright  ~10 رحلات      بطيئة · غالية · لا غنى عنها
       ╱ ╲
      ╱   ╲  Feature   ~40%            مسار HTTP كامل بقاعدة بيانات
     ╱     ╲
    ╱───────╲ Unit     ~55%            قواعد العمل وآلات الحالات
   ╱_________╲ Arch    دائمة           حدود الموديولات
```

| الطبقة | المجموعة | تعمل في CI | الهدف |
|--------|----------|:----------:|-------|
| معمارية | `Architecture` | ✅ أولًا | الحدود لم تُكسر |
| وحدة | `Unit` | ✅ | قاعدة عمل واحدة معزولة |
| وظيفية | `Feature` | ✅ | مسار كامل + صلاحيات |
| متصفح | `e2e` | ✅ ليلي | الرحلات الحرجة |
| خارجية | `@group external` | ❌ يدوي | تكامل حقيقي مع مزوّد |

**التغطية:** لا تقل عن **70%** لكل موديول. المال والانضباط والجدولة
هدفها **90%** — لا لأن الرقم مقدّس، بل لأن الخطأ فيها مكلف.

---

## 2. اختبارات المعمارية — تُكتب أولًا

تُبنى في الموجة 0 **قبل أي موديول**. بناؤها لاحقًا يعني اكتشاف عشرات
الخروقات دفعةً واحدة، وعندها يُسهَّل الأمر بتعطيلها.

```php
// tests/Architecture/ModuleBoundariesTest.php
arch('لا موديول يستورد نموذجًا من موديول آخر')
    ->expect('Modules')
    ->not->toUse(fn (string $c) => str_contains($c, 'Domain\Models'))
    ->ignoring(fn (string $from, string $to) => sameModule($from, $to));

arch('الموديولات المختومة لا تُستورد من الخارج')
    ->expect(['Modules\Payroll', 'Modules\Billing',
              'Modules\Audit', 'Modules\Identity'])
    ->toOnlyBeUsedIn(fn (string $c) => sameModule($c));

arch('العقود لا تُرجع نماذج Eloquent')
    ->expect('Modules')
    ->classes()
    ->matching('*\Domain\Contracts\*')
    ->not->toReturn(Model::class);

arch('كل ملف يعلن strict_types')
    ->expect('Modules')->toUseStrictTypes();

arch('لا debug helpers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])->not->toBeUsed();

arch('اتجاه الاعتماد بين الطبقات محفوظ')
    ->expect('Modules')
    ->not->toUse(fn ($from, $to) => layerOf($to) > layerOf($from));
```

### فحوص إضافية بلا Pest Arch

| الفحص | الطريقة |
|-------|---------|
| هجرة تلمس جدولًا لا يملكه الموديول | تحليل نصي لملفات الهجرة مقابل جدول الملكية في `08` |
| نص ظاهر بلا ترجمة | بحث عن سلاسل عربية أو إنجليزية طويلة في Blade/TSX |
| رقم سياسة في الكود | بحث عن أرقام مقارنة في `Domain` و `Application` |
| فحص على اسم دور | بحث عن `hasRole` و `role ===` |

---

## 3. اختبارات الوحدة

**تختبر قاعدة عمل واحدة بلا قاعدة بيانات وبلا شبكة.**

### آلات الحالات — إلزامية لكل واحدة

```php
it('يمنع الانتقال من Completed إلى أي حالة', function () {
    expect(SessionStatus::Completed->allowedTransitions())->toBeEmpty();
});

it('يمنع إقفال حصة بلا حضور معتمد لكل مشارك', function () {
    $session = SessionBuilder::make()
        ->withParticipants(3)
        ->withConfirmedAttendance(2)   // ناقص واحد
        ->build();

    expect(fn () => $session->finalize())
        ->toThrow(BusinessRuleViolation::class, 'session.attendance_incomplete');
});

it('يمنع Frozen إلى Active مباشرة', function () {
    expect(EnrollmentStatus::Frozen->canTransitionTo(EnrollmentStatus::Active))
        ->toBeFalse();
});
```

**المصفوفة الكاملة:** لكل حالة، كل انتقال مسموح ينجح وكل ممنوع يرمي.
تُولَّد بـ `dataset` لا بكتابة يدوية.

### الحسابات المالية

الأمثلة الثلاثة في [`14-payroll-rules.md`](14-payroll-rules.md) تُنفَّذ حرفيًا:

```php
it('مثال القرآن الجماعي يعطي 500 جنيه', function () {
    $period = PayrollScenario::quranMonthly()
        ->sessionsHeld(8)
        ->studentNoShow(1)
        ->acceptedCancellation(1)
        ->teacherAbsent(1)
        ->postponed(1)
        ->calculate();

    expect($period->netFor($teacher))->toEqual(Money::of(50000, 'EGP'));
});
```

### التوقيت

```php
it('جدول أسبوعي يعبر التوقيت الصيفي يحافظ على الساعة المحلية', function () {
    $sessions = ScheduleGenerator::weekly('Africa/Cairo', '18:00')
        ->between('2026-04-20', '2026-05-10')
        ->generate();

    expect($sessions)->each->toStartAtLocalTime('18:00');
});

it('حصة تنتهي 18:00 وأخرى تبدأ 18:00 لا تتعارضان', function () {
    expect($a->overlaps($b))->toBeFalse();
});
```

---

## 4. اختبارات وظيفية

**كل مسار API يحتاج ثلاثة اختبارات على الأقل:**

```php
it('المعلم يرصد حضور حصته', function () {
    actingAs($teacher)
        ->postJson("/api/v1/sessions/{$session->id}/attendance/confirm", [...])
        ->assertOk();
});

it('معلم آخر لا يرصد حضور حصة ليست له', function () {
    actingAs($otherTeacher)
        ->postJson("/api/v1/sessions/{$session->id}/attendance/confirm", [...])
        ->assertForbidden();
});

it('يرفض تعديل الحضور بلا سبب مكتوب', function () {
    actingAs($teacher)
        ->patchJson("/api/v1/sessions/{$session->id}/attendance", [
            'records' => [['student_id' => $s->id, 'status' => 'present']],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'attendance.override_reason_required');
});
```

### مصفوفة الصلاحيات

كل مسار يُختبر ضد **كل الأدوار التسعة** عبر `dataset`، ويُقارَن الناتج
بمصفوفة [`06-permissions-matrix.md`](06-permissions-matrix.md). دور غير مذكور
في المصفوفة يجب أن يحصل على 403.

### العزل بين المؤسسات

```php
it('مستخدم لا يرى بيانات مؤسسة أخرى ولو بمعرّف صريح', function () {
    actingAs($userOfOrgA)
        ->getJson("/api/v1/students/{$studentOfOrgB->id}")
        ->assertNotFound();   // 404 لا 403 — لا نكشف الوجود
});
```

---

## 5. اختبارات الأحداث

```php
it('إقفال الحصة ينشر SessionFinalized بالحمولة الصحيحة', function () {
    Event::fake([SessionFinalized::class]);
    $session->finalize();

    Event::assertDispatched(SessionFinalized::class, fn ($e) =>
        $e->sessionId === $session->id
        && $e->outcome === 'completed'
        && $e->occurredOn === $session->scheduled_start->toDateString()
    );
});

it('المستمع idempotent — استدعاؤه مرتين لا يضاعف القيدة', function () {
    $listener->handle($event);
    $listener->handle($event);

    expect(PayrollEntry::where('session_id', $id)->count())->toBe(1);
});
```

**كل مستمع يحتاج اختبار idempotency.** الطوابير تعيد المحاولة،
والحدث قد يصل مرتين — هذه ليست حالة نادرة.

---

## 6. اختبارات المزوّدين

```php
it('يرفض ويب هوك بتوقيع فاسد', function () {
    postJson('/webhooks/bigbluebutton', $payload, ['X-Signature' => 'bad'])
        ->assertUnauthorized();
});

it('يعيد المحاولة للخطأ العابر فقط', function () {
    expect($gateway->send($msg)->isRetryable)->toBeTrue();   // 503
    expect($gateway->send($bad)->isRetryable)->toBeFalse();  // رقم غير صالح
});
```

**كل الاختبارات تستخدم تنفيذ `Null` أو `Fake`.**
`CLASSROOM_PROVIDER=null` في `phpunit.xml` — لا اتصال شبكي في CI أبدًا.

الحمولات الحقيقية المسجَّلة في `tests/Fixtures/<provider>/` هي أساس
اختبارات العقد.

---

## 7. رحلات المتصفح

عشر رحلات فقط — الأغلى والأهم:

1. الطالب يدخل ويرى جدوله ويفتح الفصل.
2. المعلم يعطي حصة ويرصد الحضور ويكتب التقرير.
3. الطالب يطلب تأجيلًا · المعلم يوافق · تُنشأ حصة التلافي.
4. ثلاث غيابات تُنتج تجميدًا آليًا.
5. الطالب يطلب فك التجميد ويجتاز التقييم ويعود نشطًا.
6. ولي الأمر يبدّل بين ابنين ويرى تقرير كل واحد.
7. مسؤول التسجيل يستورد مجموعة من Excel بمواعيدها وأعضائها.
8. المشرف يقترح تسوية والمالي يعتمدها.
9. إقفال فترة مستحقات من الاحتساب حتى القفل.
10. تبديل اللغة عربي/إنجليزي مع التحقق من RTL.

**كل رحلة تُشغَّل بالعربية وبالإنجليزية.**

---

## 8. البيانات التجريبية

### Factories

كل موديول يوفّر Factory لكل نموذج، وحالات جاهزة:

```php
StudentFactory::new()->minor()->withGuardian()->create();
SessionFactory::new()->completed()->withAttendance()->create();
EnrollmentFactory::new()->frozen()->create();
```

### Builders للسيناريوهات

```php
SchoolScenario::make()
    ->withProgram('quran')
    ->withGroup(students: 5)
    ->withTeacher(rate: 5000)
    ->withMonthOfSessions()
    ->build();
```

يُبنى مرة ويُعاد استخدامه — لا تكرار إعداد في كل ملف.

### قاعدة البيانات

- `RefreshDatabase` مع معاملات — لا إعادة بناء لكل اختبار.
- PostgreSQL حقيقي في CI · **لا SQLite** — نستخدم `EXCLUDE` و `tstzrange`
  و `JSONB` ولا مكافئ لها في SQLite.
- التاريخ مثبَّت بـ `Carbon::setTestNow()` — لا اختبار يعتمد على الوقت الحقيقي.

---

## 9. ما لا نختبره

| لا نختبر | لماذا |
|----------|-------|
| إطار Laravel نفسه | مُختبَر عند مطوّريه |
| الحصول والضبط البسيط | لا قاعدة عمل فيه |
| صحة تنسيق Blade/TSX | مهمة اللينتر لا الاختبار |
| المزوّد الخارجي الحقيقي في CI | هش وبطيء — مجموعة `external` يدوية |

---

## 10. أوامر التشغيل

```bash
composer test                              # كل شيء
vendor/bin/pest --testsuite=Architecture   # الحدود أولًا
vendor/bin/pest --testsuite=Unit
vendor/bin/pest --filter=Payroll
vendor/bin/pest --coverage --min=70
npm run test:e2e
```

---

## 11. بوابة CI

بالترتيب — وأي فشل يوقف الباقي:

```
1. Pint          التنسيق
2. PHPStan       المستوى 6
3. Architecture  الحدود
4. Unit          قواعد العمل
5. Feature       المسارات والصلاحيات
6. Coverage      ≥ 70%
7. TypeScript    فحص الأنواع
8. Build         بناء الواجهة
```

**اختبارات المعمارية قبل الوحدة عمدًا:** كسر الحدود خطأ تصميمي،
واكتشافه في الثانية الثالثة أرخص من اكتشافه بعد عشر دقائق.
