# External integrations checklist

لا توجد بيانات اعتماد staging مصرح بها في هذه الجولة، لذلك لم يُنفذ smoke حي ولم تُكشف أسرار. لا يمنع ذلك الوظائف الداخلية، لكنه يتطلب إكمال البنود قبل تفعيل كل تكامل.

## BigBlueButton / الفصل الافتراضي

- الحالة: العقد ومسار `null` قابلان للتشغيل؛ الاتصال الحي غير مختبر.
- المطلوب: endpoint HTTPS، shared secret، callback URL عام، وسياسة recordings.
- تحقق staging: create/join/end meeting، actor permissions، callback signature، timeout والفشل الآمن.
- قبل التفعيل: ضع `CLASSROOM_PROVIDER=bigbluebutton` والأسرار في secret manager. عند التعطيل استخدم القيمة النصية `"null"`.

## WhatsApp

- الحالة: pipeline والعقود واختبارات الفشل موجودة؛ إرسال/استقبال حي غير مختبر.
- المطلوب: provider account، sender/phone ID، access token، verify token، webhook secret وURL.
- تحقق staging: template approved، outbound delivery، inbound webhook signature، retry/idempotency وopt-out.
- اترك القناة معطلة حتى نجاح smoke وعدم ظهور tokens في logs.

## S3-compatible storage

- الحالة: التخزين المحلي يعمل؛ S3 حي غير مختبر.
- المطلوب: region، bucket، endpoint عند الحاجة، access key/secret، وسياسة IAM محدودة.
- تحقق staging: upload/read/delete lifecycle، signed URLs، CORS، encryption، retention ورفض cross-tenant keys.
- لا تمنح صلاحيات bucket-wide غير المطلوبة، ولا تضع credentials في `.env.example` أو Git.

## بوابة القبول

لكل تكامل: سجل البيئة والوقت والنتيجة دون الأسرار، اختبر success/failure/timeout، راجع logs، ثم فعّل feature/config تدريجيًا. فشل تكامل معتمد للعميل يعيد القرار إلى `CONDITIONAL GO` أو `NO-GO` حسب أثره.
