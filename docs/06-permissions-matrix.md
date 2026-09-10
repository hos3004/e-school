# 06 — مصفوفة الصلاحيات

**القاعدة الأولى:** لا يوجد في هذا المشروع سطر واحد يقول
`if ($user->role === 'admin')`. الصلاحية تُفحص بالاسم، لا بالدور.

الدور مجرد **حزمة صلاحيات** قابلة للتعديل من لوحة التحكم. إضافة دور جديد
أو تعديل صلاحيات دور قائم يجب ألا يتطلب نشر كود.

---

## 1. الأدوار

| الدور | الوصف | العدد اليوم |
|-------|-------|-------------|
| `platform_admin` | المدير — يرى كل شيء ويضبط السياسات | 1 |
| `academic_supervisor` | مشرف أكاديمي — المستوى والمعلمون والتقارير | 2+ |
| `finance_supervisor` | مشرف مالي — **يعتمد** المكافآت والخصومات والمستحقات | 1 |
| `registrar` | مسؤول التسجيل — القيود والمجموعات والمواعيد | 1 |
| `communications_officer` | مسؤول التواصل — الرسائل والإعلانات | 1 |
| `teacher` | معلم | 16 |
| `student` | طالب | ~200 |
| `guardian` | ولي أمر | ~100 |
| `auditor` | مراجع — قراءة فقط شاملة، بلا أي تعديل | حسب الحاجة |

**التركيب مسموح:** شخص قد يحمل `teacher` و `guardian` معًا. الصلاحيات تُجمع.

---

## 2. تسمية الصلاحيات

```
<المورد>.<الفعل>[.<النطاق>]
```

**الأفعال القياسية:** `view` · `create` · `update` · `delete` · `approve` · `export`

**النطاقات:**

| النطاق | المعنى |
|--------|--------|
| `.own` | ما يخصه هو فقط |
| `.assigned` | ما أُسند إليه (مجموعات المعلم، أبناء ولي الأمر) |
| `.any` | كل السجلات في المؤسسة |

مثال: `session.update.assigned` = يعدّل حصص مجموعاته فقط.

---

## 3. المصفوفة

**الرموز:** ● كامل · ◐ محدود بالنطاق · ○ قراءة فقط · — ممنوع

### الأشخاص والقيود

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `student.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `student.view.any` | ● | ● | ○ | ● | ○ | — | — | — | ○ |
| `student.create` | ● | — | — | ● | — | — | — | — | — |
| `student.update` | ● | ◐ | — | ● | — | — | ◐own | ◐children | — |
| `guardian.view` | ● | ● | — | ● | ○ | ◐ | — | ◐own | ○ |
| `guardian.link` | ● | — | — | ● | — | — | — | — | — |
| `staff.view` | ● | ● | ○ | ○ | — | ◐own | — | — | ○ |
| `staff.view.any` | ● | ● | ○ | ○ | — | — | — | — | ○ |
| `staff.contract.view` | ● | ○ | ● | — | — | ◐own | — | — | ○ |
| `staff.contract.update` | ● | — | — | — | — | — | — | — | — |
| `staff.availability.create` | ● | — | — | — | — | ◐own | — | — | — |
| `staff.availability.approve` | ● | ● | — | — | — | — | — | — | — |
| `enrollment.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `enrollment.create` | ● | ● | — | ● | — | — | — | — | — |
| `enrollment.pause` | ● | ● | — | ● | — | — | ◐request | ◐request | — |
| `enrollment.freeze` | ● | ● | — | — | — | — | — | — | — |
| **`enrollment.reactivate`** | ● | ● | — | — | — | — | — | — | — |

> تشمل `student.create` إنشاء نماذج التسجيل العامة وتعديل أسئلتها داخل مؤسسة
> المستخدم. النماذج نفسها لا تُحذف؛ تُعطّل للحفاظ على مصدر الطلبات التاريخية.

### الأكاديمي

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `program.manage` | ● | ● | — | — | — | — | — | — | ○ |
| `course.manage` | ● | ● | — | — | — | — | — | — | ○ |
| `group.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `group.manage` | ● | ● | — | ● | — | — | — | — | — |
| `content.view` | ● | ● | — | ○ | — | ◐ | ◐enrolled | ◐children | ○ |
| `content.manage` | ● | ● | — | — | — | ◐assigned | — | — | — |

### التشغيل

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `schedule.view` | ● | ● | — | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `schedule.manage` | ● | ● | — | ● | — | — | — | — | — |
| `schedule.change.request` | ● | ● | — | ● | — | ◐assigned | — | — | — |
| `schedule.change.respond` | — | — | — | — | — | — | **◐own** | — | — |
| `session.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `session.create` | ● | ● | — | ● | — | ◐assigned | — | — | — |
| `session.cancel` | ● | ● | — | ● | — | ◐assigned | — | — | — |
| `session.postpone.request` | ● | ● | — | ● | — | ◐assigned | **◐own** | ◐children | — |
| `session.postpone.approve` | ● | ● | — | ● | — | **◐assigned** | — | — | — |
| `session.assign_substitute` | ● | ● | — | ● | — | — | — | — | — |
| `session.join` | ◐ | ◐ | — | — | — | ◐assigned | ◐own | — | — |
| `classroom.observe` | ● | ◐authorized | — | — | — | — | — | — | — |
| `classroom.moderate` | ● | ◐authorized | — | — | — | — | — | — | — |
| `classroom.guest.invite` | ● | ◐authorized | — | — | — | — | — | — | — |
| `classroom.guest.revoke` | ● | ◐authorized | — | — | — | — | — | — | — |
| `attendance.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `attendance.record` | ● | ● | — | — | — | **◐assigned** | — | — | — |
| `attendance.override` | ● | ● | — | — | — | — | — | — | — |
| `recording.view` | ● | ◐authorized | — | — | — | ◐assigned | ◐grant | — | — |
| `recording.view.any` | ● | ◐authorized | — | — | — | — | — | — | — |
| `recording.download` | ● | ◐authorized | — | — | — | — | — | — | — |
| `recording.grant` | ● | ◐authorized | — | — | — | — | — | — | — |
| `recording.delete` | ● | — | — | — | — | — | — | — | — |

**خصوصية التسجيلات:** `◐grant` تعني منحة وصول نشطة محددة لها انتهاء، وليست صلاحية
افتراضية لدور الطالب. `◐authorized` للمشرف يتطلب منح الصلاحية ونطاق المؤسسة. المعلم
يرى تسجيل حصته فقط ولا ينزله افتراضيًا، وGuardian لا يرث الوصول من رابط القرابة.

### التعلّم والتقارير

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `assignment.manage` | ● | ● | — | — | — | ◐assigned | — | — | ○ |
| `assignment.submit` | — | — | — | — | — | — | **◐own** | — | — |
| `assignment.grade` | ● | ● | — | — | — | ◐assigned | — | — | — |
| `assessment.manage` | ● | ● | — | — | — | ◐assigned | — | — | ○ |
| `assessment.take` | — | — | — | — | — | — | ◐own | — | — |
| `grade.view` | ● | ● | — | ○ | — | ◐assigned | ◐own | ◐children | ○ |
| `session_report.create` | ● | ● | — | — | — | **◐assigned** | — | — | — |
| `session_report.view` | ● | ● | — | ○ | — | ◐own | ◐own | ◐children | ○ |
| `monthly_report.create` | ● | ● | — | — | — | — | — | — | — |
| `monthly_report.approve` | ● | **●** | — | — | — | — | — | — | — |
| `certificate.issue` | ● | ● | — | — | — | ◐assigned | — | — | — |
| `badge.award` | ● | ● | — | — | — | ◐assigned | — | — | — |

### المال

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `payroll.view` | ● | ○ | ● | — | — | **◐own** | — | — | ○ |
| `payroll.calculate` | ● | — | ● | — | — | — | — | — | — |
| `payroll.review` | ● | ○ | ● | — | — | — | — | — | — |
| **`payroll.adjustment.propose`** | ● | **●** | ● | — | — | — | — | — | — |
| **`payroll.adjustment.approve`** | ● | **—** | **●** | — | — | — | — | — | — |
| `payroll.approve` | ● | — | ● | — | — | — | — | — | — |
| `payroll.pay` | ● | — | ● | — | — | — | — | — | — |
| `payroll.lock` | ● | — | ● | — | — | — | — | — | — |

> **الصف الحاسم:** المشرف الأكاديمي **يقترح** التسوية بملحوظة، والمشرف
> المالي **يعتمدها**. هذا تنفيذ حرفي لطلب العميل، ويُفرض بقيد في قاعدة
> البيانات يمنع `proposed_by = approved_by`، لا بتحقق في الواجهة فقط.

### التواصل والنظام

| الصلاحية | admin | acad.sup | fin.sup | registrar | comms | teacher | student | guardian | auditor |
|----------|:-----:|:--------:|:-------:|:---------:|:-----:|:-------:|:-------:|:--------:|:-------:|
| `message.send` | ● | ● | ● | ● | ● | ◐assigned | ◐ | ◐ | — |
| `notifications.outbox.create` | ● | — | — | — | ● | — | — | — | — |
| `message.moderate` | ● | ● | — | — | ● | — | — | — | — |
| **`messaging.inbound.view`** | ● | ● | — | — | ● | — | — | — | — |
| `announcement.publish` | ● | ● | — | ○ | ● | — | — | — | — |
| `popup_campaign.view_any` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.view` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.view_analytics` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.create` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.update` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.publish` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.pause` | ● | — | — | — | ● | — | — | — | — |
| `popup_campaign.archive` | ● | — | — | — | ● | — | — | — | — |
| `class_wall.post` | ● | ● | — | — | — | ◐assigned | ◐own | — | — |
| `report.view` | ● | ● | ◐finance | ◐ops | ○ | ◐own | — | — | ○ |
| `report.export` | ● | ● | ◐finance | ◐ops | — | — | — | — | ● |
| `audit.view` | ● | ○ | ○ | — | — | — | — | — | **●** |
| `settings.manage` | ● | — | — | — | — | — | — | — | — |
| `user.impersonate` | ● | — | — | — | — | — | — | — | — |
| `system.alerts` | ● | ● | — | — | — | — | — | — | — |

---

## 4. صلاحيات ولي الأمر بالتفصيل

نطاق `children` يعني: **فقط الطلاب المرتبطين به عبر `guardian_links` النشطة**.

| ما يراه ولي الأمر | دائمًا |
|-------------------|--------|
| جدول أبنائه وحصصهم | نعم |
| الحضور والغياب والمخالفات | نعم |
| التقارير الأكاديمية والدرجات | نعم |
| التسجيلات المرئية لأبنائه | نعم |
| المدفوعات (عند التفعيل) | نعم |

| ما لا يراه أبدًا |
|------------------|
| محادثات ابنه الخاصة مع المعلم أو الطلاب |
| بيانات أي طالب آخر، ولو في نفس المجموعة |
| ملاحظات المشرف الداخلية على المعلم |
| أي بيان مالي يخص المعلمين |

**النيابة (`can_act_for`):** لمن هم دون 13 سنة، ولي الأمر يستطيع تقديم
طلب تأجيل أو تجميد نيابة عن ابنه. الفعل يُسجَّل في التدقيق باسم
**ولي الأمر** صراحةً، مع الإشارة إلى الطالب المعني.

---

## 5. قواعد التنفيذ

### في الكود

```php
// صحيح
$this->authorize('attendance.record', $session);

// صحيح
Route::post('/sessions/{session}/attendance')
    ->middleware('can:attendance.record,session');

// خطأ — يكسر عقد المشروع
if ($user->hasRole('teacher')) { ... }
```

### Policy لكل مورد

كل كيان له Policy في `src/Application/Policies/` تُسجَّل في مزوّد خدمة
الموديول. الـ Policy هي التي تفهم معنى `own` و `assigned` و `children`.

### إضافة صلاحية جديدة

1. أضف سطرها في هذا الملف.
2. أضفها في `AccessControl` seeder.
3. أضف حالة الاختبار في `tests/Feature` للدور المسموح والممنوع.
4. لا تنشرها بلا الثلاثة.

### التحقق الافتراضي

كل مورد **ممنوع افتراضيًا**. الوصول يحتاج صلاحية صريحة.
`Gate::before` مخصص لـ `platform_admin` فقط، ومع ذلك تُسجَّل أفعاله في التدقيق.

## 6. مسارات اللوحة المستقلة Console v2

جميع `/manage/*` تتطلب ميزة console وتسجيل دخول وحسابًا نشطًا و`admin.panel.access`، إضافةً إلى صلاحية المورد أدناه. لا صلاحيات جديدة ولا فحص باسم الدور.

| المورد / العملية | الحارس الإضافي |
|---|---|
| تقرير اليوم والتقارير | `report.view`؛ ملخص الصفحة لا يُرسل دونها |
| PDF التقرير | `report.view` + `report.export` |
| الطلاب: قراءة / إنشاء / تعديل | `student.view.any` / `student.create` / `student.update` مع Policy المورد والمؤسسة |
| المعلمون: قراءة / إنشاء وتعديل الملف | `staff.view.any` / `staff.contract.update` مع Policy المورد والمؤسسة |
| تعديل اسم/هاتف/توقيت حساب مرتبط | `UserPolicy::update` فوق صلاحية الملف؛ حقول الحساب للقراءة دونها |
| العقود والأسعار في الملف | `staff.contract.view`؛ لا تُرسل للخادم العميل دونها |
| البرامج والمستويات | `program.manage` مع نطاق المؤسسة |
| الكورسات | `course.manage` مع نطاق المؤسسة |
| المجموعات قراءة / كتابة وإسناد وتفعيل | `group.view` / `group.manage` مع Policies والإجراءات القائمة |
| القرآن الفردي قراءة / تسكين واقتراح الإتاحة | `student.view.any` / إضافة `schedule.manage` |
| الإعدادات قراءة / تعديل | `organizations.view` / `organizations.update` للمؤسسة الحالية فقط |

`/learn/*` تتطلب ميزة console وتسجيل الدخول والحساب النشط وملف الطالب/المعلم الخاص. صفحة الحصة تعتمد Policy الحصة، الحضور `attendance.record`، التقرير `session_report.create`، دخول الفصل `session.join`. ملف الطالب للمعلم يتطلب `student.view` وإسنادًا نشطًا مؤسسيًا؛ لا تمنح معرفة المعرّف الوصول. تعديل الحساب الذاتي يمر بطلبات وسياسات الهوية الحالية.

### توسعة إعدادات اللوحة الجديدة

كل المسارات التالية تحتاج الدخول للوحة الجديدة وقراءة المؤسسة، وتتحقق من مؤسسة المستخدم في الخادم:
- بادئة اسم المستخدم: organizations.manage_settings + OrganizationPolicy::manageSettings.
- توجيه أنواع التنبيه: settings.manage + NotificationCategorySettingPolicy.
- عرض التقويمات وإنشاؤها واعتمادها وإغلاقها: academic_calendars.view_any مع الصلاحية الخاصة بالفعل وسياسة التقويم.
- عرض العطلات وإنشاؤها وإزالتها: holidays.view_any مع الصلاحية الخاصة بالفعل وسياسة العطلة. ربط تقويم يتطلب قراءته والتحقق من المؤسسة.

### مركز المتابعة الجديد `/manage/followup`

هذا المركز يركّب الموارد القائمة ولا ينشئ صلاحيات بديلة. يشترط الدخول صلاحيات `admin.panel.access` و`student.view.any` و`enrollment.view` و`attendance.view` و`discipline.view_any`، مع علم تفعيل Console وحساب نشط.

| العملية | الحراسة الإضافية |
|---|---|
| تعليق القيد مؤقتًا | `EnrollmentPolicy::pause` |
| تجميد القيد | `EnrollmentPolicy::freeze` |
| استئناف التعليق | `EnrollmentPolicy::reactivate` |
| تقديم طلب عودة | `EnrollmentPolicy::requestReactivation` و`ReactivationRequestPolicy::create` |
| مراجعة التقييم وحسم الطلب | `EnrollmentPolicy::reactivate` و`ReactivationRequestPolicy::decide` |
| تصحيح الحضور وقبول العذر | `AttendancePolicy::override`، و`ViolationEventPolicy::waive` لكل مخالفة غياب مرتبطة قبل حفظ أي تغيير |

جميع معرّفات الموارد مقيدة بمؤسسة المستخدم، والقرارات تتحقق من الحالة الحالية تحت قفل ومعاملة. لا تمنح صلاحية قراءة المركز أي قدرة كتابة.


### التسجيل والتسكين والمستحقات في Console

| المسار / الفعل | الصلاحيات والقيود |
|---|---|
| نماذج التسجيل والطلبات وقبولها | student.create مع سياسات النماذج والطلب، وتأكيد ربط هوية الحساب الموجود، ونطاق المؤسسة |
| جدول التسكين وفحصه وحفظه | student.view.any + enrollment.create + group.manage؛ مراجعة الأهلية والسعة والمعلم عبر إجراء التسكين الحالي ومعاملة واحدة |
| كشف حصص المعلمين ومستحقاتهم | payroll.view مع تفعيل features.payroll، ومؤسسة المعلم والفترة |
| اقتراح مكافأة أو تسوية | payroll.view + الصلاحية المحددة في payroll.adjustments.propose_permission وسياسة التسوية |
| اعتماد أو رفض التسوية | payroll.view + payroll.adjustments.approve_permission؛ فصل المقترح عن المعتمد وحماية الفترة المدفوعة |

لا تمنح هذه الصفحات صلاحية صرف أو تغيير حالة الفترة المالية. التصحيح قيد مستقل، والقيمة التاريخية للحصة لا تتغير بتغيير السعر الحالي.

### تقويم الحصص والجداول والقرآن والإتاحة في Console

هذه واجهات للموارد الحالية، دون صلاحيات أو أدوار جديدة:

| المسار / العملية | الصلاحيات والسياسة والنطاق |
|---|---|
| GET /manage/sessions | session.view + student.view.any؛ حصص ومشاركو مؤسسة المستخدم فقط، وتوقيت الحساب وبداية أسبوع المؤسسة |
| جداول المجموعة داخل التقويم | schedule.view + SchedulePolicy::viewAny؛ القوالب الجماعية للمؤسسة فقط |
| GET /manage/schedules/create وPOST /manage/schedules | schedule.manage + SchedulePolicy::create؛ تحقق وجهة المجموعة والكورس والمعلم والإسناد في CreateScheduleAction |
| GET /manage/schedules/{schedule}/edit وPATCH /manage/schedules/{schedule} | schedule.manage + SchedulePolicy::update؛ جدول جماعي من المؤسسة، قفل قبل التعديل، ومنع تبديل المجموعة أو الكورس أثناء تحريره |
| POST /learn/teacher/schedules/{schedule}/change-requests | `schedule.change.request` + ScheduleChangeRequestPolicy::create؛ RequestScheduleChange يتحقق أن القالب نشط ومسند لنفس المعلم، ويصنع صف قبول لكل طالب نشط في الكورس. لا يتغير الجدول عند الطلب. |
| POST /learn/student/schedule-changes/{change}/respond | `schedule.change.respond` + ScheduleChangeRequestPolicy::respond؛ الرد مقصور على صف القبول المعلّق لهذا الطالب. رفض واحد ينهي الطلب، واكتمال القبول يطبّق الموعد عبر UpdateScheduleAction. |
| POST /learn/teacher/schedule-changes/{change}/withdraw | `schedule.change.request` أو `schedule.manage` + ScheduleChangeRequestPolicy::withdraw؛ ومطابقة staff_profile_id لصاحب الطلب. |
| GET /manage/schedules/availability | schedule.manage؛ مجموعة وكورس ومعلم صالحون من المؤسسة، واستثناء جدول موجود يتطلب SchedulePolicy::update وتطابق الوجهة |
| PATCH /manage/quran/{student}/schedules/{schedule} | student.view.any + schedule.manage + SchedulePolicy::update؛ تطابق الطالب والكورس الفردي والمؤسسة وحساب الطالب النشط |
| تسكين طلب قرآن مقبول جديد | student.view.any + schedule.manage + enrollment.create + RegistrationApplicationPolicy::scheduleIndividual؛ قفل الطلب المختار، تحقق الأهلية والحالة، معاملة واحدة للقيد والجدول وحالة الطلب |
| GET /manage/teachers/{teacher}/availability | staff.view + StaffProfilePolicy::view؛ ملف المعلم المسموح من المؤسسة فقط |
| إضافة نافذة إتاحة | staff.view + staff.availability.create وسياسة إضافة إتاحة الملف؛ المعلم وحسابه نشطان |
| اعتماد/رفض نافذة إتاحة | staff.view + staff.availability.approve + TeacherAvailabilityPolicy::approve؛ نافذة المعلم المحدد والحالة المسموحة |
| إزالة نافذة إتاحة | staff.view + TeacherAvailabilityPolicy::delete؛ إداري المؤسسة يحتاج staff.contract.update، وصاحب النافذة يحتاج staff.availability.create عند تعطيل المراجعة؛ لا تتغير الحصص المحجوزة |

لا تنفذ واجهة التقويم تعديلًا مباشرًا على حصة أو حضور أو دفتر مستحقات. إجراء UpdateScheduleAction يحافظ على الماضي والمهلة المحمية، ويعيد توليد المستقبل داخل معاملة وتدقيق. معرفة معرّف مورد أو إرساله من المتصفح لا تمنح الوصول إلى مؤسسة أخرى.


### استكمال الملف وأجر الحصة — 2026-09-09

- مسارات /profile/complete: صاحب الحساب فقط عبر UserPolicy::update، وبوجود ملف طالب أو معلم داخل مؤسسته. لا يقبل معرّف مستخدم من الطلب.
- قراءة وتعديل جدول مدد وأجور الحصص في الإعدادات: organizations.manage_settings وOrganizationPolicy::manageSettings، مع سبب مكتوب وفحص نسخة متزامنة وسجل تدقيق.
- الحساب ذو الملف غير المكتمل يُمنع من صفحات الموقع وواجهات API؛ استكمال الملف والخروج وتغيير اللغة متاحة فقط حتى يؤكد البيانات.

Pending teaching assignments: administrative read uses schedule.view; changes use schedule.manage scoped to organization. Teacher learning roster exposes only assigned students via the Scheduling public DTO query. Links are soft-deleted and audited; no lesson time is fabricated.

Teacher financial visibility: console teacher profile PUT /manage/teachers/{profile}/financial-visibility requires admin.panel.access, staff.contract.update and organization-scoped StaffProfilePolicy::update, with reason and audit. Hidden teacher accounts are denied payroll.view, payroll.export and staff.contract.view; admin.panel.access holders retain their authorized administrative financial access. No new role or permission is introduced.
