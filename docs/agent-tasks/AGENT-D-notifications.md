# AGENT D — الإشعارات · البريد · WhatsApp Cloud API

> مدير المشروع (Claude) يحتفظ بـ Sessions/Scheduling/Substitute/BBB.
> **لا تغيّر منطق Sessions إطلاقًا** — تستمع لأحداثه فقط.

## اقرأ أولًا (إلزامي)
1. `CLAUDE.md` — عقد العمل.
2. `docs/client-answers.md` — **قسم `CLIENT UPDATE — 2026-08-22`.** أقسام §ق · §ر · §ش تخصك.
3. `docs/phase-1-critical-modules.md` — جدول «5. الإشعارات» صفوف D1–D12.
4. `docs/12-notification-architecture.md` — المعمارية القائمة.
5. `config/notifications.php`.

## البيئة
```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --filter=<Name>
docker compose exec -T app vendor/bin/pint
```
Mailpit يعمل على `http://localhost:8025` (SMTP داخلي) — استخدمه للتحقق اليدوي.
**~212 اختبارًا فاشلًا سابقًا لا علاقة لها بك — لا تصلحها.**

## ملفاتك حصريًا
- `modules/Notifications/**`
- `modules/Integrations/**`

**بادئة هجراتك الحصرية `2026_08_22_14*`:**
```
modules/Notifications/database/migrations/2026_08_22_140000_create_notification_templates_table.php
modules/Integrations/database/migrations/2026_08_22_140100_add_whatsapp_delivery_fields.php
```

## البنية القائمة — أعد استخدامها ولا تبنِ بديلًا
اقرأ قبل أي كتابة:
- `modules/Notifications/src/Application/Services/OutboxDispatcher.php`
- `modules/Notifications/src/Application/Jobs/SendQueuedNotification.php`
- `modules/Notifications/src/Infrastructure/Gateways/InAppChannelGateway.php`
- `modules/Notifications/src/Infrastructure/Persistence/ConfiguredChannelGateway.php`
- `modules/Integrations/src/Domain/Contracts/ChannelGateway.php`
- `modules/Integrations/src/Domain/ValueObjects/{GatewayMessage,GatewayResult}.php`
- جداول `notification_outbox` · `notification_delivery_attempts` · `notification_preferences`

**ممنوع Scope Creep:** لا موديول جديد · لا Outbox ثانٍ · لا نظام قوالب موازٍ.

---

## D3–D4 · البريد الإلكتروني (متطلب مرحلة أولى)

`modules/Notifications/src/Infrastructure/Gateways/MailChannelGateway.php` ينفّذ `ChannelGateway`:
- يستخدم **Laravel Mail** (`Illuminate\Contracts\Mail\Mailer`) — **مستقل عن المزوّد تمامًا**. لا SDK لمزوّد بعينه.
- Mailable قابل للترجمة يقرأ القالب من جدول القوالب ويحقن البارامترات.
- التطوير: Mailpit/`log`. الإنتاج: SMTP/SES من `.env` بلا تغيير كود.
- يعيد `GatewayResult` بـ`external_message_id` عند توفره.

**D4 حرج — اختبار حقيقي يثبت أن مسار الإشعار يصل إلى Mail transport:**
اختبار Feature يستخدم `Mail::fake()`، يُطلق حدثًا حقيقيًا من المشروع، ويؤكد
`Mail::assertSent(...)` بالمستقبل والموضوع واللغة الصحيحة — **عبر المسار الكامل**
(حدث → dispatcher → outbox → job → gateway → Mail)، لا باستدعاء الـgateway مباشرة.

## D5–D7 · WhatsApp Cloud API (متطلب مرحلة أولى — outbound)

`modules/Integrations/src/Infrastructure/Gateways/WhatsAppCloudGateway.php` ينفّذ `ChannelGateway`:
- Meta Graph API: `POST https://graph.facebook.com/{version}/{phone_number_id}/messages`
- الإعدادات من `config/notifications.php` + `.env`: `WHATSAPP_TOKEN` · `WHATSAPP_PHONE_NUMBER_ID` · `WHATSAPP_API_VERSION`. **لا أسرار في المستودع.**
- **قوالب Meta:** `type: template` مع `name` و`language.code` و`components[].parameters[]` — لا نص حر (Meta ترفضه خارج نافذة 24 ساعة).
- **تطبيع رقم الهاتف (D6):** خدمة `PhoneNumberNormalizer` تحوّل `01001234567` + كود دولة إلى E.164 `+201001234567`. تقرأ كود الدولة من ملف الطالب/المعلم. رقم غير صالح = فشل واضح لا استثناء صامت.
- خزّن `external_message_id` (`messages[0].id`) و`status` و`failure_reason` (رمز خطأ Meta + رسالته).
- استخدم `Http::timeout()` و`retry()` من إعدادات القناة، وميّز بين خطأ قابل لإعادة المحاولة (5xx · 429 · شبكة) وغير قابل (400 · رقم غير صالح · قالب مرفوض) — الجدول فيه بالفعل `retryable` (انظر هجرة `2026_08_22_000002_track_notification_failure_retryability`).

**قاعدة حاكمة: فشل WhatsApp أو Email لا يُفشل العملية الأصلية.** الحصة تبقى محفوظة والتسليم يُعلَّم `failed`. اختبر هذا صراحةً.

## D9 · القوالب متعددة اللغات

`notification_templates`: `id` · `organization_id` nullable · `event_key` · `channel` · `locale` · `subject` nullable · `body` text · `provider_template_name` nullable (اسم قالب Meta) · `parameters` jsonb (أسماء البارامترات المتوقعة) · `is_active` · timestamps · unique(organization_id, event_key, channel, locale)

- `TemplateRenderer` يحلّ `{{placeholder}}` من payload الإشعار.
- سقوط اللغة: لغة المستخدم → `ar` → `en`.
- **بذرة قوالب ar+en لكل حدث من الأحداث الـ21 أدناه، للقنوات الثلاث.**

## D2 · إشعارات داخل النظام
- جرس بعدّاد غير المقروء في لوحة Filament + صفحة قائمة.
- `markAsRead` / `markAllAsRead` عبر مسار محمي بـPolicy.
- بث لحظي عبر Reverb إن كان مُعدًّا (حاوية `reverb` تعمل)؛ وإلا polling.

## D8 · إعادة المحاولة والإرسال اليدوي
- `RetryNotificationAction` موجود — تحقق أنه يحترم `retryable` وbackoff من الإعدادات.
- **إعادة إرسال يدوي للإدارة** من `NotificationOutboxResource` بصلاحية، مع تسجيل من أعاد الإرسال ومتى.
- أمر `RetryFailedNotifications` مجدول.

## D10 · الأحداث الـ21 — القائمة المحدَّثة للمرحلة الأولى
```
registration.submitted            registration.approved           registration.rejected
teacher.availability.approved     student.assigned_to_teacher     student.assigned_to_group
session.scheduled                 session.rescheduled             teacher.apology.submitted
teacher.apology.approved          teacher.apology.rejected        session.substitute.required
session.substitute.assigned       session.substitute.changed      session.approaching
session.joinable                  classroom.guest_invited         teacher.apology.second_warning
teacher.apology.third_escalation  session.report.due              session.report.late
```

لكل حدث: مفتاح · مستقبلوه (student/guardian/teacher/supervisor/admin) · قنواته · قالب ar+en.

**الربط:** Listeners في `modules/Notifications/src/Application/Listeners/` تستمع لأحداث Domain المنشورة من الموديولات الأخرى وتستدعي `QueueNotificationAction`.
- أحداث التسجيل يوفّرها الوكيل A (`Modules\Students\Domain\Events\Registration*`)
- أحداث الحصة والبديل والاعتذار يوفّرها المدير (`Modules\Sessions\Domain\Events\*`)
- إن لم يوجد حدث بعد: **اكتب الـListener والقالب جاهزين وسجّل في تقريرك اسم الحدث الذي تنتظره.** لا تنشئ الحدث في موديول غيرك.

**ممنوع منعًا باتًا:** إرسال مباشر داخل Controller أو Action. كل شيء عبر Outbox.

## D12 · WhatsApp inbound — ليس Blocker
`whatsapp_inbound` موجود في موديول Messaging (**ليس ملكك — لا تعدّله**).
إن كان الربط شبه مجاني: وجّه الوارد إلى صندوق **Admin/Supervisor فقط**.
**ممنوع توجيه ردود WhatsApp تلقائيًا إلى المعلم.** وإلا اتركه وسجّل ذلك.

---

## القواعد الملزمة
- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*`. الاستماع للأحداث فقط.
- **لا أرقام سياسة في الكود** — `config/notifications.php` و`config/scheduling.php`.
- **الترجمات:** كل نص إشعار من القوالب أو ملفات الترجمة. **ممنوع نص عربي أو إنجليزي مكتوب داخل PHP.**
- **الأسرار من `.env` فقط.**
- **UTC** في التخزين.
- `declare(strict_types=1)` · `final` · تعليقات عربية تشرح **لماذا**.

## الاختبارات المطلوبة
- **اختبار Mail حقيقي عبر المسار الكامل** (D4)
- WhatsApp: `Http::fake()` يتحقق من شكل payload القالب واللغة والبارامترات
- تطبيع رقم مصري ورقم سعودي إلى E.164، ورقم غير صالح يفشل بوضوح
- **فشل قناة لا يُفشل العملية الأصلية** — السجل `failed` والكيان الأصلي محفوظ
- خطأ 429 قابل لإعادة المحاولة · خطأ 400 غير قابل
- إعادة الإرسال اليدوي تنشئ محاولة جديدة وتسجّل الفاعل
- سقوط اللغة: لغة غير موجودة → `ar` → `en`

## تعريف «خلصت»
`migrate --force` ينجح · اختباراتك تمر · pint نظيف · لا خرق حدود · رسالة تصل فعليًا إلى Mailpit في التحقق اليدوي.

## التقرير النهائي
`docs/agent-tasks/REPORT-D.md`: ما نُفِّذ · الملفات · الاختبارات ونتائجها **الفعلية** · **أسماء الأحداث التي تنتظرها من وكلاء آخرين** · ما يحتاج credentials حقيقية · ما لم يُنجَز ولماذا.

**لا تدّعِ أن WhatsApp مختبَر على مزوّد حقيقي إلا إذا تم فعلًا بـcredentials حقيقية.**

**لا `git commit` ولا `git push`.**
