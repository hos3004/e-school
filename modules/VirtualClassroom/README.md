# VirtualClassroom — الفصل المباشر

## يملك

`classrooms` · `classroom_events`

## ينشر

- `classroom.created`
- `classroom.started`
- `classroom.participant_joined`
- `classroom.participant_left`
- `classroom.ended`
- `classroom.provider_unhealthy` — تنبيه فوري للإدارة

## يعتمد على

- `Sessions` (`sessions.scheduled` لإنشاء الفصل، و`classroom.started/ended` تعود إليه).
- `Integrations` — عقد مزوّد الفصل (BigBlueButton افتراضيًا) في `Domain/Contracts/VirtualClassroomProvider`.

## قواعد خاصة

- **الفصل يُنشأ عند المزوّد قبل الموعد بـ 20 دقيقة** لا عند ضغط الطالب — بطء المزوّد لا يتحول لتأخير الحصة (docs/13 §6).
- نوافذ الدخول: المعلم/المشرف من -20 دقيقة، الطالب من -10 دقائق حتى +15 دقيقة بعد البداية.
- الطالب المجمّد قيده لا يدخل الفصل مهما كان الوقت.
- نقطة POST /api/webhooks/classroom عامة للمزوّد فقط: توقيع BBB يُتحقق منه قبل قراءة الحدث، ويُحدّ معدلها من config/virtual-classroom.php.
- شغّل classroom:smoke-test بعد وضع بيانات BBB في البيئة؛ والفحص الحي لا يكتب بيانات في المنصة ولا يطبع الأسرار. تسجيل webhook عبر classroom:webhook يحتاج تثبيت bbb-webhooks عند خادم BBB.
- `classroom_events` هو مصدر احتساب الحضور الآلي — فهرس `(classroom_id, occurred_at)` إلزامي.
