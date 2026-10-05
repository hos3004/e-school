# AccessControl — الأدوار والصلاحيات

## يملك

`roles` · `permissions` · `role_has_permissions` · `model_has_roles`

(على أساس `spatie/laravel-permission` مع إضافة `organization_id` و`module`)

## ينشر

- `access_control.role_assigned`
- `access_control.permission_changed`
- `RoleAssignmentGateway` لإسناد دور مسمى بطريقة idempotent من منسقات الطبقات الأعلى.

## يعتمد على

لا أحد — طبقة 0. يستهلكه كل الموديولات عبر `Policies` و`can:` middleware فقط.

## قواعد خاصة

- **ممنوع** `if ($user->role === 'admin')` في أي مكان — الصلاحيات حصرًا حسب مصفوفة `docs/06-permissions-matrix.md`.
- `is_system = true` يمنع حذف الأدوار الأساسية للنظام.
- أي تغيير في الأدوار أو الصلاحيات يُدقَّق في `audit_log` (مستمعو `Audit`).
