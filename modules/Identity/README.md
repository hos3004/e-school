# Identity — الحسابات والدخول (موديول مختوم)

## يملك

`users` · `user_devices` · `password_reset_tokens`

## ينشر

- `identity.user_registered`
- `identity.login_succeeded`
- `identity.login_failed`
- `identity.password_changed`
- `identity.two_factor_enabled`
- `UserAccountDirectory` للبحث الإداري المعزول بالمؤسسة وإرجاع `UserAccountData` فقط.
- `UsernameSuggestionGateway` لتوليد أسماء مستخدمين متاحة دون كشف خدمة التطبيق الداخلية.

## يعتمد على

لا أحد — طبقة 0. لا يستورد أحد كياناته إطلاقًا؛ التواصل عبر الأحداث والعقود المعلنة فقط.

## قواعد خاصة

- **مختوم** (`config/modules.php → sealed_domains`): بيانات اعتماد وجلسات — ممنوع أي وصول جانبي، ويُفرض آليًا في `tests/Architecture`.
- الحساب الموقوف لا يُحذف ولا بياناته: `status = suspended` يمنع الدخول فقط (قاعدة «لا حذف»).
- `email` و`username` بنوع `CITEXT` وفريدتان على مستوى المنصة.
