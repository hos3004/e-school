# Certificates — الشهادات والبادچات

## يملك

`certificate_templates` · `certificates` · `badges` · `badge_awards`

## ينشر

- `certificates.issued`
- `certificates.badge_awarded`

## يعتمد على

- `Students` · `Enrollments` (شروط الإصدار).
- `Notifications` يستقبل أحداثه.

## قواعد خاصة

- جداول هذا الموديول معرَّفة ملكيتها في `docs/08` لكنها **لم تُعرَّف بعد في `docs/07-database-schema.md`** — تُبنى هيجراتها عند تعريفها في المخطط.
- الشهادة تُصدر بعد استيفاء `completion_rules` للكورس/البرنامج — لا إصدار يدوي خارج القواعد.
- كل شهادة مرتبطة بنموذج قالب (`certificate_templates`) — لا تصميم مولّد بالكود.
