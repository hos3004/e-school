# تقرير AGENT H — إصلاح اختبارات الموديولات غير المشغولة

> قاعدة الاختبار المعزولة: `eschool_testing_agent_h` عبر `phpunit.agent-h.xml`
> (نسخة `phpunit.xml` بتغيير `DB_DATABASE` فقط).
> أمر التشغيل: `docker compose exec -T app vendor/bin/pest --configuration=phpunit.agent-h.xml modules/<Module>/tests`

## جدول النتائج — يُحدَّث موديولًا بموديول

| الموديول | قبل | بعد | ما أُصلح فعلًا | ملفات الإنتاج المعدَّلة |
|----------|-----|-----|-----------------|--------------------------|
| Audit | 13 فاشل / 12 ناجح (31 تأكيد) | **0 فاشل / 25 ناجح (76 تأكيد)** ✅ | سببان: **(1)** `AuditLogFactory` كان يستدعي `Fixtures::organizationId()` الذي يستعلم/يُدرج في جدول `organizations` بينما الهجرة كانت محصورة بمسار Audit فينفجر بـ `SQLSTATE[42P01] relation "organizations" does not exist`. عمود `audit_log.organization_id` nullable وبلا FK (هجرة `2026_01_01_000030`)، فالمصنع الآن يولّد ULID عشوائيًا. لم يُمسّ أي اختبار. **(2) إصلاح جذري عابر للموديولات:** `RefreshAuditDatabase::migrateFreshUsing()` كان يحصر `migrate:fresh` في مسار Audit، وعلم `RefreshDatabaseState::$migrated` عام على مستوى العملية — فبعد أول كلاس Audit تتخطى كل الكلاسات اللاحقة (في أي موديول بنفس العملية) الهجرة وتجد قاعدة ناقصة. حُذفت الدالة ليعود السلوك الافتراضي (هجرة كل الموديولات — 80 جدولًا، صفر فشل عند الاختبار اليدوي). | `modules/Audit/database/factories/AuditLogFactory.php` · `modules/Audit/tests/Support/RefreshAuditDatabase.php` (حذف override فقط؛ بقية الـtrait كما هي) |
| Guardians | 29 فاشل / 2 ناجح (آخر تشغيل كامل بعد إصلاح الجذر أعلاه) | ⏸️ **معلّق — السبب خارج نطاقي** (انظر القسم ٢، البند ١). سيُعاد تشغيله بعد إصلاح الملف الخارجي | لا شيء داخل النطاق — الفشل المتبقي كله من تغيّر مخطط `users` خارج نطاقي مقابل `shared/src/Testing/Fixtures.php` الذي لم يُحدَّث | — |

---

## ١. فشل لم أستطع إصلاحه

(لا شيء حتى الآن)

## ٢. أسباب خارج نطاقي

### البند ١ — هجرة Identity الجديدة (`username` NOT NULL) ضد `shared/src/Testing/Fixtures.php` غير المحدَّث — يوقف Guardians

- **الملفات المعنية (كلها خارج نطاقي — لم ألمسها):**
  - `modules/Identity/database/migrations/2026_08_22_110500_add_username_and_phone_to_users.php` (حُفظت 2026-08-22 الساعة 16:28): backfill للأسماء ثم `ALTER TABLE users ALTER COLUMN username SET NOT NULL` (السطر 79).
  - `shared/src/Testing/Fixtures.php` (آخر تعديل 12:34 صباحًا — قبل الهجرة): `userId()` السطر 58 يُدرج في `users` بالأعمدة `(id, organization_id, name, email, password, created_at, updated_at)` **بلا username**.
- **رسالة الفشل الكاملة:**
  ```
  SQLSTATE[23502]: Not null violation: 7 ERROR: null value in column "username"
  of relation "users" violates not-null constraint
  ```
- **الأثر:** 25 استخدامًا لـ `Fixtures::(organizationId|userId|studentProfileId)` في اختبارات Guardians (Unit وFeature والمصنعان `GuardianProfileFactory` و`GuardianLinkFactory`). أغلبها داخل أجسام الاختبارات نفسها — تعديلها محظور بعقد المهمة («أصلح سبب الفشل لا الاختبار»)، والملفان المصدران (Identity + shared) خارج نطاقي أيضًا.
- **ما جرّبته:** تحقق يدوي أن مشكلة `organizations` اختفت بعد الإصلاح الجذري (اختفت فعلًا — الخطأ انتقل من `42P01 table missing` إلى `23502 not-null`)؛ تتبّع السلسلة الكاملة حتى الملفين المصدرين؛ فحص زمن الحفظ للتأكد أن الهجرة أحدث من `Fixtures`.
- **لماذا توقفت:** أي إصلاح يتطلب تعديل `shared/**` أو `modules/Identity/**` — كلاهما محظور صراحة. المنفّذ الآخر نشِط الآن وسيتصدم بالمشكلة ذاتها في موديوله — مرشح قوي يصلّح `Fixtures::userId()` بإضافة username قريبًا. سأعيد زيارة Guardians قبل إغلاق التقرير.

---
