# مهام Codex المتبقية — e-school

> **LEGACY / لا تنفّذ كطابور حالي.** طابور المرحلة الأولى المعتمد في
> `docs/agent-tasks/QUEUE-antigravity.md` ونطاقها في `docs/phase-1-approved-scope.md`.

> شغّل هذا الملف كمهمة واحدة في Codex من داخل `I:/e-school`.
> الحالة عند كتابة الملف: 1038 ملف تحت `modules/*/src` · 70 جدولًا · 49 مورد Filament مسجَّل ·
> اللوحة تفتح وتسجيل الدخول يعمل · بيانات تجريبية موجودة (6 معلمين · 40 حصة · 48 حضور · 28 قيدة).

---

## قواعد تحكم كل المهام أدناه

1. **اكتب كل ملف على القرص لحظة الانتهاء منه.** لا تستكشف طويلًا ثم تكتب في النهاية —
   عدة وكلاء فقدوا عملهم كله بهذه الطريقة.
2. **اقرأ من `vendor/` لا من ذاكرتك.** المثبَّت هنا Laravel 13 و Filament 5 و Pest 5،
   وأخطاء كثيرة سببها كتابة واجهة Filament 3 من الذاكرة.
3. **بعد كل مهمة شغّل فحص الإقلاع:**
   `docker compose exec -T app php artisan route:list --json | head -c 80`
   إن ظهر خطأ فأصلحه قبل الانتقال للمهمة التالية — خطأ الإقلاع يعطّل كل شيء.
4. **لا تلمس** `composer.json` ولا `vendor/` ولا `node_modules/` ولا الهجرات المُنفَّذة.
5. **لا تنفّذ `git commit`** إطلاقًا.

### أخطاء متكررة — تجنّبها وأصلحها أينما وجدتها
| الخطأ | الصواب |
|-------|--------|
| `form(Form $form): Form` | `form(Schema $schema): Schema` مع `use Filament\Schemas\Schema;` |
| `protected static ?string $navigationIcon` | `protected static string\|\BackedEnum\|null $navigationIcon` |
| `protected static ?string $navigationGroup` | `protected static \UnitEnum\|string\|null $navigationGroup` |
| `App\Models\User` | `Modules\Identity\Domain\Models\User` |
| `faker->ulid()` | `Str::ulid()` — Faker لا يملك ulid |
| `HasFactory` مع `HasModuleFactory` | `HasModuleFactory` وحده (تصادم تريتات) |
| ULID عشوائي في مفتاح أجنبي | `Shared\Testing\Fixtures::organizationId()` في المصانع |
| `Pages\X::class` داخل مورد | `<Resource>Resource\Pages\X::class` مؤهَّلًا بالكامل |
| `->relationship('x','id')` بلا علاقة | احذف المُصفّي أو أضف العلاقة أولًا |

---

## المهمة 1 — الترجمات الناقصة (أعلى أولوية، أثرها فوري ومرئي)

القائمة الجانبية في اللوحة تعرض **مفاتيح الترجمة الخام** بدل النصوص، مثل:

```
assignments::filament.navigation_group
staff::filament.profile.plural_label
```

**المطلوب:**

1. استخرج كل مفاتيح `__('...')` المستخدمة تحت `modules/*/src/Presentation/`:
   ```bash
   grep -rhoE "__\('[a-z_]+::[a-zA-Z0-9_.]+'" modules/*/src/Presentation --include=*.php | sort -u
   ```
2. لكل مفتاح بصيغة `<module>::<file>.<path>` تأكد من وجوده في
   `modules/<Module>/resources/lang/ar/<file>.php` **و** `en/<file>.php`.
3. أنشئ الناقص بنص عربي حقيقي مفهوم للمستخدم النهائي — **لا نسخ حرفي للمفتاح**،
   ولا ترجمة آلية ركيكة. أمثلة على المستوى المطلوب:
   - `navigation_group` للموديول Assignments → `الواجبات`
   - `profile.plural_label` للموديول Staff → `ملفات الموظفين`
   - `entry.amount` للموديول Payroll → `المبلغ`
4. النص الإنجليزي مقابل دقيق، لا ترجمة حرفية.

**معيار الإتمام:** الأمر في الخطوة 1 لا يُخرج أي مفتاح غير موجود في ملفات اللغة.

---

## المهمة 2 — توحيد أسماء الصلاحيات

الموارد تفحص صلاحيات بنمطين مختلفين:
- النمط المعتمد في `docs/06-permissions-matrix.md`: `student.view` · `payroll.approve`
- نمط اخترعه الوكلاء: `academics.programs.view_any` · `accesscontrol.roles.view`

النتيجة: موارد كثيرة مخفية من اللوحة لأن `canAccess()` ترجع `false`.

**المطلوب:**

1. اجمع كل الصلاحيات التي تفحصها الموارد:
   ```bash
   grep -rhoE "can\('[a-z_.]+'\)" modules/*/src/Presentation --include=*.php | sed "s/can('//; s/')//" | sort -u
   ```
2. قارنها بالموجود فعلًا:
   ```bash
   docker compose exec -T postgres psql -U eschool -d eschool -tAc "select name from permissions order by 1"
   ```
3. **لكل صلاحية غير موجودة**: عدّل المورد ليستخدم الاسم المعتمد من
   `docs/06-permissions-matrix.md` بدلًا منها. الخريطة المتوقعة:
   - `academics.programs.*` و `academics.courses.*` و `academics.levels.*` → `program.manage` أو `course.manage`
   - `accesscontrol.roles.*` و `accesscontrol.permissions.*` → `settings.manage`
   - أي `<module>.<resource>.view_any` → أقرب صلاحية `<resource>.view` في المصفوفة
4. **لا تضف صلاحيات جديدة للقاعدة** — وحّد الأسماء على المصفوفة.
5. بعدها احذف ثابت `RESOURCE_PERMISSIONS` من
   `modules/AccessControl/database/Seeders/AccessControlSeeder.php` (كان حلًا مؤقتًا)،
   وأعد التشغيل:
   ```bash
   docker compose exec -T app php artisan db:seed --class="Modules\AccessControl\Database\Seeders\AccessControlSeeder" --force
   ```

**معيار الإتمام:** كل صلاحية تفحصها الموارد موجودة في القاعدة، والمقارنة في الخطوة 2 لا تُظهر فروقًا.

---

## المهمة 3 — توصيل واجهة الطالب والمعلم وولي الأمر

`resources/js/` فيه 28 ملفًا كاملًا (Layouts · Components · Pages لثلاثة أدوار)،
لكن **لا يوجد مسار واحد يوصل إليها**. `/student` و `/teacher` و `/guardian` غير موجودة.

**اقرأ أولًا:** `resources/js/types/index.d.ts` — هو عقد الـprops الذي تتوقعه الصفحات.
طابقه حرفيًا.

**المطلوب:**

### 3.1 `app/Http/Middleware/HandleInertiaRequests.php`
يشارك عبر Inertia:
- `auth.user` — `id` و `name` و `email` و `locale` وأسماء أدواره
- `flash` — `success` و `error` من الجلسة
- `locale` و `direction` (`rtl` للعربية، وإلا `ltr`)
- `translations` — خريطة مسطّحة `key => value` للغة النشطة، يستهلكها `t()` في الصفحات

سجّله في `bootstrap/app.php` ضمن مجموعة `web` بعد `SetLocale`.

### 3.2 `resources/views/app.blade.php`
`<html lang="{{ app()->getLocale() }}" dir="...">` مع `@vite(['resources/css/app.css', 'resources/js/app.tsx'])`
و `@inertiaHead` و `@inertia`.

### 3.3 `routes/web.php`
| المسار | الصفحة | الصلاحية |
|--------|--------|----------|
| `/` | إعادة توجيه حسب الدور | — |
| `/student` | `Student/Dashboard` | `session.view` |
| `/student/schedule` | `Student/Schedule` | `schedule.view` |
| `/student/sessions/{id}` | `Student/Sessions/Show` | `session.view` |
| `/student/assignments` | `Student/Assignments/Index` | `assignment.submit` |
| `/student/reports` | `Student/Reports` | `session_report.view` |
| `/teacher` | `Teacher/Dashboard` | `session.view` |
| `/teacher/schedule` | `Teacher/Schedule` | `schedule.view` |
| `/teacher/sessions/{id}` | `Teacher/Sessions/Show` | `attendance.record` |
| `/teacher/postponements` | `Teacher/Postponements` | `session.postpone.approve` |
| `/guardian` | `Guardian/Dashboard` | `student.view` |
| `/guardian/children/{studentId}/attendance` | `Guardian/Child/Attendance` | `attendance.view` |
| `/guardian/children/{studentId}/reports` | `Guardian/Child/Reports` | `session_report.view` |

كلها خلف `auth` و `can:` — **وممنوع الفحص على اسم الدور في الكود**.

### 3.4 متحكمات في `app/Http/Controllers/Portal/`
متحكم واحد لكل صفحة بدالة `__invoke` واحدة يُرجع `Inertia::render(...)`.

- **اقرأ البيانات بـ `DB::table(...)`** لا باستيراد نماذج موديولات أخرى —
  الاستيراد عبر الحدود ممنوع ومفروض باختبارات معمارية.
  الجداول: `sessions` · `session_participants` · `attendances` · `assignments` ·
  `assignment_submissions` · `monthly_reports` · `postponement_requests` ·
  `student_profiles` · `guardian_links` · `groups` · `courses`
- **نافذة الدخول:** الصفحات تتوقع `canJoinAt` لكل حصة. احسبها في الخادم =
  بداية الحصة ناقص `config('virtual-classroom.join_window.before_minutes')`.
  **لا تضع هذه السياسة في الواجهة.**
- **نطاق ولي الأمر:** لا يرى إلا الطلاب المرتبطين به عبر صف نشط في `guardian_links`.
  عند طلب طالب ليس ابنه أرجع **404 لا 403** — لا نكشف الوجود.
- كل متحكم يُرجع مصفوفات فارغة سليمة عند غياب البيانات، حتى تعرض الصفحة حالتها الفارغة
  بدل أن تنهار.

**معيار الإتمام:** `php artisan route:list` يُظهر المسارات الثلاثة عشر،
وفتح `/student` بحساب طالب يعرض الصفحة بلا خطأ.

---

## المهمة 4 — محرّك الإشعارات

الجداول والنماذج موجودة في `modules/Notifications`، والمحرّك غير مكتوب.
**كل القواعد التي تحتاجها في `config/notifications.php`** — اقرأه ولا تخترع أرقامًا.

### 4.1 `src/Domain/Contracts/NotificationDispatcher.php`
```php
dispatch(string $category, array $recipientIds, array $payload, ?string $correlationId = null): int
```
يُرجع عدد السطور المكتوبة في الصندوق الصادر.

### 4.2 `src/Application/Services/OutboxDispatcher.php`
بالترتيب:
1. اقرأ إعداد الفئة من `config('notifications.categories.'.$category)`؛ الفئة المجهولة ترمي
   `Shared\Support\BusinessRuleViolation`
2. القنوات = تقاطع قنوات الفئة مع المفعّلة عالميًا مع تفضيلات المستلم،
   **لكن** الفئة `critical` تتجاهل تفضيلات المستلم، و `in_app` تُضاف دائمًا
3. اللغة: لغة المستلم ثم `config('notifications.localization.fallback_locale')`
4. الوقت: الآن، إلا إذا كانت الفئة غير حرجة والوقت داخل ساعات الهدوء
   **بتوقيت المستلم** — عندها تُجدول لبداية اليوم التالي
5. `idempotency_key = sha256($eventId.$userId.$channel.$category)` — التكرار خلال
   `idempotency_window_minutes` يُكتب بحالة `suppressed` ولا يُرسل
6. اكتب السطر في `notification_outbox` بحالة `queued`

**اكتب في الصندوق فقط — لا ترسل من هنا إطلاقًا.**

### 4.3 `src/Application/Jobs/SendQueuedNotification.php`
مهمة `ShouldQueue`: تتخطى ما ليس `queued` → `sending` → تستدعي بوابة القناة من
`modules/Integrations/src/Domain/Contracts/` → تكتب محاولة في
`notification_delivery_attempts` → `sent` أو إعادة جدولة بـ
`config('notifications.delivery.backoff_seconds')` أو `failed`.

### 4.4 أمران
`notifications:dispatch-due` و `notifications:retry-failed`
(الثاني مُشار إليه في `routes/console.php` بالفعل ويجب أن يعمل).

### 4.5 الربط
`bindings()` و تسجيل الأمرين في `NotificationsServiceProvider`.

**ممنوع:** استيراد نموذج من موديول آخر — تعامل بالمعرّفات، واقرأ لغة المستلم ومنطقته
الزمنية عبر `DB::table('users')`.

---

## المهمة 5 — مزوّد BigBlueButton

العقد مكتوب بالكامل في
`modules/VirtualClassroom/src/Domain/Contracts/VirtualClassroomProvider.php`
والتنفيذ غير موجود. اقرأ العقد أولًا ونفّذ كل دواله.

**المطلوب:** `src/Infrastructure/Providers/BigBlueButtonProvider.php` وكائنات القيمة
المذكورة في العقد تحت `src/Domain/ValueObjects/`.

**تفاصيل بروتوكول BBB:**
- كل نداء = `GET {BBB_BASE_URL}{action}?{params}&checksum={sha1(action + params + secret)}`
- الاستجابة XML؛ حوّلها لكائنات القيمة ولا تُسرّب XML خارج هذا الصنف
- `create` · `join` · `isMeetingRunning` · `getMeetingInfo` · `end` ·
  `getRecordings` · `deleteRecordings`
- الإعدادات من `config('virtual-classroom.providers.bigbluebutton')`

**قواعد إلزامية:**
- `NullProvider` أولًا (يعمل بلا شبكة، للاختبارات والتطوير) ثم BBB
- مهلة زمنية صريحة + إعادة محاولة للأخطاء العابرة فقط + قاطع دائرة
- كل خطأ من المزوّد يُترجم لاستثناء مجال — لا تسريب استثناءات Guzzle
- `parseWebhook` تتحقق من التوقيع **قبل أي معالجة** وترجع `null` للأحداث المجهولة
- **لا سر في الكود** — كله من `.env`

---

## المهمة 6 — تنظيف نهائي وتشغيل الفحوص

بعد إتمام ما سبق فقط:

```bash
docker compose exec -T app vendor/bin/pint
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T app php artisan test
```

- `pint` يصلح التنسيق آليًا — شغّله ثم راجع الناتج
- أخطاء `phpstan` أصلحها بأنواع صحيحة لا بـ `@phpstan-ignore`
- الاختبارات: آخر قياس كان **249 ناجحًا من 449**. ارفع الرقم بإصلاح
  **الأنماط المتكررة** لا اختبارًا اختبارًا — كل نمط يُصلح عشرات الاختبارات دفعة واحدة.

---

## الترتيب الموصى به

```
1 الترجمات        ← أثر مرئي فوري، ونطاقه محدود
2 الصلاحيات       ← يُظهر 47 موردًا مخفيًا
3 واجهة الأدوار   ← أكبر فجوة وظيفية
4 الإشعارات       ← يفعّل التذكيرات والتنبيهات
5 BigBlueButton   ← يفعّل الفصل المباشر
6 الفحوص          ← بعد استقرار كل ما سبق
```

بعد كل مهمة: فحص الإقلاع، ثم انتقل. لا تبدأ مهمة والإقلاع مكسور.
