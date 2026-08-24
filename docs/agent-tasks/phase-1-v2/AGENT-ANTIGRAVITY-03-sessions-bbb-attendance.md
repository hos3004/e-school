# Antigravity — Phase 1 / Task 03

## الجدولة والحصة وBigBlueButton والتسجيل والحضور والتجميد وتقرير الحصة

> **الحالة عند الإنشاء:** Pending Task 02
> **التنفيذ:** في محادثة جديدة بعد اعتماد تقرير Task 02
> **تقرير التسليم:** `docs/agent-tasks/phase-1-v2/REPORT-ANTIGRAVITY-03.md`

## اقرأ قبل أي فعل

1. `AGENTS.md`
2. `PROJECT_MAP.md`
3. `docs/phase-1-approved-scope.md`
4. هذا الملف كاملًا
5. `docs/agent-tasks/phase-1-v2/REPORT-ANTIGRAVITY-02.md`
6. الأقسام ذات الصلة في `docs/client-answers.md`
7. `docs/05-state-machines.md`
8. `docs/06-permissions-matrix.md`
9. `docs/11-provider-interfaces.md`
10. `docs/13-scheduling-rules.md`
11. `docs/21-definition-of-done.md`

لا تنفّذ تكليفات الحزم التاريخية. هذه المهمة معتمدة: خطة قصيرة، ثم نفّذ دون توقف
لاستئذان جديد إلا لسر خارجي أو قرار قد يسبب ضعف أمان/فقد تسجيل.

## شرط البدء

- Task 02 معتمد، ويوجد في بيئة الاختبار: مؤسستان، مجموعة صحيحة، دورة، معلم مؤهل،
  طالب مقبول ومسكن، وإتاحة للطرفين.
- استخدم بيئة الاختبار المعزولة ولا تشغّل full suite في حاوية التصفح.
- لا `git commit` ولا `git push`، وافحص التغييرات المتزامنة قبل كل patch.

## الهدف القابل للتحقق

تشغيل يوم دراسي كامل:

`جدولة → إنشاء حصة → تذكير event → دخول BBB بصلاحيات → join/leave → تسجيل →
اعتماد حضور → تقرير معلم → إلغاء/تأجيل/اعتذار/بديل → تنبيه وتجميد طالب`

Task 03 يطلق أحداث الإشعار الصحيحة؛ توصيل Email/WhatsApp الكامل يُغلق في Task 04.

الأحداث التي يملك مصدرها في هذه المهمة تشمل: `session.scheduled` و
`session.rescheduled` و`session.cancelled` و`session.postponed`،
`teacher.apology.submitted/approved/rejected`، `session.substitute.required/assigned/changed`،
`session.approaching` و`session.joinable` و`classroom.guest_invited`، تحذيري اعتذار
المعلم، تحذيرات غياب الطالب وfreeze/unfreeze، و`session.report.due/late`.

## 1. الجدولة والحصص

- إنشاء جدول مفرد ومتكرر بـRRULE مع Preview قبل الحفظ.
- UTC في التخزين وعرض timezone المستخدم، مع DST والاستثناءات والإجازات.
- منع تعارض المعلم الفعلي والطالب والمجموعة، وقيد/قفل يمنع السباق المتزامن.
- استيراد المواعيد الحالية من Excel عبر Preview وActions الجدولة نفسها؛ الصف المتعارض
  أو غير الصالح يعطي row error ولا يتجاوز القيود.
- دورة حالات Session Enum وفق state machine؛ لا status strings أو انتقال مباشر.
- original teacher ثابت، actual teacher واضح، وسجل دائم لكل استبدال.
- الإلغاء والتأجيل وحصة التلافي بسبب مكتوب، actor/responsibility وAudit.
- اعتذار المعلم يحتاج اعتماد المشرف؛ اعتماده يفتح البحث عن بديل ولا يلغي الحصة.
- مرشح البديل مؤهل ومتوافق ومتـاح ومن المؤسسة نفسها وبلا تعارض.

## 2. الفصل الداخلي عبر BigBlueButton

- استخدم `VirtualClassroomProvider` وكمّل `BigBlueButtonProvider`; لا Zoom ولا WebRTC مخصص.
- لكل Group صفحة فصل في المنصة، ولكل Session meeting instance مستقل.
- create/join/info/end/recordings/webhook عبر Adapter، ولا يتسرب XML/Guzzle للمجال.
- Join URL يولد Server-side بعد Policy لكل طلب، قصير العمر وغير مخزن كرابط دائم.
- Teacher الفعلي Moderator، Student المسكن Attendee، Supervisor/Admin حسب
  `classroom.observe` و`classroom.moderate`؛ لا role-name bypass.
- الطالب المجمّد أو من مجموعة/مؤسسة أخرى لا يحصل على Join URL.
- دعوة الضيف ـ إن كانت مفعلة ـ token عشوائي لحصة واحدة، expiration/revocation/use limits.
- تحقق توقيع Webhook قبل parsing، وidempotency يمنع تكرار event.
- timeout/retry/circuit breaker/health check وأسرار من البيئة.
- Null/Fake للعقد والـCI، واختبار خارجي حقيقي عند توفر BBB credentials.

## 3. تسجيل الحصص

- التسجيل مفعّل افتراضيًا لكل حصة ويرتبط بـSession ID وBBB recording ID. الإعدادات
  تضبط التشغيل والاحتفاظ ولا تسمح بإطفائه لتجاوز اختبار القبول.
- مزامنة حالة التسجيل بعد انتهاء المعالجة، مع retry/idempotency وفشل مرئي للإدارة.
- لا URL عام. العرض والتنزيل يمران عبر Policy ومنحة وصول ورابط موقع قصير العمر.
- Admin مسموح؛ Supervisor بصلاحية؛ Teacher يرى تسجيل حصته دون تنزيل افتراضيًا؛
  Student/Guardian ممنوعان افتراضيًا.
- RecordingAccessGrant لطالب/مجموعة له انتهاء وإلغاء وAudit.
- الاحتفاظ والأرشفة من config؛ لا حذف النسخة الساخنة قبل إثبات الأرشفة إذا كانت مفعلة.
- لا تعتبر row في قاعدة البيانات تسجيلًا ناجحًا دون قابلية استرجاع metadata/playback المصرح.

## 4. الحضور والغياب والإلغاء والتأجيل

- خزّن كل join/leave لكل مشارك مع provider participant identity وربطه بالحساب الصحيح.
- احسب first_join/last_leave/total_connected/reconnects/late/early_leave والنسبة.
- Join واحد أو اتصال قصير لا يساوي حاضرًا؛ thresholds من config.
- نتيجة المزود provisional، ثم تأكيد المعلم/المشرف للحصة والطلاب.
- أي تعديل بعد الاعتماد يحتاج صلاحية وسببًا وقبل/بعد في Audit.
- احسب للمعلم والطالب كلًا من attendance/absence/cancellation/postponement على الفترة.
- لا تنسب إلغاء الإدارة إلى المعلم أو الطالب؛ خزّن requester/decider/responsible party.
- الصفحات تعرض التفاصيل لا العداد فقط: الحصة والوقت والسبب والقرار والمصدر.

## 5. الانضباط والتجميد

- قواعد الطالب في `config/discipline.php` ونافذة rolling، لا أرقام داخل الكود.
- تنبيه متدرج ثم freeze تلقائي عند threshold المعتمد، مع idempotency لمنع التكرار.
- التجميد يغير الحالة ويمنع وصول الدورة/الحصة ولا يحذف الحساب أو البيانات.
- طلب التجميد الاختياري المؤقت يحمل موعد عودة ومسار اعتماد وإشعار منفصلًا عن العقوبة.
- فك التجميد لا يتم بزر مباشر فقط: يمر باختبار/تقييم الفريق الإداري المعتمد، ثم قرار
  مصرح مع سبب وتدقيق، ولا يمحُ التاريخ. نفّذ الحد الأدنى المحدد لهذا المسار دون فتح
  نطاق Assessments العام.
- متابعة المعلم تنبيه/تحذير/escalation فقط؛ **لا إيقاف أو عقوبة آلية للمعلم**.
- اختبر حافة النافذة المتحركة، timezone، وإعادة احتساب job مرتين.

## 6. تقرير المعلم الفوري والتقارير التشغيلية

- بعد نهاية الحصة يظهر للمعلم نموذج مرتبط بالحصة والطلاب؛ لا URL وهمي/null.
- payload يطابق FormRequest/Action الفعلي، وsession/teacher ownership يتحقق Server-side.
- الحقول المطلوبة، ملخص الحصة، ملاحظات الطلاب، وحالات المتابعة مترجمة ومتحققة.
- المهلة من config (الحالي 60 دقيقة): داخلها on-time وبعدها late مع event واضح.
- Supervisor/Admin يعرضان التقرير ويتابعان التأخر ويطلبان تصحيحًا بصلاحية وتدقيق؛
  لا تضف approve/reject state لتقرير الحصة بلا قاعدة عميل معتمدة.
- تقارير تشغيلية للحصة والمعلم والطالب والمجموعة: الحضور والغياب والإلغاء والتأجيل
  والتجميد والتقارير المتأخرة، عبر Reporting read models/DTOs لا joins عابرة.
- لا تقارير مالية ولا Advanced BI.

## الصفحات الدنيا المطلوبة

- Admin/Supervisor calendar وschedule preview/create/edit.
- sessions list/show/cancel/postpone/reschedule/substitute/apology review.
- Group classroom وSession join/observe/moderate/guest invitation.
- Teacher dashboard/schedule/session/attendance/report/apology.
- Student dashboard/schedule/session/join/attendance/status.
- Attendance review/edit/audit، Discipline freezes/unfreeze/escalations.
- Student temporary-freeze request/return date، ومراجعة طلب إعادة التفعيل/التقييم.
- Recordings list/show/grants/health/failures.
- session/teacher/student/group operational reports.

## خارج هذه المهمة

- لا توصل SMTP/WhatsApp النهائي ولا تبنِ inbox/chat/assignments؛ فقط أطلق events.
- لا تنفّذ مالية أو Zoom أو Assessments كاملة؛ استثناء Assessment هو تقييم فك التجميد فقط.
- لا توسع Guardian أو self-booking خارج ما يحتاجه دخول/خصوصية الحصة.

## اختبارات القبول الإلزامية

1. Preview صحيح ثم إنشاء حصص، ورفض تعارض المعلم/الطالب/المجموعة والسباق المتزامن.
   ويعطي استيراد Excel النتائج نفسها ولا يكرر جدولًا عند إعادة الملف.
2. اعتذار المعلم لا يلغي الحصة؛ البديل المؤهل يصبح الفعلي والأصلي لا يتغير.
3. Teacher/Student/Supervisor يدخلون BBB بالأدوار الصحيحة؛ غير المصرح/المؤسسة الأخرى/frozen يُرفض.
4. Webhook بتوقيع فاسد 401، وإعادته لا تضاعف join/leave/recording.
5. عدة اتصالات وانقطاعات تحسب المدة بدقة، وJoin قصير لا ينتج حضورًا كاذبًا.
6. اعتماد الحضور وتعديله بسبب يظهر في Audit، والعدادات تفصل actor والمسؤولية.
7. غيابات داخل rolling window تجمّد الطالب، والحدث الخارج من النافذة لا يُحتسب،
   وفك التجميد يُرفض قبل استيفاء التقييم الإداري المطلوب.
8. طلب تجميد اختياري يحمل موعد عودة ومسار اعتماد منفصلًا، ولا يزيد عداد المخالفات؛
   والعودة/إعادة التفعيل تتبع الحالة والتقييم المعتمدين.
9. المعلم لا يعاقب آليًا مهما تكرر الاعتذار؛ يظهر escalation للإدارة.
10. التقرير من الواجهة يحفظ داخل المهلة وبعدها late، ومعلم آخر/مؤسسة أخرى يُرفض.
11. تسجيل BBB يعود ويرتبط بالحصة؛ العرض الافتراضي والمنحة والانتهاء/الإلغاء تعمل.
12. اختبار BBB external موثق عند توفر credentials؛ إن لم تتوفر الحالة Blocked لا Complete.
13. Architecture/ownership، migrations/rollback، targeted PHP/UI tests وRTL manual pass.

## التقرير المطلوب

اكتب `REPORT-ANTIGRAVITY-03.md` مع:

- matrix لكل صفحة/Action/Provider flow وحالتها ودليلها.
- أوامر الاختبار، أعداد النتائج، قاعدة الاختبار، ونتيجة external BBB منفصلة.
- IDs/أوقات عينة تثبت meeting/events/attendance/recording/report دون أسرار أو بيانات حساسة.
- events التي يجب على Task 04 توصيلها وقوالب payload المعلنة بعقود، لا Models.
- لكل event: المصدر، وقت الإطلاق، payload schema، idempotency/correlation key، وحالات عدم الإطلاق.
- كل Partial/Blocked، خاصة credentials والتسجيل/الأرشفة.

لا تعلن الإتمام إذا عمل NullProvider فقط، أو كان الحضور يدويًا بلا أحداث BBB، أو كان
التقرير API بلا واجهة، أو كان الطالب المجمّد قادرًا على الانضمام.
