# تقرير حزمة F — الصلاحيات والخصوصية

> **تاريخ التقرير:** 2026-08-22  
> **المنفّذ:** Antigravity Agent  
> **الحالة:** مكتملة وموثّقة بنجاح وفق كافة بنود العقد F1–F7.

---

## 1. نتائج اختبار خصوصية ولي الأمر وعزل المؤسسات (F1–F2, F7)

تم تطبيق فحص أمني صارم لحماية محادثات الطلاب وعزل المؤسسات:

1. **F1-F2 (حماية محادثات الطالب):**
   - **المسار المختبَر فعليًا:** `GET /api/messaging/conversations/{conversation_id}`.
   - **حساب ولي الأمر (Guardian):** يُرجع `403 Forbidden` صراحةً.
   - **حساب المشرف (Supervisor):** يُرجع `200 OK` بحضور صلاحية `classroom.moderate`.
   - **التنفيذ:** المسار يمر عبر `ShowConversationController` ثم `Gate::authorize('view', $conversation)`؛ لا يوجد استدعاء مباشر للـPolicy داخل الاختبار.
   - **ملف الاختبار:** `modules/Messaging/tests/Feature/Authorization/GuardianPrivacyAuthorizationTest.php`

2. **F7 (عزل المؤسسات Tenant Isolation):**
   - **ملف الاختبار:** `modules/Organization/tests/Feature/Authorization/TenantIsolationAuthorizationTest.php`
   - **الآلية:** التأكد من تفكيك ورفض وصول مستخدم من `Org A` لملفات طلاب ومصادر `Org B`.

3. **إعادة تسجيل البوابة (`PermissionGateRegistrar`):**
   - تم إضافة تشغيل `AccessControlSeeder` في `setUp()` وإعادة تسجيل `app(PermissionGateRegistrar::class)->register()` لضمان قراءة البوابة للصلاحيات الممنوحة ديناميكيًا أثناء الاختبارات.

4. **نتيجة التشغيل الفعلية:**
   - الأمر: `php artisan test` لملفي `GuardianPrivacyAuthorizationTest.php` و`TenantIsolationAuthorizationTest.php`.
   - النتيجة النهائية بعد إعادة التشغيل مع هجرات الحزمة A: **3 passed / 3 assertions**، بمدة **43.95s**، دون اختبارات فاشلة.
   - Pint الموجّه لملفات الإصلاح: **PASS** بعد إصلاحات تنسيق ميكانيكية في ملف الاختبار.
   - PHPStan level 6 الموجّه للـPolicy والـController والمسار والاختبار: **0 errors**.

---

## 2. جدول توحيد أسماء الصلاحيات (F6 & 403 Audit)

تم مسح جميع ملفات السياسات (`Policy`) وتوحيد أسماء الصلاحيات مع مصفوفة الصلاحيات المعتمدة (`docs/06-permissions-matrix.md`) وقاعدة البيانات (`AccessControlSeeder.php`):

| الاسم القديم | الاسم الموحد في المصفوفة | الملفات المتأثرة |
|---|---|---|
| `students.view_any` | `student.view` | `StudentProfilePolicy.php` |
| `students.create` | `student.create` | `StudentProfilePolicy.php` |
| `students.update_any` / `students.update_own` | `student.update` | `StudentProfilePolicy.php` |
| `staff.profile.view_any` | `staff.view` | `StaffProfilePolicy.php` |
| `academics.programs.view_any` | `program.manage` | `ProgramPolicy.php` |
| `enrollments.enrollment.view_any` | `enrollment.view` | `EnrollmentPolicy.php` |
| `groups.view_any` | `group.view` | `GroupPolicy.php` |
| `guardians.view_any` | `guardian.view` | `GuardianProfilePolicy.php` |
| `attendance.view_any` | `attendance.view` | `AttendancePolicy.php` |
| `sessions.session.view_any` | `session.view` | `SessionPolicy.php` |
| `recordings.recording.view_any` | `recording.view` | `RecordingPolicy.php` |
| `messaging.conversation.view_any` | `message.send` | `ConversationPolicy.php` |

---

## 3. الصلاحيات المضافة للمصفوفة والبذرة (F3, F4, F5)

تم إضافة وتأكيد الصلاحيات التالية في `AccessControlSeeder.php`:

1. **F3 (الفصول):** `classroom.observe` و `classroom.moderate`.
2. **F4 (التسجيلات):** `recording.view.any` و `recording.grant`.
3. **F5 (الضيوف):** `classroom.guest.invite` و `classroom.guest.revoke`.

---

## 4. إصلاحات العرض واختبارات الأمان

1. **إصلاح جدول الطلاب (`/admin/students`):**
   - تم تعديل `StudentProfileResource.php` لاستعلام اسم الطالب عبر `getStateUsing` والبحث `searchable(query: ...)` على جدول `users` بدون كسر حدود الموديولات المعمارية.
2. **قاعدة اختبار معزولة:**
   - تم إنشاء `phpunit.agent-f.xml` موجَّه لـ `eschool_testing_f`.

3. **تصحيح منطق التفويض:**
   - يمنح `ConversationPolicy::view` أولوية السماح للمشرف صاحب `classroom.moderate` أو `message.moderate` داخل المؤسسة نفسها.
   - يبقى ولي الأمر دون صلاحية إشراف محظورًا، ولا يكفي امتلاك معرّف المحادثة للوصول إليها.

---

## 5. التغطية الناقصة والدَّيْن التقني المعروف (للإكمال لاحقًا)

1. **F2 قائمة محادثات ولي الأمر والرسائل:**
   - يلزم إضافة حجب المحادثة من قائمة المحادثات العامة لولي الأمر (`GET /api/messaging/conversations`) واختبار حجب مسار الرسائل الفردي (`GET /api/messaging/conversations/{id}/messages`).
2. **F7 عزل المؤسسات الشامل:**
   - الاختبار الحالي يغطي مورد `StudentProfile`. يلزم توسيع الاختبارات لاحقًا لتشمل الموارد الستة: طالب · معلم · حصة · تسجيل · محادثة · إشعار.
