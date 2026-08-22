# تقرير الحزمة D — الإشعارات والبريد وWhatsApp

> **تاريخ التقرير:** 2026-08-22  
> **النطاق:** `Notifications` و`Integrations` والتهيئة/التوثيق المباشر لهما  
> **الحالة:** `NEEDS_REVIEW_BEFORE_PRODUCTION`

## 1. المطلوب

تنفيذ رحلة إشعارات Phase 1 فوق الـOutbox القائم، وتشمل:

- In-App مع غير المقروء وتعليم القراءة وإعادة الإرسال الإدارية.
- بريد مستقل عن المزوّد عبر Laravel Mail، مع تحقق Mailpit.
- WhatsApp Cloud API بقوالب Meta وتطبيع الهواتف وتصنيف الأخطاء.
- قوالب عربية وإنجليزية للأحداث الـ21 والقنوات الثلاث.
- retries/failures/idempotency/delivery logs وربط Domain Events الحقيقية حين تسمح عقودها بذلك.

## 2. ما نُفّذ

### Outbox والتسليم

- نافذة idempotency مدتها 30 دقيقة مع قفل PostgreSQL advisory يمنع السباق دون تعطيل إعادة الإرسال بعد انتهاء النافذة.
- claim ذري للرسائل المستحقة، واحترام `scheduled_for` وساعات الهدوء وbackoff من الإعدادات.
- فصل الفشل القابل لإعادة المحاولة عن الفشل الدائم؛ قرار الإعادة مصدره `GatewayResult::isRetryable()`.
- تسجيل كل محاولة مع النتيجة، و`external_message_id` وحالة المزوّد وسبب الفشل.
- أوامر dispatch/retry مجدولة: المستحق كل دقيقة، والفاشل القابل للإعادة كل 15 دقيقة.
- إعادة إرسال يدوية محمية بـPolicy، مع تسجيل المستخدم المنفذ ووقت الإرسال UTC وإنشاء محاولة جديدة.

### In-App

- API محمي بـ`auth:sanctum` للقائمة والعداد وتعليم إشعار واحد أو الجميع كمقروء.
- عزل القراءة بـ`user_id` و`organization_id`.
- عداد وصفحة Filament وإجراءات القراءة وإعادة الإرسال.
- polling fallback فعّال عبر `GET /api/notifications/unread-count`؛ Reverb غير مربوط في هذه الحزمة.

### البريد

- `MailChannelGateway` ينفذ العقد العام ويستخدم Laravel Mail بلا SDK خاص بمزوّد.
- Mailable وBlade view مترجمان، مع subject/body من القالب والـpayload.
- المسار الكامل Event → Outbox → Job → Gateway → Mail مغطى باختبار.
- تحقق Mailpit يدوي ناجح: وصلت رسالة إلى `agent-d-mailpit@example.test` بعنوان `Session scheduled`، وحالة الـOutbox `sent` والمزوّد `accepted`. أُعيد البريد التجريبي إلى قيمته السابقة بعد التحقق.

### WhatsApp Cloud API

- `WhatsAppCloudGateway` يرسل Meta template payload إلى Graph API، مع اللغة والبارامترات المرتبة.
- تطبيع أرقام مصر والسعودية إلى E.164، ورفض الرقم غير الصالح كفشل دائم واضح.
- timeout/retries من `config/notifications.php`؛ 429 و5xx وأخطاء الشبكة retryable، و400/القالب أو الرقم المرفوض permanent.
- لا أسرار في المستودع؛ token وphone number ID وAPI version من `.env`.
- فشل البريد أو WhatsApp لا يرمي فشلًا إلى العملية الأصلية؛ يبقى كيان العمل محفوظًا ويسجّل الـOutbox الفشل.

### القوالب والأحداث

- جدول `notification_templates` مع override اختياري للمؤسسة، القناة، اللغة، subject/body، اسم قالب Meta، والبارامترات المتوقعة.
- `TemplateRenderer` يتحقق من placeholders ويطبق السقوط: لغة المستخدم ثم `ar` ثم `en`، مع تنسيق التواريخ بتوقيت المستلم.
- بذرة كاملة: 21 حدثًا × 3 قنوات × لغتين = **126 قالبًا**.
- خريطة استقبال للأحداث الـ21، وتسجيل Listeners ديناميكيًا فقط عندما يكون صنف الحدث موجودًا.
- لا استعلامات لجداول موديولات أخرى ولا imports لنماذجها؛ المستقبلون يجب أن يصلوا كـuser IDs داخل payload الحدث.

### قرار ملكية الهجرة

هجرة `140100` تعدّل `notification_outbox` و`notification_delivery_attempts`، وهما ملك `Notifications`. نُقلت لذلك إلى موديول `Notifications` بدل المسار المقترح تحت `Integrations`. فحص `Schema::table` أصبح ناجحًا، وبقي فشل معماري واحد غير متعلق بالحزمة في Sessions موثق أدناه.

## 3. الملفات الرئيسية

- `config/notifications.php` و`.env.example`
- `modules/Notifications/src/Application/Services/OutboxDispatcher.php`
- `modules/Notifications/src/Application/Listeners/QueueConfiguredDomainEventNotification.php`
- `modules/Notifications/src/Application/Services/TemplateRenderer.php`
- `modules/Notifications/src/Infrastructure/Gateways/MailChannelGateway.php`
- `modules/Notifications/src/Infrastructure/Mail/NotificationMail.php`
- `modules/Notifications/src/Presentation/Filament/Resources/NotificationOutboxResource.php`
- `modules/Notifications/routes/api.php`
- `modules/Notifications/database/migrations/2026_08_22_140000_create_notification_templates_table.php`
- `modules/Notifications/database/migrations/2026_08_22_140100_add_whatsapp_delivery_fields.php`
- `modules/Notifications/database/migrations/2026_08_22_140200_add_in_app_read_and_manual_retry_metadata.php`
- `modules/Notifications/database/migrations/2026_08_22_140300_add_manual_retry_actor_foreign_key.php`
- `modules/Notifications/database/Seeders/NotificationTemplateSeeder.php`
- `modules/Integrations/src/Infrastructure/Gateways/WhatsAppCloudGateway.php`
- `modules/Integrations/src/Infrastructure/Gateways/PhoneNumberNormalizer.php`
- `modules/Notifications/tests/Feature/`
- `modules/Integrations/tests/Feature/WhatsAppCloudGatewayTest.php`
- `modules/Integrations/tests/Unit/PhoneNumberNormalizerTest.php`

## 4. التحقق الفعلي

- حزمة Agent D الكاملة على قاعدة PostgreSQL اختبار معزولة بعد جميع التغييرات: **86 passed / 433 assertions**.
- جولة مركزة مستقلة للملفات المتأثرة بإضافة حقول التسليم: **32 passed / 153 assertions**.
- اختبارات In-App/السياسات/الإعادة الموجهة: **20 passed / 84 assertions**.
- WhatsApp: Unit **6 passed**، Feature **5 passed / 37 assertions**.
- Notifications كاملة مع عقود الـgateway في جولة الانحدار السابقة: **59 passed / 250 assertions**.
- Pint على نطاق الحزمة: **116 files PASS**؛ وفحص الهجرة بعد نقلها: **1 file PASS**.
- PHPStan level 6 الموجّه لملفات الإنتاج المعدلة واعتمادياتها المباشرة: **0 errors**.
- `route:list --json`: ناجح.
- `schedule:list`: ناجح وأظهر أمري dispatch/retry بالجدولة المطلوبة.
- rollback/up للهجرات الأربع على قاعدة الاختبار المعزولة: ناجح.
- فحص ملكية الجداول: **2 passed / 1 failed**؛ الفشل الوحيد خارج النطاق موضح أدناه.

ملاحظة: تشغيلات مبكرة على قاعدة `eschool_testing` المشتركة تعرّضت لتعارض `RefreshDatabase` متزامن من وكلاء آخرين وظهرت فيها جداول مفقودة. أُعيدت الاختبارات على قاعدة مستقلة، والنتائج أعلاه هي النتائج المعتمدة.

## 5. الأحداث المنتظرة والعائق التشغيلي

الأصناف الحقيقية الموجودة حاليًا هي:

- `SessionScheduled`
- `SessionPostponed`
- `SessionSubstituteAssigned`

لكن payload لهذه الأحداث يحمل profile/group/participant IDs ولا يحمل user IDs للمستقبلين. لذلك يستقبلها Listener بأمان ويسجل تحذيرًا ولا ينشئ إشعارًا خاطئًا أو يعبر حدود الموديولات. يلزم أن يضيف الموديول المالك حقول المستقبلين المعرفة في `config/notifications.php` حتى تعمل رحلة إنشاء/تعديل الحصة فعليًا.

الأحداث/العقود التي ما زالت غير موجودة وينتظرها الموديول:

- `RegistrationSubmitted`, `RegistrationApproved`, `RegistrationRejected`
- `TeacherAvailabilityApproved`
- `StudentAssignedToTeacher`, `StudentAssignedToGroup`
- `SessionRescheduled`
- `TeacherApologySubmitted`, `TeacherApologyApproved`, `TeacherApologyRejected`
- `SessionSubstituteRequired`, `SessionSubstituteChanged`
- `SessionApproaching`, `SessionJoinable`
- `ClassroomGuestInvited`
- `TeacherApologySecondWarning`, `TeacherApologyThirdEscalation`
- `SessionReportDue`, `SessionReportLate`

هذا يمنع إعلان D4 مكتملة حرفيًا بحدث مشروع حقيقي ذي مستقبل صالح؛ الاختبار الحالي يثبت الرحلة الكاملة بحدث Domain مهيأ، لكنه لا يعوّض إثراء عقد حدث الحصة في الموديول المالك.

## 6. ما تبقى Blocked أو خارج النطاق

- **ربط أحداث الحصة الحقيقية:** blocked حتى تحمل الأحداث user IDs للمستقبلين؛ لم يُعدّل Sessions امتثالًا للتكليف.
- **اختبار Meta الحقيقي:** لم يُنفّذ لعدم وجود credentials حقيقية؛ الاختبارات تستخدم `Http::fake()` فقط.
- **Reverb:** غير منفّذ؛ polling هو fallback العامل والمعلن في الإعدادات والتوثيق.
- **WhatsApp inbound (D12):** لم يُنفّذ لأنه غير blocker وجدوله ملك Messaging، ولم يُسمح بتوسيع النطاق إليه.
- **فحص الملكية العام:** `modules/Sessions/database/migrations/2026_01_10_000001_create_session_substitutions_table.php` ينشئ `session_substitutions` غير المسجل في `tests/Architecture/table_ownership.php`. تعديل Notifications عبر `Schema::table` يمر الآن.

## 7. الدين التقني الذي لم يُصلح

- لم يبدأ أي repo-wide PHPStan cleanup بعد توجيه مدير المشروع.
- القياس العام السابق للموديولات التأسيسية بقي **257** ملاحظة، معظمها typing لـPest/dynamic test context؛ منها Notifications 19 وIntegrations 13. ملفات الإنتاج الخاصة بهذه الحزمة خرجت بصفر.
- لم تُمس أخطاء الأنواع أو Eloquent/Pest في الموديولات غير المرتبطة.
- لا commit ولا push.

## 8. حكم الجاهزية

النواة (Outbox، القوالب، In-App، البريد، WhatsApp outbound، الإعادات وسجلات التسليم) منفذة ومختبرة محليًا. الجاهزية النهائية للإنتاج تحتاج مراجعة وإثراء عقود أحداث الموديولات المالكة، credentials Meta حقيقية، وقرارًا صريحًا بشأن Reverb؛ لذلك التصنيف هو **`NEEDS_REVIEW_BEFORE_PRODUCTION`** وليس جاهزًا 100% للإنتاج.
