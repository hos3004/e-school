# Scheduling — الجدولة والتأجيل

## يملك

`schedules` · `schedule_weekly_slots` · `postponement_requests`

## ينشر

- `scheduling.schedule_created`
- `scheduling.schedule_changed`
- `scheduling.conflict_detected`
- `scheduling.postponement_requested`
- `scheduling.postponement_alternative_proposed`
- `scheduling.postponement_scheduled`
- `scheduling.postponement_rejected`
- `scheduling.postponement_expired`

## يعتمد على

- `Groups` · `Staff` · `Enrollments` (صلاحية الجدولة).
- `Sessions` عبر أحداثه: يستقبل `enrollments.frozen` ليلغي الحصص المستقبلية، وينشر توليد الحصص إلى Sessions.

## مركز التشغيل الحالي

- `ScheduleResource` ينشئ قالبًا أسبوعيًا لمجموعة أو لطالب فردي، ولا يقبل
  `organization_id` من النموذج. الجدول الفردي يخزن لكل يوم خانة مستقلة تحمل
  ساعة بدايته، بينما تبقى صيغة اليوم/الساعة الواحدة متوافقة مع الجداول القديمة.
- الخيارات متسلسلة: الهدف ← الكورس ← المعلم المؤهل/المسند ← الطالب ذو القيد
  القابل للجدولة.
- `ScheduleMaterializer` يحافظ على الساعة المحلية عند DST ويولّد Sessions
  ومشاركين عبر `SessionSchedulingGateway`؛ لا يكتب في جداول Sessions مباشرة.
- تعديل/إيقاف القالب يحمي نافذة 48 ساعة، ويحوّل الأحداث الأبعد إلى
  `Superseded` بلا أثر مالي قبل إعادة التوليد.
- Hub القالب يعرض الحصص وسجل التدقيق، وكل كتابة تتطلب سببًا.
- التسكين عبر `StudentAssignedToGroup` يزامن الطالب فورًا مع الحصص المستقبلية.
- اعتماد التأجيل ينشئ حصة التلافي وينسخ المشاركين ويؤجل الأصل داخل معاملة واحدة.

## قواعد خاصة (docs/13)

- **المهلات من `config/scheduling.php` — لا أرقام في الكود**: الإلغاء قبل 60 دقيقة، التأجيل قبل 15 دقيقة؛ ما دون المهلة يتحول `NoShow`.
- تعديل `Schedule` لا يمس حصصًا انعقدت أو حُسبت مستحقاتها أو تقع خلال 48 ساعة قادمة.
- التكرار بصيغة RRULE (RFC 5545)؛ الحصص تُولَّد قبل موعدها بـ 60 يومًا مع تخطي العطل.
- حدود التأجيل: 4 طلبات شهريًا للطالب، نافذة إقامة حصة التلافي 30 يومًا، SLA رد المعلم 12 ساعة ثم تصعيد للإدارة (`Expired`).
- **الإلغاء لا ينشئ حصة تلافي إطلاقًا** — التأجيل وحده يقابله تلافي.
- الطالب لا يملك زر إلغاء حاليًا (`features.student_cancellation = false`) — «طلب تأجيل» فقط.
