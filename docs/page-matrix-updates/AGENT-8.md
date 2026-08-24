# AGENT-8 — سجل التسليم: لوحة الإدارة B1 + عقد التنقل + QA

التاريخ: 2026-08-23 · النطاق: B1 (لوحة الإدارة) + Navigation Contract (8.0) + الترجمة

---

## 1. ما نُفِّذ

### 8.0 — عقد التنقل (`resources/js/Layouts/AppLayout.tsx`)
- توحيد عنصر التنقل في الشكل `{ href, labelKey, icon }` عبر مصفوفة معلنة واحدة
  لكل منطقة: `student` / `teacher` / `guardian`.
- إضافة مكوّن أيقونات SVG مضمّن (`NavigationIcon`) بأسماء معلنة:
  `home | schedule | assignments | reports | postponements` — بلا نصوص مباشرة.
- الروابط الحالية فقط (كلها موجودة في route:list):
  - student: `/student` · `/student/schedule` · `/student/assignments` · `/student/reports`
  - teacher: `/teacher` · `/teacher/schedule` · `/teacher/postponements`
  - guardian: `/guardian`
- active state يعمل مع المسارات الفرعية (`startsWith(href + '/')`) والصفحة
  الرئيسية لكل منطقة تُبرَز بالتطابق التام فقط.
- الوكلاء 4–7 يضيفون My Profile/Notifications بنفس الشكل `{ href, labelKey, icon }`.

### 8.1 — لوحة الإدارة B1

**ملف ترجمة جديد:** `resources/lang/{ar,en}/dashboard.php` هو المصدر الفعلي، لأن
`path.lang` في هذا المستودع يشير إلى `resources/lang` (تم التحقق:
`$app['path.lang'] = /var/www/html/resources/lang`). وملفا
`lang/{ar,en}/dashboard.php` الممنوحان في العقد موجودان كملفين رفيعين يعيدان
`require resource_path(...)` — مصدر واحد للحقيقة بلا ازدواج محتوى.

**PlatformOverview** — كل النصوص عبر `__('dashboard.stats.*')` مع placeholders،
وكل البطاقات قابلة للنقر (`Stat::url()`):
| البطاقة | الوجهة |
|---|---|
| الطلاب | `/admin/students` (كانت تشير إلى `/admin/student-profiles` — مسار غير موجود، أُصلح) |
| المعلمون والطاقم | `/admin/staff-profiles` |
| البرامج الدراسية | `/admin/program-filaments` (كانت `/admin/programs` — غير موجودة، أُصلحت) |
| حصص اليوم | `/admin/sessions` |
| نسبة الحضور هذا الشهر | `/admin/attendance-filaments` (كانت بلا رابط) |
| مستحقات الشهر (خلف feature flag) | `/admin/payroll-entries` |

**NeedsAttention** — كل بند صار رابطًا فعليًا (href داخل البيانات، والبطاقة كلها
`<a>` في الـ blade)، وأُضيف البندان المطلوبان بالمصفوفة:
| البند | الاستعلام | الوجهة |
|---|---|---|
| طلبات تأجيل تنتظر ردًا | `postponement_requests` (requested/alternative_proposed) | `/admin/postponement-requests` |
| طلبات تأجيل انقضت مهلتها | نفسها + `expires_at < now` | `/admin/postponement-requests` |
| حصص تنتظر اعتماد الحضور | `sessions.status = awaiting_review` | `/admin/sessions` |
| **تسجيلات بانتظار المراجعة (جديد)** | `registration_applications.status = submitted` (لا توجد حالة `awaiting_review` في هذا الجدول — الأقرب نحويًا هي Submitted) | `/admin/registration-applications` |
| قيود مجمَّدة تأديبيًا | `enrollments.status = frozen` | `/admin/students` (فلتر لاحقًا كما هو متفق) |
| طلبات فك تجميد معلّقة | `reactivation_requests.status = pending` | `/admin/discipline-reactivations` |
| **إتاحة معلمين غير معتمدة (جديد)** | `teacher_availability.approval_status = pending` | `/admin/staff-profiles` |
| تسويات مالية (feature flag) | `payroll_adjustments` بلا اعتماد/رفض | `/admin/payroll-entries` |
| إشعارات فاشلة | `notification_outbox.status = failed` | `/admin/notification-outboxes` |

**SessionsTrend** — العنوان وتسميتا المنحنيين عبر `__('dashboard.sessions_trend.*')`
(بتجاوز `getHeading()` بدل خاصية، حفاظًا على دورة حياة Livewire).

**UpcomingSessions (جديد)** — أقرب 10 حصص `scheduled/confirmed` لم تبدأ؛ أعمدة:
وقت البداية (بـ timezone المجموعة من جدول groups، وسقوط إلى `app.timezone`),
المجموعة (اسم jsonb موضعي ar/en + الكود), المعلم (users عبر staff_profiles),
وعمود عرض يفتح `/admin/sessions/{id}`.

**QuickActions (جديد)** — طالب جديد → `/admin/students/create` · برنامج جديد →
`/admin/program-filaments/create` · مجموعة جديدة → `/admin/groups/create` ·
الحصص → `/admin/sessions` (لا توجد صفحة إنشاء للحصص في route:list، فاستُخدم
الفهرس كما هو متفق في التعليمات).

**AdminPanelProvider** — سطرا تسجيل `QuickActions::class` و`UpcomingSessions::class`
فقط (هذا هو الاستخدام المسموح وحده).

### نقطة 8.1.6 — Recording problems: تجاوزت مع تسجيل Known issue
`RecordingStatus::Failed` **موجود** فعلاً، لكن مورد `RecordingResource` مسجّل في
اللوحة **بدون أي صفحات (Pages)** فلا تولد له أي routes: `php artisan route:list
--path=admin/recordings` لا يعيد شيئًا. الربط بمسار غير موجود يخالف قاعدة القبول
(«كل وجهة موجودة فعليًا في route:list»)، فلم تُضَف بطاقة ميتة.

## 2. مسارات اختبرتها ونتائج HTTP

دخول admin@demo.local عبر `POST /login` (Fortify، حقل `login`) بعد `GET /login`
لأخذ XSRF — نجاح (302 → /). ثم:

| المسار | الحالة | ملاحظة |
|---|---|---|
| `/admin` | **200** | اللوحة تعرض الـ widgets الخمسة (تحقق wire:key لكل منها) |
| `/admin/students` | 500 | خطأ وكيل آخر — انظر Known issues |
| `/admin/staff-profiles` | 500 | خطأ وكيل آخر |
| `/admin/program-filaments` | **200** | |
| `/admin/sessions` | 500 | خطأ وكيل آخر |
| `/admin/attendance-filaments` | **200** | |
| `/admin/postponement-requests` | **200** | |
| `/admin/registration-applications` | **200** | |
| `/admin/discipline-reactivations` | 403 | Policy للمورد تمنع حساب الديمو — خارج نطاقي |
| `/admin/notification-outboxes` | **200** | |
| `/admin/enrollments` | **200** | |
| `/admin/groups` | 500 | خطأ وكيل آخر |
| `/admin/groups/create` | **200** | |
| `/admin/students/create` | 403 | Policy — خارج نطاقي |
| `/admin/program-filaments/create` | **200** | |

الصفحات ذات 200 = كل وجهات بطاقاتي/أزراري باستثناء الأربع التي يكسرها كود
الوكلاء الآخرين (المسارات نفسها موجودة في route:list).

### تحقق محتوى الـ widgets
لأن Filament v5 lazy-loads الـ widgets عبر `x-intersect` (HTML الأولي يحوي
placeholders فقط)، تحققت من المخرجات بتشغيل الـ widgets نفسها داخل app
محمّلة بالكامل على قاعدة الديمو:
- PlatformOverview: 5 بطاقات بعناوين/أوصاف مترجمة وروابط صحيحة (نموذج:
  «الطلاب | 3 نشط · 1 مجمَّد | url=/admin/students»).
- NeedsAttention: title/subtitle/empty مترجمة؛ البنود الصفرية تُختفي (ظهر فقط
  «قيود مجمَّدة» بقيمة 1 → /admin/students).
- QuickActions: الإجراءات الأربعة بعناوين مترجمة ومساراتها أعلاه.
- UpcomingSessions: 10 صفوف حقيقية (نموذج: «23 أغسطس 2026 19:00 | حلقة القرآن أ ·
  DEMO-G1 | أحمد عبد الرحمن -> /admin/sessions/01M0M6VF2XW7PAYBBN8P9HJG8M»).
- لا وجود لأي مفتاح ترجمة خام `dashboard.*` في HTML الصفحة (grep = 0).

### اختبارات آلية
- `TEST_AGENT_ID=ag8 php scripts/test-isolated.php tests/Feature/PortalRoutesTest.php`
  → **22 passed, 157 assertions** (أُعيد تشغيله أخيرًا: نفس النتيجة).
- `--group=arch` → 82 passed / **2 failed قديمة في modules أخرى** (Reporting
  يستورد أصناف موديولات أخرى + انتهاك اتجاه طبقات) — لا علاقة لها بملفاتي
  (كل تغييراتي تحت `app/`, `lang/`, `resources/`).
- `pint --test` على Widgets+Provider+lang → PASS · `phpstan analyse` على
  Widgets+Provider → No errors · `tsc --noEmit` → صفر أخطاء في ملفاتي (الأخطاء
  الظاهرة كلها في Pages خاصة بوكلاء 4–7 تمرر prop باسم `header` غير معرّف
  في AppLayout أصلًا قبل تغييري).

## 3. أسطر تحديث مقترحة لمصفوفة الصفحات

```
B1 | Admin Dashboard | PARTIAL → FUNCTIONAL
     ما تحقق: بطاقات PlatformOverview الخمس كلها قابلة للنقر وفتح صفحات موجودة ·
     NeedsAttention كل بنوده روابط فعلية + بندان جديدان (تسجيلات بانتظار المراجعة،
     إتاحة غير معتمدة) · UpcomingSessions (أقرب 10 حصص بعمود view) · QuickActions
     (4 أزرار بوجهات موجودة) · نقل كل نصوص الـ widgets إلى lang/{ar,en}/dashboard.php
     · SessionsTrend مترجم.
     المتبقي للـ COMPLETE: Sessions requiring substitute widget · Recording problems
     (محجوز على غياب /admin/recordings) · Teacher apology requests صريحة · فلتر
     "المجمّدون" في /admin/students · إصلاح 500s الموارد الأربعة و403 policies.
8.0 | Navigation contract | FUNCTIONAL
     شكل موحد { href, labelKey, icon } + أيقونات SVG + active state للمسارات الفرعية.
```

## 4. Known issues (خارج ملكيتي — لأصحابها)

1. **500 `/admin/students`** — `modules/Students/.../StudentProfileResource.php:247`:
   `Filament\Tables\Actions\ViewAction` غير موجود في Filament v5 (الصحيح
   `Filament\Actions\ViewAction`). نفس الخطأ في
   `modules/Staff/.../StaffProfileResource.php:184` و
   `modules/Sessions/.../SessionResource.php:142`.
2. **500 `/admin/groups`** — `modules/Groups/.../GroupResource.php:132`:
   استدعاء `->color()` على Closure (badge column).
3. **403 `/admin/students/create` و `/admin/discipline-reactivations`** —
   Policies/canAccess للمواردها تمنع حساب admin@demo.local؛ تحتاج مراجعة مصفوفة
   الصلاحيات أو seed صلاحيات الديمو.
4. **`/admin/recordings` غير موجود نهائيًا** — RecordingResource مسجّل بلا صفحات
   List/Create/Edit؛ عند إضافة `ListRecordings` تصبح بطاقة «تسجيلات فاشلة»
   (`RecordingStatus::Failed`) إضافة سطر واحد.
5. **pending migrations لوكيلين آخرين** لم تطبَّق على قاعدة الديمو (phone_password_reset_tokens,
   recording_access_grants, preferred_course, classroom_events idempotency_key).
   طبّقت فقط هجرة `add_approval_to_teacher_availability_table` (Staff) لأن
   NeedsAttention يعتمد عمود `approval_status` — عبر
   `migrate --path=... --force`. البقية على أصحابها.
6. **ESLint لا يعمل في المستودع كله** — لا يوجد eslint.config رغم سكربت `lint`;
   اكتفيت بـ tsc.
7. **lazy-load**: تحقق محتوى الـ widgets تم عبر تنفيذها server-side (ليس عبر
   curl) لأن Livewire يؤجل mount حتى x-intersect؛ المتصفح سيجلب المحتوى تلقائيًا.

## 5. الملفات التي لمستها

- `app/Filament/Widgets/PlatformOverview.php` (تعديل)
- `app/Filament/Widgets/NeedsAttention.php` (تعديل)
- `app/Filament/Widgets/SessionsTrend.php` (تعديل)
- `app/Filament/Widgets/UpcomingSessions.php` (جديد)
- `app/Filament/Widgets/QuickActions.php` (جديد)
- `app/Providers/Filament/AdminPanelProvider.php` (تسجيل widgets فقط)
- `resources/views/filament/widgets/needs-attention.blade.php` (روابط + ترجمة)
- `resources/views/filament/widgets/upcoming-sessions.blade.php` (جديد)
- `resources/views/filament/widgets/quick-actions.blade.php` (جديد)
- `resources/lang/ar/dashboard.php` · `resources/lang/en/dashboard.php` (جديدان — المصدر)
- `lang/ar/dashboard.php` · `lang/en/dashboard.php` (جديدان — aliases كما في العقد)
- `resources/js/Layouts/AppLayout.tsx` (عقد التنقل + الأيقونات)
- `docs/page-matrix-updates/AGENT-8.md` (هذا السجل)
