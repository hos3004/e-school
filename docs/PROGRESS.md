# سجل تقدّم البناء — e-school Platform

## 2026-09-04 — مسار القرآن الفردي وحجز خانات المعلم

- أضيف كورس C-QURAN-IND باسم «القرآن الفردي» عبر بذرة إنتاج محدودة قابلة
  لإعادة التشغيل، مع نقل تأهيل معلمي القرآن الحاليين إليه دون تكرار.
- شاشة الجدولة تميّز التسكين الجماعي من الفردي، وترشّح الكورسات حسب نمط
  الحصة، وتعرض للفردي مدد 25 و35 و55 دقيقة فقط.
- اختيار الوقت يعتمد الإتاحة المعتمدة ويستبعد حجوزات المعلم المتعارضة، مع
  ملخص حي وإشعار نجاح بعدد الحصص ومواعيدها والخانات البديلة.
- يرسل إنشاء الجدول بريدًا وإشعارًا داخل التطبيق للطالب والمعلم بالجدول كاملًا،
  ويعمل تذكير الساعة تلقائيًا كل دقيقة مع زر تشغيل يدوي في لوحة التحكم.
- أضيف مفتاح في الملف الشخصي للطالب والمعلم لتعطيل بريد الجدول والتذكير، مع
  إبقاء إشعار التطبيق فعالًا.
- أضيفت اختبارات للخانات والتعارض ومدد الفردي والبذرة وترشيح الكورس، ورسائل
  الجدول، ومنع تكرار التذكير، وتفضيل تعطيل البريد، وظهور زر الإدارة.

> **سجل تاريخي للدفعات السابقة، وليس قائمة النطاق أو الطابور الحالي.**
> راجع `docs/phase-1-approved-scope.md` و`docs/agent-tasks/QUEUE-antigravity.md`.

> ملف حالة يقرأه loop البناء كل ١٥ دقيقة. البند المعلّم `[x]` مكتمل — لا يُعاد تنفيذه.
> عند اكتمال كل البنود، أو بعد `2026-08-21 23:28`، يتوقف اللوب.

**بدء البناء:** 2026-08-21 21:28
**الموعد النهائي للّوب:** 2026-08-21 23:28
**معرّف مهمة اللوب:** `5ec08961`

---

## المرحلة 0 — هيكل المستودع

- [x] 0.1 هيكل مجلدات Laravel + 27 module
- [x] 0.2 `composer.json` مع PSR-4 لكل module
- [x] 0.3 ملفات الجذر: `.gitignore` `.gitattributes` `.editorconfig` `.env.example` `README.md` `CLAUDE.md`
- [x] 0.4 هيكل Laravel التنفيذي: `bootstrap/` `public/` `artisan` `routes/`
- [x] 0.5 أدوات الجودة: `pint.json` `phpstan.neon` `phpunit.xml` `rector.php`
- [x] 0.6 بيئة Docker: `docker-compose.yml` + `docker/php/Dockerfile` + nginx
- [x] 0.7 `package.json` + `vite.config.ts` + `tailwind` + `tsconfig.json`
- [x] 0.8 CI: `.github/workflows/ci.yml`

## المرحلة 1 — النواة المشتركة (Shared Kernel)

- [x] 1.1 `shared/src` : `BaseModule`, `ModuleServiceProvider`, اكتشاف الموديولات
- [x] 1.2 Domain Events: `DomainEvent`, `EventBus`, سجل الأحداث
- [x] 1.3 أنواع أساسية: `Money`, `TimeRange`, `Timezone`, `Ulid` traits
- [x] 1.4 ملفات `config/`: `modules` `features` `virtual-classroom` `scheduling` `discipline` `payroll` `notifications` `academic`
- [x] 1.5 Enums الأساسية: SessionStatus · EnrollmentStatus · AttendanceStatus · PayrollPeriodStatus · PostponementStatus (البقية ضمن حزم الوكلاء)
- [x] 1.6 Contracts: VirtualClassroomProvider (البقية ضمن حزم الوكلاء في 20-agent-task-packages)

## المرحلة 2 — وثائق المعمارية (docs/)

- [x] 2.1 `00-README.md` + `client-answers.md` (محضر إجابات العميل)
- [x] 2.2 `01-PRD.md`
- [x] 2.3 `02-scope-and-phases.md`
- [x] 2.4 `03-domain-model.md`
- [x] 2.5 `04-entity-relationship-model.md`
- [x] 2.6 `05-state-machines.md`
- [x] 2.7 `06-permissions-matrix.md`
- [x] 2.8 `07-database-schema.md`
- [x] 2.9 `08-module-boundaries.md`
- [x] 2.10 `09-domain-events.md`
- [x] 2.11 `10-api-contracts.md`
- [x] 2.12 `11-provider-interfaces.md`
- [x] 2.13 `12-notification-architecture.md`
- [x] 2.14 `13-scheduling-rules.md`
- [x] 2.15 `14-payroll-rules.md`
- [x] 2.16 `15-security-model.md`
- [x] 2.17 `16-testing-strategy.md`
- [x] 2.18 `17-coding-standards.md`
- [x] 2.19 `18-ADRs.md`
- [x] 2.20 `19-agent-dependency-graph.md`
- [x] 2.21 `20-agent-task-packages.md`
- [x] 2.22 `21-definition-of-done.md`

## المرحلة 3 — الأساس التنفيذي

- [x] 3.1 هجرات النواة: users, organizations, roles/permissions, audit
- [x] 3.2 هجرات الأكاديمي: programs, courses, groups, enrollments
- [x] 3.3 هجرات التشغيل: schedules, sessions, attendance, classrooms, recordings
- [x] 3.4 هجرات المال: payroll ledger, billing (خلف feature flag)
- [x] 3.5 Seeders: الأدوار والصلاحيات + إعدادات المؤسسة + بيانات تجريبية
- [x] 3.6 اختبارات معمارية (Pest Arch) تحرس حدود الموديولات
- [ ] 3.7 تجهيز الملفات للـ commit الأول — لا تنفّذ git commit، اتركها للمستخدم

---

## سجل التنفيذ

| الوقت | الخطوة | ملاحظة |
|-------|--------|--------|
| 21:28 | 0.1 + 0.2 | 27 موديول، 946 مجلد، composer.json صالح |
| 21:42 | 0.3 – 0.8 | ملفات الجذر · Laravel skeleton · أدوات الجودة · Docker · Vite · CI |
| 21:50 | 1.1 – 1.6 | Shared Kernel · 12 ملف config يحمل قواعد العمل · 5 enums · عقد الفصل المباشر |
| 21:58 | 2.1 – 2.3 | 00-README · client-answers · 01-PRD · 02-scope |
| 22:05 | 2.4 – 2.6 | 03-domain-model · 04-ERD · 05-state-machines |
| 22:15 | 2.7 – 2.8 | 06-permissions-matrix · 07-database-schema |
| 22:22 | 2.9 · 2.10 · 2.15 | 08-module-boundaries · 09-domain-events · 14-payroll-rules |
| 22:32 | 2.14 · 2.19 · 2.22 | 13-scheduling-rules · 18-ADRs · 21-definition-of-done |
| 22:38 | 2.20 | 19-agent-dependency-graph |
| 22:07 | 2.11 | 10-api-contracts |
| 22:12 | 2.12 | 11-provider-interfaces |
| 22:27 | 2.13 | 12-notification-architecture |
| 22:42 | 2.16 | 15-security-model |
| 22:57 | 2.17 | 16-testing-strategy |
| 23:12 | 2.18 | 17-coding-standards |
| 23:27 | 2.21 | 20-agent-task-packages — المرحلة 2 مكتملة |
| 23:39 | إيقاف اللوب | انتهت المهلة (23:28). المهمة 5ec08961 محذوفة. المرحلة 3 لم تبدأ. |
| 08:40 | المراحل أ–هـ | 68 هجرة · 70 جدول · 609 ملف src · 207 مسار · لوحة Filament تعمل بحساب إداري · 9 أدوار و66 صلاحية |
| 08:40 | المرحلة و | 449 اختبار: 249 ناجح · 200 فاشل — إصلاح مستمر بـcodemods |
| 09:30 | الموجة 3 | 23 من 27 موديول مكتمل · 1012 ملف src · Scheduling كُتب يدويًا بعد فشل الوكلاء 10 مرات |
| 11:03 | إيقاف اللوب | انتهت المهلة. 1045 ملف src · 70 جدولًا · 49 مورد Filament · اللوحة تعمل ببيانات تجريبية. المتبقي موثّق في docs/CODEX-TASKS.md |
| 2026-08-22 | اعتماد Codex/OpenCode | صُححت صلاحيات Students/Staff/Attendance والمحادثات، وفُصل اعتماد Identity المعماري، وأُزيلت 86 compiled views من Git. الهجرات fresh/rollback/fresh ناجحة؛ Pint ناجح؛ PHPStan الموجّه صفر. الجولة الكاملة: 686 ناجحًا · 75 فاشلًا · 4502 توكيدًا. التصنيف `NEEDS_REVIEW_BEFORE_PRODUCTION` ولا دمج إلى main. |
| 2026-08-24 | Student onboarding + profile hubs | Wizard إداري ينشئ حسابًا جديدًا أو يربط الموجود داخل معاملة، تسكين آمن عبر المنسق المركزي مع السبب والتدقيق، وStudent/Teacher Hubs عبر DTO Query Contracts. المسارات المتأثرة: 15 اختبارًا ناجحًا · 123 توكيدًا · PHPStan موجّه بلا أخطاء. |
| 2026-08-31 | Flexible registration forms | منشئ Filament متعدد النماذج والأسئلة، روابط slug مستقلة، حفظ مصدر الطلب ولقطة الإجابات، فلتر مصدر وفلاتر أسئلة مضبوطة، وثلاثة قوالب بداية. نجحت 34 اختبارات/90 توكيدًا، وملكية الجداول 3/3، وrollback/reapply، وPint/PHPStan، وTypeScript/Vite؛ بقي QA بصري فقط بسبب bind mount قديم داخل Docker. |
| 2026-09-03 | واجهة Tele Course العامة | أضيفت 11 صفحة تسويقية عربية/إنجليزية، 5 مسارات برامج، 21 صورة محسّنة، SEO وحركات خفيفة ودعم RTL والموبايل. نجح TypeScript والبناء، ونجحت 15 اختبارات Feature بـ130 توكيدًا. |
