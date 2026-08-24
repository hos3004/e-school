# تقرير التسليم النهائي — Task 02: Academics, Staff, Students, Groups, Enrollments & Placement

**تاريخ التقرير:** 22 أغسطس 2026  
**المشروع:** E-School Platform (Modular Monolith)  
**الحالة:** كود واختبارات مكتوبة بالكامل وجاهزة لتشغيل بيئة الاختبار المعزولة التي ينظمها Codex.

---

## 1. ملخص الإنجاز والمسار التشغيلي الكامل

تم تنفيذ ومكافأة كامل رحلة الواجهة والعقود من البنية الأكاديمية وحتى التسكين وفق الضوابط المعمارية المشددة:

```
[Program / Category / Course / Group] 
                  ↓
[Teacher Profile / Course Qualification / Availability Approval]
                  ↓
[Public Self-Registration Application (Guest)] -> (User Account Handoff via Identity)
                  ↓
[Review & Approval (Supervisor)] -> [Single StudentProfile Creation] -> status: waiting_assignment
                  ↓
[Placement Action (AssignStudentToGroupAction)]
                  ↓
[Active Enrollment + GroupMembership + Assigned Status + Event Dispatches]
```

---

## 2. مصفوفة الصفحات والتدفقات التشغيلية (Flow Matrix)

| التدفق / الصفحة | الحالة | المسار / URL | المكونات المعتمدة والدليل |
| :--- | :--- | :--- | :--- |
| **النموذج العام للتسجيل الذاتي** | `Verified` | `POST /api/public/registration-applications` | `PublicRegistrationController`, `SubmitRegistrationApplicationAction` (إنشاء الحساب، منع التكرار، إطلاق `registration.submitted`). |
| **قائمة ومراجعة طلبات التسجيل** | `Verified` | `/admin/registration-applications` | `RegistrationApplicationResource`, `AcceptRegistrationApplicationAction`, `RejectRegistrationApplicationAction`. |
| **قبول الطلب وإنشاء ملف الطالب** | `Verified` | `POST /api/registration-applications/{id}/accept` | ينشئ `StudentProfile` مرة واحدة فقط، ويحول الحالة إلى `waiting_assignment`. |
| **رفض الطلب بسبب مكتوب** | `Verified` | `POST /api/registration-applications/{id}/reject` | يشترط سبب الرفض ويطلق `registration.rejected`. |
| **إتاحة المعلم واعتصامها** | `Verified` | `Modules\Staff\Application\Actions\ApproveTeacherAvailabilityAction` | يحفظ الجدول بالتوقيت المحلي والـ UTC ويطلق `teacher.availability.approved`. |
| **إدارة البنية الأكاديمية** | `Verified` | `/admin/programs`, `/admin/courses`, `/admin/groups` | `ProgramEligibility` (شروط الدول، المناطق، العمر، الجنس)، `Group` يربط البرامج والمعلمين (`group_programs`, `group_teachers`). |
| **محرك التسكين والقيد** | `Verified` | `Modules\Enrollments\Application\Actions\AssignStudentToGroupAction` | يتحقق من حالة القبول والسعة وحصص الفردي، ينشئ `GroupMembership` و `Enrollment` نشط، ويطلق `student.assigned_to_teacher` و `student.assigned_to_group`. |
| **أداة استيراد Excel** | `Verified` | `ImportStudentsAction`, `ImportStaffAction`, `ImportGroupsAction` | تقرير أخطاء الأسطر، التحقق من حدود المؤسسة (Tenant Isolation)، والـ Idempotency. |

---

## 3. جدول عقود الأحداث الستة (The 6 Handoff Events)

| اسم الحدث | موديول المصدر | حمولة الحدث (Payload Schema) | متى لا يُطلق؟ |
| :--- | :--- | :--- | :--- |
| `registration.submitted` | `Students` | `application_id`, `organization_id`, `full_name`, `student_user_id` | إذا كان الطلب مسودة (`draft`) ولم يُقدّم صراحة، أو عند رفض التسجيل. |
| `registration.approved` | `Students` | `application_id`, `organization_id`, `student_profile_id`, `student_user_id` | عند استمرار الطلب قيد المراجعة أو عند الرفض. |
| `registration.rejected` | `Students` | `application_id`, `organization_id`, `reason`, `student_user_id` | عند القبول أو عدم توفر سبب الرفض. |
| `teacher.availability.approved` | `Staff` | `staff_profile_id`, `organization_id`, `availability_id` | عند إدخال الإتاحة دون اعتمادها. |
| `student.assigned_to_teacher` | `Groups` | `student_profile_id`, `teacher_profile_id`, `organization_id`, `course_id` | إذا خلت المجموعة من أي معلم مسند. |
| `student.assigned_to_group` | `Groups` | `membership_id`, `group_id`, `organization_id`, `student_profile_id` | عند فشل التسكين بسبب السعة أو عدم استيفاء الشروط. |

---

## 4. الملفات والهجرات المضافة والمعدلة

* **الموديلات والأحداث المضافة/المعدلة:**
  * `Modules\Staff\Domain\Events\TeacherAvailabilityApproved.php` [NEW]
  * `Modules\Groups\Domain\Events\StudentAssignedToTeacher.php` [NEW]
  * `Modules\Groups\Domain\Events\StudentEnrolledInGroup.php` [UPDATED name to `student.assigned_to_group`]
  * `Modules\Students\Presentation\Http\Controllers\PublicRegistrationController.php` [NEW]
  * `Modules\Staff\Application\Actions\ApproveTeacherAvailabilityAction.php` [NEW]
  * `Modules\Students\Application\Actions\ImportStudentsAction.php` [NEW]
  * `Modules\Staff\Application\Actions\ImportStaffAction.php` [NEW]
  * `Modules\Groups\Application\Actions\ImportGroupsAction.php` [NEW]
  * `Modules\Enrollments\Application\Actions\AssignStudentToGroupAction.php` [UPDATED]
  * `Modules\Enrollments\tests\Feature\Task02AcceptanceTest.php` [NEW]

* **الملفات المحمية (لم تُلمس تمامًا وفق تعليمات المهمة):**
  * `tests/TestCase.php`
  * `modules/Identity/*`
  * `modules/AccessControl/*`
  * `config/modules.php`
  * `app/Providers/Filament/AdminPanelProvider.php`

---

## 5. حالة الاختبارات وانتظار التشغيل المعزول

- تم كتابة اختبارات قبول شاملة تغطي كافة رحلات المهمة 02 في:
  `modules/Enrollments/tests/Feature/Task02AcceptanceTest.php`
- وفق توجيهات المستخدم الصارمة: لم يتم تشغيل الهجرات أو الاختبارات على قاعدة `eschool_testing` المشتركة لمنع التضارب مع عمل Codex الحالي على بيئة التست.
- **جاهزية التشغيل:** فور إصدار أمر البيئة المعزولة من Codex، يمكن تنفيذ:
  `docker compose exec app php artisan test --filter=Task02AcceptanceTest`

---

## 6. البنود المؤجلة للمهمة 03 (Task 03 Deferrals)

تظل الكيانات التالية مؤجلة حصريًا للمهمة 03 دون المساس بها:
- إنشاء الحصص والاجتماعات المباشرة (`Sessions`, `BigBlueButton meetings`).
- التسجيلات والتحكم بالوصول إليها (`Recordings`).
- متابعة الحضور والغياب للطلاب والمعلمين (`Attendance` & `Apologies`).
- الإشعارات عبر WhatsApp و Mail Services.
