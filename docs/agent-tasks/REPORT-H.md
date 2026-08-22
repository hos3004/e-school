# تقرير AGENT H — تحقق اختبارات الموديولات غير المشغولة

> سجّلت جولة OpenCode الأصلية **201 اختبارًا ناجحًا، صفر فشل، و558 توكيدًا** على
> قاعدة معزولة، لكنها كانت جولة تحقق بنيوي للاختبارات وليست إثباتًا لصلاحيات
> الإنتاج أو لجاهزية الموديولات السبعة. بعد المراجعة أزيل تكييف اختبارات
> Assessments الذي كان يعرّف صلاحيات غير قابلة للتحقق في الإنتاج. ثم نجحت
> الحزم المقبولة Audit وAttendance وAcademicReports في جولة مستقلة: **78 اختبارًا
> و227 توكيدًا**. أما Assessments فكشف وضعه الحقيقي: **27 ناجحًا و6 فاشلة
> (84 توكيدًا)** بسبب 403 بعد إزالة الـGates الوهمية.

## نتيجة الجولة الأصلية وحدودها

| الموديول | نتيجة الجولة الأصلية | حكم الاعتماد الحالي |
|---|---:|---|
| Audit | 25 اختبارًا / 76 توكيدًا | **مُعاد التحقق وناجح**؛ إصلاح الهجرة والمصنع ومسار `Factories` مقبول |
| Guardians | 31 / 85 | تحقق بنيوي فقط؛ لا تغيير لـOpenCode داخل الموديول |
| Attendance | 31 / 91 | **مُعاد التحقق وناجح**؛ helper وتصحيح أسماء الصلاحيات مقبولان، مع بقاء مانع نطاق المؤسسة أدناه |
| Assessments | 33 / 96 | **غير معتمد**؛ بعد إزالة Gates الوهمية: 27 ناجحًا و6 فاشلة / 84 توكيدًا |
| AcademicReports | 22 / 60 | **مُعاد التحقق وناجح**؛ إضافة `original_teacher_id` للمصنع مقبولة |
| Discipline | 28 / 71 | تحقق بنيوي فقط؛ لا تغيير لـOpenCode داخل الموديول |
| Reporting | 31 / 79 | تحقق بنيوي فقط؛ لا تغيير لـOpenCode داخل الموديول |
| **الإجمالي التاريخي** | **201 / 558** | **ليس نتيجة حالية بعد المراجعة ولا بوابة جاهزية إنتاجية** |

## بيئة التحقق المعزولة

استُخدمت قاعدة `eschool_testing_agent_h` وملف محلي `phpunit.agent-h.xml`. الملف
مستبعد عمدًا عبر `.gitignore`، لذلك لا يوجد في clone جديد. لإنشائه من الملف
المتتبع `phpunit.xml` على PowerShell:

```powershell
Copy-Item phpunit.xml phpunit.agent-h.xml
$xml = Get-Content phpunit.agent-h.xml -Raw
$xml = $xml.Replace('value="eschool_testing"', 'value="eschool_testing_agent_h"')
Set-Content phpunit.agent-h.xml -Value $xml -NoNewline -Encoding utf8
```

أو على Bash:

```bash
cp phpunit.xml phpunit.agent-h.xml
sed -i 's/value="eschool_testing"/value="eschool_testing_agent_h"/' phpunit.agent-h.xml
```

بعد إنشاء قاعدة PostgreSQL المعزولة، يكون أمر الموديول:

```bash
docker compose exec -T app vendor/bin/pest --configuration=phpunit.agent-h.xml modules/<Module>/tests
```

لم تستخدم الجولة الأصلية `php artisan test` ولم تنفذ `migrate:fresh` على قاعدة
التطوير المشتركة.

## التغييرات المقبولة

### Audit

- استبدل `AuditLogFactory` الاعتماد على `Fixtures::organizationId()` بـULID مستقل؛
  العمود nullable وبلا FK، فلا يحتاج المصنع للكتابة في جدول Organization.
- حُذف `migrateFreshUsing()` المقيد بمسار Audit. علم
  `RefreshDatabaseState::$migrated` عام داخل العملية، ولذلك كانت الهجرة الجزئية
  تترك الكلاسات التالية أمام قاعدة ناقصة.
- صُحح مسار التحميل اليدوي إلى
  `modules/Audit/database/Factories/AuditLogFactory.php`. الكتابة السابقة
  `database/factories` كانت تمر على Windows ويمكن أن تفشل على CI/Linux.
- تغيير المصنع وحذف override سبقا الدخول في commit `183a43b`. إصلاح حالة الأحرف
  وتنظيف التعليق متابعة غير ملتزمة في شجرة العمل.

### Attendance

- أضيف `original_teacher_id` إلى `CreatesSessionParticipant` بنفس قيمة
  `staff_profile_id` لأن الحصة الاختبارية غير مستبدلة. هذا التغيير سبق دخوله في
  commit `8750500`.
- أُعيدت `AttendancePolicy` إلى المرجعين الحاكمين، مصفوفة الصلاحيات وSeeder:
  `viewAny/view` يستخدمان `attendance.view` و`confirm` يستخدم
  `attendance.record`. أزيل تعريف `attendance.view_any` و`attendance.confirm`
  الوهميين من اختبارات Attendance.
- **مانع قديم خارج هذه الحزمة:** الاستعلامات والسياسة لا تفرضان بعد نطاق المؤسسة
  أو ملكية الطالب/المعلم. نجاح اختبار الصلاحية لا يثبت عزل بيانات الحضور بين
  المؤسسات، ولا يجوز تصنيف المسار جاهزًا للإنتاج قبل معالجة ذلك باختبار object-level.

### AcademicReports

- أضيف `original_teacher_id` إلى إدراج الحصة في `SessionReportFactory` بنفس قيمة
  المعلم الفعلي. هذا مطلوب بعد هجرة Sessions `2026_08_22_150000`، والتغيير باقٍ
  في شجرة العمل.

## Assessments — غير معتمد

طبقة FormRequests والموارد تستخدم أسماء ثلاثية مثل
`assessments.assessment.create` و`assessments.attempt.submit`، بينما
`docs/06-permissions-matrix.md` و`AccessControlSeeder` يسجلان
`assessment.manage` و`assessment.take` و`grade.view` فقط.

كانت الجولة الخضراء تعرف الاسمين محليًا في الاختبارات، فتنتج حالة لا يستطيع
مستخدم إنتاجي حقيقي امتلاكها. أزيلت إضافات Gate وعاد الاختباران إلى HEAD. يجب
اختيار تصنيف صلاحيات واحد وتحديث FormRequests والسياسات والموارد وSeeder
والمصفوفة معًا، ثم إضافة اختبارات تستخدم أدوارًا وصلاحيات مبذورة فعلًا.

يوجد كذلك مانع object-level مستقل: بدء المحاولة لا يثبت أن
`student_profile_id` يخص المستخدم، ومتحكما submit/grade لا يفوضان على
`AssessmentAttemptPolicy`. لم يُعدّل إنتاج Assessments في هذه الحزمة.

## ما لم يغيره OpenCode

- Guardians وDiscipline وReporting: لا ملفات وظيفية معدلة؛ النتائج المسجلة إعادة
  تشغيل لا أكثر.
- حل Guardians اعتمد على DEFAULT للـ`username` أضافه مالك Identity خارج نطاق H.
- `phpunit.agent-h.xml` ملف محلي مستبعد، وليس أصلًا مطلوبًا إدخاله في Git.

## حالة التحقق بعد المراجعة

- نجحت Audit وAttendance وAcademicReports معًا: **78 اختبارًا و227 توكيدًا**،
  seed `1787410224`.
- بعد إزالة Gates الوهمية، خرج Assessments بـ **27 اختبارًا ناجحًا و6 فاشلة
  و84 توكيدًا**، seed `1787410389`. الإخفاقات الستة كلها 403 في مسارات create،
  list، update، archive، validation وstart-attempt؛ وهذا فشل صادق وليس سببًا
  لإعادة Gates الوهمية.
- الجولة الكاملة للمستودع بعد تجميع الإصلاحات خرجت بـ **686 ناجحًا و75 فاشلًا
  و4502 توكيدًا**، seed `1787412752`. شملت الإخفاقات fixtures خارج H لا تملأ
  أعمدة Sessions/Programs الجديدة، ومخالفات معمارية وصلاحيات غير موحّدة؛ فلا يجوز
  تعميم نتيجة 78 الخضراء على الفرع كله.
- بعد إصلاح صلاحيات Assessments ونطاقات الملكية، تشغّل الموديولات السبعة ثم
  `composer check` واختبارات المعمارية قبل الدمج.

## الملفات التي تبقى ضمن تسليم H

| الملف | الحالة |
|---|---|
| `modules/Audit/database/Factories/AuditLogFactory.php` | منطق المصنع سبق commit؛ تنظيف تعليق غير ملتزم |
| `modules/Audit/tests/Support/RefreshAuditDatabase.php` | حذف override سبق commit؛ إصلاح case غير ملتزم |
| `modules/Attendance/tests/Concerns/CreatesSessionParticipant.php` | سبق commit `8750500` |
| `modules/Attendance/src/Application/Policies/AttendancePolicy.php` | تصحيح غير ملتزم إلى الصلاحيات الرسمية |
| `modules/Attendance/tests/Unit/AttendancePolicyTest.php` | تصحيح غير ملتزم للصلاحيات الرسمية |
| `modules/Attendance/tests/Feature/AttendanceApiTest.php` | تصحيح غير ملتزم للصلاحيات الرسمية |
| `modules/AcademicReports/database/Factories/SessionReportFactory.php` | إضافة غير ملتزمة لـ`original_teacher_id` |
| `docs/agent-tasks/REPORT-H.md` | تقرير مصحح غير ملتزم |

لم ينفذ وكيل H نفسه commit أو push وقت مراجعته، ولم يحذف اختبارًا أو يخفف
توكيدًا؛ جُمعت التغييرات لاحقًا في commits الاعتماد بواسطة مدير المشروع.
