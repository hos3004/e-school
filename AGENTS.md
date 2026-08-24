# AGENTS.md — عقد العمل داخل هذا المستودع

هذا المشروع يُبنى بواسطة عدة agents تعمل بالتوازي. القواعد التالية ليست اقتراحات —
هي ما يمنع 10 agents من إنتاج 10 مستويات جودة مختلفة.

---

## 0. بيئة العمل النشطة — WSL2 فقط (إلزامي)

تم نقل نسخة العمل المعتمدة إلى Ubuntu داخل WSL2. من الآن فصاعدًا:

- **مصدر الحقيقة الوحيد القابل للتعديل:** `/home/gamer/e-school`
- الوصول إليه من Windows: `\\wsl.localhost\Ubuntu\home\gamer\e-school`
- النسخة `I:\e-school-ARCHIVED-2026-08-24` **قديمة وللقراءة فقط**. ممنوع تعديلها أو تشغيل Docker أو
  الاختبارات أو إنشاء commits منها، وممنوع مزامنتها إلى Linux إلا بطلب صريح من المستخدم.
- إذا بدأ الـagent و`cwd` هو `I:\e-school-ARCHIVED-2026-08-24`، يجب أن يتوقف عن أي كتابة وينتقل أولًا إلى
  `/home/gamer/e-school` داخل Ubuntu/WSL2.
- كل أوامر Git وDocker وPHP وComposer وArtisan وNode، وكل تعديلات الملفات، تُنفّذ على
  نسخة Linux فقط. مثال تحقق قبل بدء أي مهمة:

```bash
wsl -d Ubuntu -- bash -lc 'cd /home/gamer/e-school && git status --short --branch'
```

لا تُنسخ تغييرات Linux إلى `I:\e-school-ARCHIVED-2026-08-24` تلقائيًا؛ أي نسخ أو مزامنة بين النسختين يحتاج
موافقة صريحة من المستخدم وتحديد اتجاه المزامنة.

## 1. المعمارية

**Modular Monolith.** تطبيق Laravel واحد، و27 موديول تحت `modules/`، لكل واحد namespace
مستقل `Modules\<Name>` ومسار `modules/<Name>/src/`.

بنية كل موديول:

```
modules/<Name>/
  src/Domain/          Models · Enums · Events · Contracts · ValueObjects · Exceptions
  src/Application/     Actions · Queries · Listeners · Policies
  src/Infrastructure/  Providers · Persistence · تكاملات خارجية
  src/Presentation/    Http (Controllers/Requests/Resources) · Filament · Routes
  database/migrations/ هجرات هذا الموديول فقط
  resources/lang/{ar,en}/
  tests/{Unit,Feature}/
```

## 2. قواعد الحدود بين الموديولات — غير قابلة للتفاوض

1. **ممنوع** استيراد `Modules\A\...\Domain\Models\*` من داخل الموديول `B`.
   التواصل بين الموديولات يتم عبر ثلاثة طرق فقط:
   - **Domain Events** (الافتراضي — انظر `docs/09-domain-events.md`)
   - **Public Contracts** المعلنة في `src/Domain/Contracts/` للموديول المالك
   - **Query Services** للقراءة فقط، تُرجع DTOs وليس Eloquent models

2. **ممنوع** أن يعرف موديول جدول موديول آخر. لا `join` عابر للحدود في كود التطبيق؛
   التقارير المجمّعة تعيش في موديول `Reporting` عبر Read Models.

3. اختبارات `tests/Architecture` تُنفّذ هذه القواعد آليًا. لو كسرتها، CI يسقط.

## 3. قواعد العمل ممنوع أن تكون hardcoded

أي رقم يخص سياسة المدرسة يعيش في `config/` أو في جدول إعدادات — أبدًا داخل الكود:

- مهلة الإلغاء (٦٠ دقيقة) ومهلة التأجيل (١٥ دقيقة) → `config/scheduling.php`
- عدد الغيابات قبل التنبيه/التجميد (١، ٢، ٣ شهريًا) → `config/discipline.php`
- من يُخصم منه ومن يُحتسب له عند الغياب/التأجيل/الاستبدال → `config/payroll.php`
- مدة الاحتفاظ بالتسجيلات (٣٠ يومًا) → `config/recordings.php`

لو كتبت `if ($count >= 3)` في كود موديول، فأنت كسرت هذا العقد.

## 4. المال — دفتر أستاذ لا يُعدّل

`payroll_entries` و `billing_entries` هي **ledger append-only**.

- عند اعتماد الحصة تُنشأ قيدة بالسعر **وقت الحصة**، ويُخزَّن السعر في القيدة نفسها.
- تغيير سعر المعلم لاحقًا لا يمس القيود القديمة أبدًا.
- التصحيح يكون بقيدة تسوية جديدة (`adjustment`) وليس بتعديل أو حذف.
- بعد حالة `paid` تُقفل الفترة؛ أي تغيير يصبح قيدة في الفترة التالية.

## 5. الحالات = Enums، وليست strings

كل دورة حياة لها `enum` في `src/Domain/Enums/` مع دالة `canTransitionTo()`.
الانتقالات المسموحة معرّفة في `docs/05-state-machines.md`. ممنوع `$session->status = 'done'`.

## 6. لا حذف — تعليق فقط

الطالب الموقوف أو المجمّد **لا يُحذف حسابه ولا بياناته**. نغيّر حالته ونمنع وصوله
للكورسات فقط. كل الجداول التي تحمل بيانات بشرية تستخدم `SoftDeletes` بحد أدنى.

## 7. التدقيق (Audit)

كل تعديل على: الحضور، الحالة الأكاديمية، المال، الصلاحيات، والتسجيلات
يُسجَّل في `audit_log` بـ (من، ماذا، قبل، بعد، متى، السبب). التغيير بدون سبب مكتوب
مرفوض على مستوى الـ FormRequest في العمليات الحساسة.

## 8. اللغة والتوطين

- الافتراضي **عربي RTL**، مدعوم كذلك `en` و `fr`.
- **ممنوع** نص مكتوب مباشرة في الواجهة أو في رسالة إشعار. كل النصوص عبر ملفات الترجمة.
- التواريخ تُخزَّن **UTC** دائمًا وتُعرض بتوقيت المستخدم.

## 9. الصلاحيات

لا `if ($user->role === 'admin')` في أي مكان. الصلاحيات عبر Policies + `can:` middleware
حسب مصفوفة `docs/06-permissions-matrix.md`. كل مورد جديد يحتاج Policy وسطر في المصفوفة.

## 10. تعريف "خلصت"

لا يُعلن أي agent إنهاء مهمة قبل أن تتحقق كل بنود `docs/21-definition-of-done.md`.

---

## أوامر متكررة

```bash
docker compose exec app php artisan test
docker compose exec app composer check
docker compose exec app php artisan module:make-migration <Module> <name>
```

## ملاحظات بيئة التطوير

- PHP على جهاز المستخدم قديم (5.6) — **كل أوامر PHP وComposer وArtisan تُنفَّذ داخل Docker**.
- قاعدة البيانات PostgreSQL؛ لا تستخدم دوال خاصة بـ MySQL في الهجرات أو الاستعلامات.
