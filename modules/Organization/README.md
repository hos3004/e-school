# Organization — المؤسسة والإعدادات

## يملك

`organizations` · `academic_calendar` · `holidays` · `countries` · `regions`

## ينشر

- `organization.settings_changed`
- `organization.feature_toggled`

## يعتمد على

لا أحد — طبقة 0. الجميع قد يعتمد على إعداداته ومفاتيح ميزاته دون استيراد كياناته.

## قواعد خاصة

- `settings` و`feature_overrides` هي تجاوزات لكل مؤسسة فوق `config/` — أي رقم سياسة يعيش هنا أو في config، لا في الكود.
- موديول `Billing` كله خلف مفتاح ميزة يُدار من هنا.
- التواريخ تُخزَّن UTC دائمًا وتُعرض بتوقيت المستخدم (`default_timezone`).
- الدولة والتقسيم الإداري يُختاران من البيانات المرجعية المملوكة هنا؛ لا تُقبل قيمة حرة لهما في بيانات الطالب أو الموظف.
