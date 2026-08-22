# Students — ملفات الطلاب

## يملك

`student_profiles`

## ينشر

- لا أحداث مسجّلة له في `docs/09` حاليًا؛ التفاعل معه عبر العقود وQuery Services.

## يعتمد على

- `Identity` (حساب المستخدم).
- يعلن للآخرين: `StudentDirectory` — Query Service يعيد DTOs ولا يسرّب Eloquent Models (docs/08 §2).

## قواعد خاصة

- **لا حذف أبدًا**: الطالب الموقوف أو المجمّد يُغيَّر حالته ويُمنع من الوصول فقط — `SoftDeletes` كحد أدنى.
- `student_code` فريد وهو رقم الطالب المعروض، مع فهرس `(organization_id, student_code)`.
- البحث التقريبي بالاسم عبر فهرس GIN على `to_tsvector`.
