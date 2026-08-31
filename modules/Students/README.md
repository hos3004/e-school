# Students — ملفات الطلاب

## يملك

`registration_forms` · `registration_questions` · `registration_applications` · `student_profiles`

## ينشر

- `registration.submitted` · `registration.accepted` · `registration.rejected`
- `student.assigned_to_teacher` بعد التسكين الناجح.

## يعتمد على

- `Identity` و`Organization` و`AccessControl` و`Audit` عبر عقودها العامة فقط.
- يعلن للآخرين: `StudentDirectory` و`StudentAdmissionQueries` و`StudentPlacementGateway`.
- يملك `RegistrationOfferingQueries` ويُربط بتنفيذ Academics في طبقة التركيب.

## قواعد خاصة

- **لا حذف أبدًا**: الطالب الموقوف أو المجمّد يُغيَّر حالته ويُمنع من الوصول فقط — `SoftDeletes` كحد أدنى.
- `student_code` فريد وهو رقم الطالب المعروض، مع فهرس `(organization_id, student_code)`.
- البحث التقريبي بالاسم عبر فهرس GIN على `to_tsvector`.
- إنشاء الطالب الإداري يمر بـ`CreateStudentOnboardingAction`: حساب → طلب → تقديم → قبول → ملف → دور، داخل معاملة واحدة.
- كل نموذج تسجيل منشور له `slug` مستقل؛ الطلب يحفظ `registration_form_id` كمصدر ثابت
  ولقطة السؤال والإجابة، لذلك تعديل النموذج لا يعيد كتابة التاريخ.
- منشئ Filament يدير الأسئلة داخل النموذج مع ترتيبها وSoftDelete وتدقيق سبب التغيير.
- التسجيل العام ينتهي عند `submitted` فقط؛ إنشاء ملف الطالب يبقى محصورًا بمسار القبول.
