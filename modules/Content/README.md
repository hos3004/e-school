# Content — مكتبة المواد التعليمية

## يملك

`course_materials` · `course_material_versions`

## ينشر

- `content.material_uploaded`
- `content.material_updated`
- `content.material_removed`

## يعتمد على

- `Academics` (الكورس المالك للمادة).
- `Identity` عبر دليل الحسابات لعرض فاعل كل إصدار.

## قواعد خاصة

- مورد الإدارة محصور بالمؤسسة ويستدعي Actions؛ لا يقبل `organization_id` من الموظف.
- حالة المادة `draft | published | unpublished` عبر Enum، و`visible_from` /
  `visible_to` تضبط النافذة الزمنية بعد النشر.
- كل إنشاء أو تعديل أو نشر يحفظ snapshot جديدًا في `course_material_versions`؛
  الأرشفة SoftDelete ولا تمحو الإصدارات أو الملفات.
- النوعان المدعومان هما `file | link`، والامتدادات والأحجام من `config/content.php`.
- API الإدارة لا يكشف `disk` أو `path`. تنزيل الطالب يجب أن يمر لاحقًا بعقد يتحقق
  من القيد النشط والكورس، ولا يُبنى كرابط عام مباشر.
