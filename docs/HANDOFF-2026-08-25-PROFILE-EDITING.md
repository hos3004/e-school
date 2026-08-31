# تسليم: تعديل مركز المعلم والطالب وإغلاق أخطاء SQL

التاريخ: 2026-08-25

## بيئة العمل الإلزامية

- مصدر الحقيقة الوحيد: `/home/gamer/e-school` داخل Ubuntu/WSL2.
- ممنوع الكتابة أو الاختبار من `I:\e-school`؛ هي نسخة قديمة للقراءة فقط.
- ابدأ دائمًا بـ:

```bash
wsl -d Ubuntu -- bash -lc 'cd /home/gamer/e-school && git status --short --branch'
```

- الشجرة شديدة الاتساخ وبها تغييرات عمل صحيحة من مراحل سابقة. ممنوع `reset` أو
  `checkout` أو حذف الملفات غير المتتبعة. أكمل فوق الحالة الحالية بحذر.
- PHP/Composer/Artisan داخل Docker فقط.
- الاختبارات لا تعمل بأمر `php artisan test` المباشر بسبب Safety Guard. استخدم دائمًا:

```bash
docker compose exec -T app php scripts/test-isolated.php <paths> --compact
```

## ما أُغلق بالكامل في هذه الجولة

وصلت ثلاثة تقارير PostgreSQL `SQLSTATE[42703]`. لم يكن السبب نقصًا في الكود أو حاجة
إلى مهاجرات جديدة؛ كانت المهاجرات الموجودة لم تُطبّق على قاعدة التطوير.

1. `classrooms.deleted_at` من
   `modules/VirtualClassroom/database/migrations/2026_08_24_220000_harden_classroom_operations.php`.
2. `session_participants.revoked_at` من
   `modules/Sessions/database/migrations/2026_08_24_200000_harden_session_participant_lifecycle.php`.
3. `course_materials.organization_id` من
   `modules/Content/database/migrations/2026_08_24_170000_expand_course_materials_for_publishing.php`.

نُفّذ على قاعدة التطوير:

```bash
docker compose exec -T app php artisan migrate --force
```

وطُبّقت كذلك المهاجرات المتسلسلة التي كانت Pending:

- `2026_08_24_190000_harden_scheduling_integrity`
- `2026_08_24_210000_harden_attendance_lifecycle`
- `2026_08_25_000000_harden_assessment_attempts`

التحقق النهائي:

- `migrate:status` لا يعرض أي `Pending`.
- الاستعلام على `information_schema.columns` أكد وجود الأعمدة الثلاثة.
- الاستعلامات الفعلية بشروط `deleted_at/revoked_at/organization_id` نجحت على PostgreSQL.
- اختبارات الرجوع وإعادة التطبيق والتشغيل للموديولات الثلاثة نجحت:
  `13 passed (94 assertions)` عبر `scripts/test-isolated.php`.
- `php artisan route:list --path=admin --except-vendor` نجح وعرض 94 مسارًا.

## الحالة الحالية لمركز المعلم والطالب

الموجود بالفعل:

- `ViewStaffProfile` يعرض تبويبات حقيقية: الحساب، المؤهلات والكورسات، العقود والأسعار،
  المجموعات، التوافر، الحصص الأخيرة.
- `ViewStudentProfile` يعرض الحساب، القيود، المجموعات، الحصص الأخيرة.
- البيانات المركبة تأتي من
  `app/Application/Queries/ProfileAdministrationQueryService.php` عبر Contracts/DTOs؛
  لا توجد joins عابرة للموديولات.
- إضافة معلم/طالب جديد كليًا موجودة كـ onboarding آمن، مع دعم ربط حساب موجود.
- تسكين الطالب موجود عبر `AssignStudentToGroupAction`، وليس كتابة مباشرة في جدول العضوية.
- قرار اعتماد/رفض توافر المعلم موجود ومدقق.

لكن طلب التعديل التفصيلي **غير مكتمل**: أغلب التبويبات الآن للعرض فقط، وصفحتا
`EditStaffProfile` و`EditStudentProfile` ما زالتا تعتمدـان حفظ Filament الافتراضي
لبعض بيانات الملف. لا تعتبر هذه المهمة منتهية قبل تنفيذ البنود التالية.

## ترتيب التنفيذ المطلوب للـagent التالي

### 1. تثبيت خط الأساس قبل التعديل

اقرأ كاملًا: `AGENTS.md` و`docs/21-definition-of-done.md` و`PROJECT_MAP.md`، ثم افحص:

- `modules/Staff/src/Presentation/Filament/Resources/StaffProfileResource.php`
- `modules/Staff/src/Presentation/Filament/Resources/StaffProfileResource/Pages/ViewStaffProfile.php`
- `modules/Staff/src/Presentation/Filament/Resources/StaffProfileResource/Pages/EditStaffProfile.php`
- `modules/Students/src/Presentation/Filament/Resources/StudentProfileResource.php`
- `modules/Students/src/Presentation/Filament/Resources/StudentProfileResource/Pages/ViewStudentProfile.php`
- `modules/Students/src/Presentation/Filament/Resources/StudentProfileResource/Pages/EditStudentProfile.php`
- `app/Application/Queries/ProfileAdministrationQueryService.php`

معيار النجاح: صفحات الإدارة تقلع، ولا توجد مهاجرات Pending، واختبارات Staff/Students
الحالية مسجلة قبل التعديل.

### 2. تعديل بيانات الملف الأساسية عبر Actions لا عبر Filament المباشر

المعلم:

- أنشئ `Modules\Staff\Application\Actions\UpdateStaffProfileAction`.
- whitelist للحقول الشخصية/الوظيفية فقط: `staff_code`, `employment_type`, `gender`,
  `country_id`, `region_id`, `date_of_birth`, `phone`, `hired_at`, `specializations`, `bio`.
- لا تسمح بتعديل `organization_id`, `user_id`, `terminated_at` من النموذج العام.
- تحقق من الجغرافيا ومن أن الملف غير مؤرشف، وسجّل Audit كاملًا مع actor/reason.
- override لـ `EditStaffProfile::handleRecordUpdate()` لاستدعاء الـAction.
- أضف `reason` إلزاميًا في نموذج الإدارة ولا تحفظه في جدول `staff_profiles`.

الطالب:

- أصلح `UpdateStudentProfileAction::EDITABLE`: الكود الحالي يحتوي `country` القديم ولا
  يتضمن `country_id` و`region_id` رغم أن النموذج يعرضهما.
- أضف `AuditRecorder` و`actorId` و`reason`، ثم استدعِ الإجراء من
  `EditStudentProfile::handleRecordUpdate()` ومن Controller.
- لا تسمح بتعديل `student_code`, `organization_id`, `user_id` أو الحالة الأكاديمية
  من نموذج التفاصيل.
- `joined_at` قرار دورة حياة؛ لا تجعله حقلًا حرًا إلا عبر Action واضح ومدقق.

معيار النجاح: تعديل كل حقل مسموح يحفظ Audit؛ ومحاولات cross-tenant أو تعديل حقول
الملكية تُرفض؛ ولا يحدث حفظ Eloquent مباشر من Filament.

### 3. تعديل الحساب المرتبط للمعلم والطالب

- التنفيذ يكون في composition root (`app/Application/Actions`) لأن شاشة Staff/Students
  تربط أكثر من موديول.
- استخدم `Modules\Identity\Application\Actions\UpdateUserProfile` للحقول الآمنة:
  `name`, `phone`, `phone_country`, `locale`, `timezone`.
- استخدم `ChangeUserStatus` في Action منفصل للحالة مع سبب؛ لا تغيّر الحالة كنص.
- تحقّق أولًا أن `user_id` والملف في المؤسسة نفسها.
- لا تعدّل البريد أو اسم المستخدم أو كلمة المرور بصمت؛ البريد يحتاج verification flow،
  وكلمة المرور لها `UpdatePassword/ResetPassword`. وفّر رابطًا/إجراءً مناسبًا بدل تجاوزها.
- أضف Action واضحًا داخل تبويب «الحساب» في مركزي المعلم والطالب، وليس زرًا غامضًا عامًا.

### 4. المؤهلات والكورسات

- الموجود حاليًا `AssignTeacherQualificationsAction` إضافي فقط، ولا يزيل تأهيلًا.
- أنشئ تدفق add/revoke مدققًا. لا hard delete لتاريخ اعتماد المعلم.
- الأفضل إضافة حقول إلغاء إلى `teacher_courses` (`revoked_at`, `revoked_by`,
  `revocation_reason`) مع إبقاء unique الحالي وإعادة تفعيل السجل عند الاعتماد مجددًا.
- حدّث `TeacherQualificationQueryService` ليعيد المؤهلات الفعالة فقط.
- قبل الإلغاء، لا تكسر إسنادًا نشطًا بصمت؛ استخدم Query Contract من Groups/Sessions أو
  composition Action لفحص الاعتماد التشغيلي.
- واجهة التبويب: إضافة كورسات واعتمادها، وإلغاء اعتماد مفرد بسبب مكتوب.

### 5. العقود والأسعار — append-only

- لا تنفذ Edit مباشر للعقود أو الأسعار التاريخية.
- «تعديل العقد/السعر» في الواجهة يعني إنهاء فترة السجل الحالي وإنشاء سجل جديد نافذ
  من تاريخ محدد؛ القيود السابقة لا تتغير مطلقًا.
- الموجود: `CreateTeacherContract` و`AddTeacherRate`، لكنهما يحتاجان actor/reason وAudit
  صريحين في مسار الإدارة.
- أضف modal «عقد جديد» وmodal «سعر جديد» داخل تبويب العقود والأسعار.
- تحقق من overlap، النطاق، العملة، البرنامج/الكورس، وأن العقد يخص نفس المعلم والمؤسسة.
- لا تعدّل `payroll_entries` القديمة إطلاقًا.

### 6. المجموعات

- لا تجعل Staff يستورد Models من Groups.
- أنشئ composition Actions تحت `app/Application/Actions` لتحميل المجموعة في الموديول
  المالك واستدعاء `AssignTeacherAction` أو `UnassignTeacherAction` مع actor/reason.
- أضف Query/DTO options للمجموعات المتاحة داخل المؤسسة؛ لا تقرأ جدول `groups` من Staff.
- في تبويب المعلم: إسناد مجموعة/كورس/دور/فترة، وإنهاء إسناد بسبب.
- في تبويب الطالب: التسكين الموجود يعمل؛ أضف نقل/إنهاء عضوية عبر محركات
  Enrollments/Groups ولا تكتب `group_memberships` مباشرة.

### 7. التوافر

- أضف modal «فترة توافر جديدة» يستدعي `SetTeacherAvailability`.
- وسّع الإجراء ليأخذ actor/reason ويسجل Audit ويتحقق من التداخلات الزمنية.
- الإلغاء عبر `RemoveTeacherAvailability` مع سبب، والاعتماد/الرفض عبر
  `DecideTeacherAvailabilityAction` الموجود.
- اعرض حالة pending/approved/rejected بوضوح.

### 8. الحصص الأخيرة

- تبقى read-only داخل ملف الشخص؛ لا تسمح بتعديل حالة الحصة أو الحضور inline.
- اجعل كل صف يفتح Session Operations Hub للسجل نفسه، وهناك فقط تُنفذ Actions دورة
  الحياة المصرح بها.
- طبّق نفس السلوك للمعلم والطالب، مع منع كشف join secrets.

### 9. تفاصيل الطالب الإضافية

حتى يصبح «نفس الأمر للطالب» واقعيًا، نظّم المركز إلى:

- الحساب (تعديل آمن للحساب والحالة).
- البيانات الشخصية والجغرافية (Action مدقق).
- ولي الأمر/الروابط (Actions موديول Guardians، لا pivot write).
- البرامج والقيود (انتقالات Enrollments الرسمية فقط).
- المجموعات (تسكين/نقل/سحب آمن).
- الحصص الأخيرة (روابط لمركز الحصة).

لا تخلط حالة طلب التسجيل بحالة حساب الطالب أو حالة قيده.

### 10. الترجمة والصلاحيات والاختبارات

- أي نص جديد في `ar`, `en`, `fr`. Staff/Students حاليًا لديهما ar/en فقط؛ أضف fr
  للنصوص الجديدة على الأقل ولا تكتب نصوصًا inline.
- استخدم Policies والصلاحيات الموجودة، وحدّث `docs/06-permissions-matrix.md` فقط إذا
  أضيفت abilities فعلية.
- السبب إلزامي في كل تغيير حساس.
- اختبارات مطلوبة: happy path، cross-tenant، unauthorized، archived read-only، audit،
  overlap، append-only، وعدم حدوث regression.
- أوامر التحقق:

```bash
docker compose exec -T app php scripts/test-isolated.php modules/Staff/tests modules/Students/tests --compact
docker compose exec -T app vendor/bin/phpstan analyse modules/Staff/src modules/Students/src app/Application/Actions app/Application/Queries/ProfileAdministrationQueryService.php --no-progress --memory-limit=1536M
docker compose exec -T app vendor/bin/pint --test modules/Staff modules/Students app/Application
docker compose exec -T app php -d memory_limit=1536M scripts/test-isolated.php tests/Architecture --compact
docker compose exec -T app php artisan route:list --path=admin --except-vendor
```

ثم Browser QA بحساب مدير على `http://localhost:8090`: عدّل كل تبويب فعليًا، أعد تحميل
الصفحة، تحقق من بقاء القيمة، سجل التدقيق، RTL، النصوص العربية، وعدم وجود console errors.

## تحذير: Assessments في منتصف تنفيذ سابق

قبل هذه الأولوية كان موديول Assessments قيد إعادة بناء ولم يكتمل. لا تحذفه ولا تعلن
اكتماله:

- أضيفت Actions/Queries/ترجمات ومهاجرة تقوية المحاولات.
- أضيفت صفحات Filament الأساسية مؤقتًا لمنع فشل إقلاع اللوحة:
  `ListAssessments`, `CreateAssessment`, `ViewAssessment`, `EditAssessment`.
- `AssessmentResource::getRelations()` يعيد `[]` مؤقتًا؛ Relation Managers للأسئلة
  والمحاولات غير مبنية بعد.
- `AssessmentAttemptResource` ما زال المسار القديم ذي التعديل المباشر.
- Tests/seeders/callsites لم تُحدّث كلها لتواقيع Actions الجديدة.
- التحقق الحالي لهذا الجزء: routes الأربعة مسجلة، وPHPStan للـResource/Pages بلا أخطاء،
  وPint لخمس ملفات نجح. هذا لا يعني اكتمال الموديول.

بعد إنهاء ملفي المعلم والطالب، أكمل Assessments ثم حدّث `PROJECT_MAP.md` و
`docs/page-completion-matrix.md` و`docs/admin-panel-readiness.md` وفق الأدلة الفعلية فقط.

## قاعدة الإعلان عن الإنجاز

لا تقل «تم» لأن الأزرار ظهرت. الإنجاز يعني أن كل تعديل يمر عبر Action/Policy، يحفظ
Audit عند اللزوم، يحترم حدود الموديولات والمؤسسة، ينجح في isolated tests وArchitecture،
ويُجرّب يدويًا من الواجهة مع إعادة تحميل البيانات.
