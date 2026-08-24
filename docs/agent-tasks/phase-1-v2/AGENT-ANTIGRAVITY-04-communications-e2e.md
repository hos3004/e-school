# Antigravity — Phase 1 / Task 04

## الإشعارات والتواصل والتكليفات والتقارير والإقفال End-to-End

> **الحالة عند الإنشاء:** Pending Task 03
> **التنفيذ:** في محادثة جديدة بعد اعتماد تقرير Task 03
> **تقرير التسليم:** `docs/agent-tasks/phase-1-v2/REPORT-ANTIGRAVITY-04.md`

## اقرأ قبل أي فعل

1. `AGENTS.md`
2. `PROJECT_MAP.md`
3. `docs/phase-1-approved-scope.md`
4. هذا الملف كاملًا
5. تقارير `REPORT-ANTIGRAVITY-01.md` و`02.md` و`03.md`
6. قسم أحداث الإشعارات والقنوات في `docs/client-answers.md`
7. `docs/06-permissions-matrix.md`
8. `docs/12-notification-architecture.md`
9. `docs/15-security-model.md`
10. `docs/16-testing-strategy.md`
11. `docs/21-definition-of-done.md`

ملفات `AGENT-A`…`AGENT-H` و`20-agent-task-packages.md` ليست أوامر. هذه مهمة تنفيذ
معتمدة: افحص، خطط بإيجاز، نفّذ، واختبر. لا تتوقف عند `implementation_plan.md`.

## شرط البدء

- التقارير الثلاثة السابقة معتمدة ولا يوجد blocker يخفي رحلة أساسية.
- events/contracts من Task 03 معلنة ومستقرة، وبيئة الاختبار المعزولة تعمل.
- لا `git commit` ولا `git push`، ولا تكتب فوق تغييرات وكيل آخر.

## الهدف القابل للتحقق

إكمال التواصل والتعلم، ثم إثبات المرحلة كلها بالمتصفح:

`تسجيل وقبول وتسكين → جدولة وتذكير → BBB وتسجيل وحضور → تقرير وتجميد →
رسالة وشات صف → تكليف وتسليم وتصحيح`

تُعاد الرحلة بأدوار مسموحة ومرفوضة وبمؤسستين، وتصل عينة حقيقية من كل قناة خارجية.

## 1. محرّك الإشعارات والواجهة

- كل حدث معتمد يمر عبر NotificationDispatcher/Outbox، لا إرسال داخل Controller/Action.
- in-app notification له recipient/organization/category/payload آمن وحالة read/unread.
- جرس وعدّاد وصفحة قائمة/تفاصيل/mark read/read all وتفضيلات، دون query explosion.
- القنوات تُختار من إعداد الفئة وتفضيل المستخدم، والحرج يتبع القواعد المعتمدة.
- idempotency يمنع التكرار، quiet hours/timezone والجدولة تعمل، والفشل لا يفشل العملية الأصلية.
- jobs تسجل attempts/provider IDs/error codes/retryability دون secrets أو بيانات حساسة.
- لوحة Admin لحالة Outbox والمحاولات وإعادة الإرسال بصلاحية وتدقيق.

الأحداث الدنيا: registration submitted/approved/rejected، teacher availability approved،
student assigned to group/teacher، session scheduled/rescheduled/approaching/joinable/
cancelled/postponed، teacher apology submitted/approved/rejected، substitute required/
assigned/changed، classroom guest invited، تحذيرا اعتذار المعلم، attendance/absence
warnings، freeze/unfreeze، report due/late، assignment published/submitted/reviewed.

Task 04 يستهلك العقود التي سلّمها Task 02/03 ولا يعيد فتح منطقها إلا إذا أثبت اختبار
عقد وجود عيب؛ أي إصلاح upstream يُسجل بوضوح ولا يغيّر ملكية الحدث.

## 2. البريد التلقائي

- فجوة معروفة يجب التحقق منها: `config/notifications.php` لا يضم Email حاليًا ضمن
  `session_reminder` رغم أن الوثيقة والقَبول يفرضان القنوات الثلاث.
- Reminder قبل الحصة بزمن يضبط من إعداد المؤسسة؛ لا رقم سياسة في الكود.
- إذا لا يوجد بريد صالح تسجل القناة skipped/suppressed وفق العقد ولا تفشل بقية القنوات.
- قوالب ar/en/fr إن كانت اللغة مدعومة، مع timezone محلي ورابط منصة آمن لا BBB secret.
- Laravel Mail/SMTP خلف gateway الموجود، Mailpit/Fake للتطوير والاختبارات.
- اختبار Mail transport حقيقي في بيئة staging/local SMTP؛ `Mail::fake()` وحده لا يكفي للاعتماد.
- bounce/failure/retry يظهر للإدارة دون كشف body حساس في log.

## 3. WhatsApp والأتمتة

- استخدم WhatsApp Cloud API/المزوّد المعتمد خلف Contract؛ لا استدعاء Meta من Controller.
- E.164، template name/language/parameters، approved-template validation قبل queue.
- external message ID وحالات accepted/sent/delivered/read/failed حيث يوفرها المزود.
- webhook signature وidempotency، retry للأخطاء العابرة فقط، إعادة إرسال يدوي بصلاحية.
- أداة Admin/Supervisor لإرسال تنبيه/إعلان بقالب معتمد إلى نطاق مضبوط:
  مؤسسة/دور/برنامج/دورة/مجموعة، مع preview وعدد المستلمين ومنع الإرسال العابر.
- جدولة الإرسال والأتمتة للأحداث المعتمدة، مع rate limits وbatching واحترام إلغاء القناة.
- outbound هو المطلوب؛ الوارد ـ إن استُقبل ـ في صندوق Admin/Supervisor فقط ولا يوجّه للمعلم آليًا.
- اختبار حقيقي بقالب ورقم اختبار عند توفير credentials؛ Fake فقط يبقي التكامل Blocked.
- صِل قالب/حدث استعادة حساب phone-only الذي سلّمه Task 01 بـWhatsApp الحقيقي، واختبر
  الطلب والرمز المنتهي والمستخدم مرتين دون كشف وجود الحساب.

## 4. المراسلات الخاصة والإشراف

- محادثة مباشرة بين الملفات التي تسمح بها Policy، مع المشاركين والمؤسسة والحالة والتوقيت.
- Inbox/thread/new message/read state، pagination، حالات empty/loading/error وترجمات.
- Admin/Supervisor لا يرى أو يتدخل إلا بصلاحية moderation صريحة ومسار audited.
- ولي الأمر ممنوع من محادثة Student↔Teacher الخاصة حتى مع guardian link صحيح.
- منع IDOR عبر thread/message/attachment IDs، ومنع cross-organization.
- escaping/sanitization، server-side validation، rate limiting، report/block/mute إن كان النموذج يدعمها.
- لا حذف صامت لرسائل حساسة؛ الاحتفاظ/الإخفاء وفق السياسة مع Audit عند الإشراف.

## 5. المحادثة الجماعية داخل الصف

- Thread/room واحدة مرتبطة بالمجموعة التشغيلية، لا غرفة عامة للدورة كلها إن تعددت المجموعات.
- أعضاء المجموعة النشطون والمعلم الفعلي/المكلف فقط يقرأون ويكتبون؛ Admin/Supervisor حسب الصلاحية.
- الطالب المنقول أو المجمّد يفقد الوصول المستقبلي وفق السياسة دون محو تاريخ التدقيق.
- system messages للأحداث المهمة عند الحاجة دون كشف بيانات خاصة.
- pagination، unread counts، moderation، rate limiting، وترجمات RTL.
- لا تبنِ بروتوكول Chat مكررًا إذا كان Messaging الحالي يملك البنية.

## 6. التكليفات

- المعلم المكلّف أو الإدارة المصرّحة ينشئ تكليفًا لدورة/مجموعة مع موعد وتعليمات.
- draft/publish/close وما هو معتمد عبر Enum/state transitions، لا strings.
- المرفقات private مع type/size validation وsigned URLs؛ لا ملفات عامة أو تنفيذية.
- الطلاب المستهدفون يرون التكليف ويستطيعون submit/resubmit حسب الإعداد والموعد.
- المعلم يرى تسليمات مجموعته فقط ويضيف feedback/grade أو حالة المراجعة وفق النموذج الحالي.
- الطالب يرى النتيجة الخاصة به فقط؛ Student B لا يرى submission طالب A.
- أحداث نشر/تسليم/تأخير/مراجعة تصل عبر الإشعارات.
- صفحات Teacher/Student/Admin موصولة فعليًا، وليست API أو Resource بلا route.
- لا تفتح Assessments أو Certificates خارج المطلوب.

## 7. التقارير النهائية

- Task 03 هو مالك تنفيذ تقارير الطلاب والمعلمين والمجموعات والحصص والحضور والانضباط.
  لا تعِد بناءها هنا؛ اختبرها E2E وأصلح فقط regression مثبتًا مع تسجيل لمس upstream.
- Task 04 يملك تقرير notification delivery والتكاملات والمحاولات الفاشلة وإعادة الإرسال.
- تحقق أن المرشحات والتصدير لكل التقارير يخضعان للمؤسسة والصلاحية مثل الشاشة، ولا
  joins عابرة للموديولات.
- لا تقارير Payroll/Billing ولا أرقام مالية في التسليم.
- مؤشرات الأداء/الفشل لا تحتوي PII زائدًا أو محتوى رسائل خاصًا.

## 8. End-to-End وبوابة القبول

اكتب Playwright/Feature scenarios فعلية بلا `todo/skip` للرحلة التالية:

1. Admin ينشئ Program وتصنيفًا داخله ودورة مرتبطة ومجموعة بعلاقاتها، ويمنح Supervisor صلاحيات محددة.
2. Teacher وStudent يملكان حسابين مستقلين وواجهتين مختلفتين.
3. زائر يرسل نموذج تسجيل؛ Supervisor يراجعه ويقبله ويسكّنه في المجموعة.
   وتُختبر عينة Import Excel منفصلة للتأكد أنها لا تتجاوز نفس قواعد القبول والتسكين.
4. المعلم/الطالب يضيفان الإتاحة، وتُجدول حصة بلا تعارض.
5. يصل تذكير In-App وEmail وWhatsApp مرة واحدة قبل الحصة.
   ويستعيد حساب phone-only وصوله عبر WhatsApp الحقيقي عند توفر الاعتمادات.
6. Teacher وStudent يدخلان BBB، Supervisor يراقب بصلاحية، وغير المصرّح يُرفض.
7. أحداث join/leave تحسب الحضور، وينتهي التسجيل ويرتبط بالحصة.
8. Teacher يرسل التقرير الفوري؛ معلم آخر لا يستطيع التقرير عن الحصة.
9. إلغاء وتأجيل منفصلان يُنسبان للطرف الصحيح وتظهر تفاصيلهما في التقرير.
10. غيابات الطالب ضمن النافذة ترسل تحذيرات ثم تجمّده وتمنع join؛ إعادة التفعيل لا
    تتم قبل اختبار/تقييم الفريق. ويُختبر منفصلًا طلب تجميد اختياري بموعد عودة.
11. Teacher وStudent يتبادلان رسالة، ومستخدم غير مصرّح/Guardian يُرفض.
12. أعضاء المجموعة يستخدمون شات الصف، وعضو مجموعة أخرى يُرفض.
13. Teacher ينشر تكليفًا؛ Student يسلّمه؛ Teacher يراجعه؛ طالب آخر لا يراه.
14. التقارير تعكس النتائج الصحيحة داخل المؤسسة فقط.

أعد مسارات الرفض بمؤسستين، IDs مباشرة، دور محدود، مدخلات تالفة، وإعادة إرسال webhook/job.

## فحوص الإقفال

- route list وإقلاع كل panel/portal.
- Architecture + table ownership بلا استثناءات جديدة.
- unit/feature/integration/e2e كلها على البيئة المعزولة.
- production build للواجهة، Pint، وPHPStan الموجّه والعام وفق بوابة
  `docs/21-definition-of-done.md`. الخطأ القديم يظل blocker للإقفال ولا يتحول إلى نجاح
  بمجرد تسجيله؛ لا ignores جديدة ولا استثناء موديول لتجميل النتيجة.
- migrate fresh، migrate على بيانات موجودة، rollback ثم fresh دون فقد غير متوقع.
- benchmark نهائي لنفس صفحات Task 01؛ لا regression بعد الإشعارات والشات.
- مراجعة permissions matrix/seeders/Policies وعدم وجود Gates اختبارية وهمية.
- بحث عن secrets/TODO/dead buttons/null URLs/hardcoded UI text/policy numbers.
- فحوص خارجية: BBB + SMTP + WhatsApp عند توفر الاعتمادات، مع حجب الأسرار من الأدلة.
- تجربة يدوية بالعربية RTL على عرض هاتف وسطح مكتب للأجزاء الظاهرة للمستخدم.

## خارج هذه المهمة

- لا مالية، لا Zoom، لا native mobile، لا Assessments/Certificates كاملة.
- لا تغيّر business rule لتجعل E2E أخضر؛ أصلح المنتج أو الاختبار الخاطئ بالدليل.
- لا تعتبر فشلًا قديمًا «خارج النطاق» إذا كسر رحلة المرحلة الأولى أو أمانها.

## التقرير النهائي المطلوب

اكتب `REPORT-ANTIGRAVITY-04.md` ويحتوي:

- مصفوفة كل بند في `docs/phase-1-approved-scope.md`: Verified/Partial/Blocked مع دليل.
- URLs/screens/commands/results لكل رحلة، دون أسرار أو PII.
- نتائج كل test suite بالأعداد والبيئة والـdatabase identifiers الآمنة.
- نتائج BBB/SMTP/WhatsApp الحقيقية منفصلة عن Fake/contract tests.
- الأداء النهائي مقارنة بخط أساس Task 01.
- migrations/rollback/build/static analysis/security checks.
- قائمة مخاطر/ديون صادقة، وتصنيف readiness واحد من المهارة المعتمدة.

لا تستخدم `READY_FOR_PRODUCTION` مع بند Partial/Blocked، أو test skipped، أو تكامل Fake
بعد توفر بياناته، أو تسرب صلاحية/مؤسسة، أو رحلة لا تعمل من الواجهة.
