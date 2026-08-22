# Messaging — المراسلات وحائط الصف وواتساب الوارد

## يملك

`conversations` · `conversation_participants` · `messages` · `class_wall_posts` · `class_wall_comments` · `whatsapp_inbound`

## ينشر

- `messaging.message_sent`
- `messaging.message_flagged`
- `messaging.whatsapp_inbound_received`

## يعتمد على

- `Identity` (المشاركون والمرسلون).
- `Notifications` يستقبل أحداثه (تنبيه رسالة جديدة، إشراف، واتساب وارد).

## قواعد خاصة

- **الإشراف افتراضي**: `is_moderated = TRUE` — الرسائل الموسومة تُراجع قبل الاستمرار (`moderated_by/at`).
- **واتساب الوارد يُخزَّن ولا يُوجَّه آليًا إطلاقًا** — يراه الأدمن والمشرف فقط عبر صلاحية `messaging.inbound.view`.
- فهرس `(conversation_id, created_at DESC)` إلزامي لعرض المحادثة بترتيبها.
