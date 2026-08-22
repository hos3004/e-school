# Content — مكتبة المواد التعليمية

## يملك

`course_materials`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا.

## يعتمد على

- `Academics` (الكورس المالك للمادة).
- `Enrollments` عبر أحداثه: يستقبل `enrollments.frozen` فيقطع الوصول، و`enrollments.reactivated` فيعيده.

## قواعد خاصة

- الوصول إلى المواد مرهون بحالة القيد: `EnrollmentStatus::grantsCourseAccess()` — الطالب المجمّد لا يرى المحتوى مهما كان الوقت.
- `visible_from` / `visible_to` تحكم ظهور المادة زمنيًا.
- أنواع المواد: `pdf | video | link | worksheet`.
