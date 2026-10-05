# Integrations — التكاملات الخارجية

## يملك

`integration_providers` · `integration_connections` · `integration_webhook_deliveries`.

كما يوفّر الموديول عقود ومزوّدي الخدمات الخارجية (الفصل المباشر، البريد، الدفع، واتساب) وفق `docs/11-provider-interfaces.md`.

## ينشر

- لا أحداث باسمه مباشرة؛ الأحداث تنبع من الموديولات المستخدمة للعقود (مثل `classroom.provider_unhealthy` من VirtualClassroom).

## يعتمد على

لا أحد — طبقة 0. الجميع قد يعتمد على عقوده المعلنة.

## Green API (WhatsApp)

- The platform admin configures the instance URL, ID and token at /manage/settings under Integrations. Saving an enabled connection calls GetStateInstance and requires authorized; credentials are encrypted in integration_connections and never returned to the browser or audit log.
- A separate admin action calls SetSettings to register the HTTPS webhook, its Bearer token, incoming messages, outgoing delivery statuses and instance-state changes. This restarts the Green API instance and can take up to five minutes.
- GreenApiConnections is the public contract used by Notifications and Messaging. The legacy .env connection remains a fallback only when no database connection exists. Disabled database connections override that fallback.
- The outbound gateway uses the organization connection. Notifications records Green API message IDs and delivery statuses, while Messaging records authenticated inbound messages idempotently.
- The Green API URL is restricted to official HTTPS hosts. Keep instance tokens in the admin form; do not send them in chat or include them in diagnostics.

## قواعد خاصة

- `GatewayResult::isRetryable` هو وحده الذي يحدد ما تُعاد محاولته (docs/12 §7) — خطأ غير قابل للإعادة يُوسم `failed` فورًا.
- فشل قناة أو مزوّد **لا يلغي** المسارات الأخرى — تدهور رشيق دائمًا.
- مفاتيح بيانات الاعتماد تعيش في البيئة أو في حقل credentials المشفّر، ولا تُسجَّل أبدًا.
