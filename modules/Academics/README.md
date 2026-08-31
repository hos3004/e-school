# Academics — البرامج والمستويات والمواد

## يملك

`programs` · `levels` · `courses`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا.
- `AcademicCatalogQueries` يعيد DTOs للبرامج والكورسات إلى واجهات التجميع.
- ينفّذ `RegistrationOfferingQueries` المملوك لموديول Students للتحقق من اختيار البرنامج/الكورس.

## يعتمد على

- `Organization` فقط (طبقة 2 فوق طبقة 0).

## قواعد خاصة

- `program_default` هو **آخر مصدر** في سلسلة استنباط سعر الحصة (`config/payroll.php → rate_resolution`) — يُستخدم فقط إذا لم يحدد عقد المعلم سعرًا.
- `completion_rules` (JSONB) تحكم شروط إكمال الكورس — لا قاعدة إكمال hardcoded في الكود.
- الأسماء والأوصاف متعددة اللغات بصيغة JSONB `{"ar", "en"}`؛ و`code` فريد لكل برنامج وكورس.
