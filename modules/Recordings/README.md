# Recordings — التسجيلات والاحتفاظ والأرشفة

## يملك

`recordings` · `recording_views`

## ينشر

- `recordings.ready`
- `recordings.archived`
- `recordings.expired`
- `recordings.deleted`
- `recordings.viewed`

## يعتمد على

- `Sessions` (`sessions.ended` يبدأ المتابعة) · `VirtualClassroom` (`classroom.ended`).
- `Audit` عبر الأحداث: المشاهدة والتنزيل والحذف كلها تُدقَّق.

## قواعد خاصة

- **مدة الاحتفاظ 30 يومًا** من `config/recordings.php` — `expires_at = available_from + retention_days`، ومهمة يومية تفرضها (فهرس جزئي على `expires_at WHERE deleted_at IS NULL`).
- **خصوصية القُصّر**: كل مشاهدة وتنزيل يُسجَّلان في `recording_views` (من، متى، IP) — مطلب غير قابل للتفاوض.
- الحذف دائمًا بسبب مكتوب (`deletion_reason`) مع `deleted_by`، ولا يُحذف الملف قبل تدقيق السجل.
