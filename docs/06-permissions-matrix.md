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
| `enrollment.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `enrollment.create` | ● | ● | — | ● | — | — | — | — | — |
| `enrollment.pause` | ● | ● | — | ● | — | — | ◐request | ◐request | — |
| `enrollment.freeze` | ● | ● | — | — | — | — | — | — | — |
| **`enrollment.reactivate`** | ● | ● | — | — | — | — | — | — | — |

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
| `session.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `session.create` | ● | ● | — | ● | — | ◐assigned | — | — | — |
| `session.cancel` | ● | ● | — | ● | — | ◐assigned | — | — | — |
| `session.postpone.request` | ● | ● | — | ● | — | ◐assigned | **◐own** | ◐children | — |
| `session.postpone.approve` | ● | ● | — | ● | — | **◐assigned** | — | — | — |
| `session.assign_substitute` | ● | ● | — | ● | — | — | — | — | — |
| `session.join` | ◐ | ◐ | — | — | — | ◐assigned | ◐own | — | — |
| `attendance.view` | ● | ● | ○ | ● | ○ | ◐ | ◐own | ◐children | ○ |
| `attendance.record` | ● | ● | — | — | — | **◐assigned** | — | — | — |
| `attendance.override` | ● | ● | — | — | — | — | — | — | — |
| `recording.view` | ● | ● | — | ○ | — | ◐assigned | ◐enrolled | ◐children | ○ |
| `recording.download` | ● | ● | — | — | — | ◐assigned | — | — | — |
| `recording.delete` | ● | — | — | — | — | — | — | — | — |

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
| `message.moderate` | ● | ● | — | — | ● | — | — | — | — |
| **`messaging.inbound.view`** | ● | ● | — | — | ● | — | — | — | — |
| `announcement.publish` | ● | ● | — | ○ | ● | — | — | — | — |
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
