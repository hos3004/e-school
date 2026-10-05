# تقرير إغلاق موانع الإصدار — E-School

التاريخ: 2026-09-01  
المرشح: `/home/gamer/e-school-release-safety/20260831T184044Z/candidate/e-school`  
الفرع: `codex/postparallel-integration-20260831`  
HEAD البرمجي الذي اختُبرت عليه الحزمة الكاملة: `d4b45a6`  
القرار: **NO-GO**

## 1. الخلاصة التنفيذية

أُغلقت الإخفاقات الاثنا عشر الموثقة، وأُغلقت موانع التفويض الحرجة في Sessions وPayroll وRecordings وMessaging باختبارات سلوك إيجابية وسلبية. نجحت الحزمة الكاملة على بذرة الترتيب التي كشفت آخر خلل في استقلال الاختبارات: 1099 اختبارًا و6636 assertion بلا فشل.

لا يزال الإصدار ممنوعًا لأن PHPStan الكامل يفشل بـ1396 ملف-error، ولأن Playwright critical smoke لم يُنفذ. لا توجد policy معتمدة تسمح baseline واسع، ولذلك لا يمكن تحويل النتيجة إلى CONDITIONAL GO.

## 2. حاجز الأمان ومصدر الحقيقة

- HEAD قبل الجولة: `1dd418822f10d2cf84e79023f43585f511cb1378`، والشجرة كانت نظيفة.
- أُنشئ فرع رجوع محلي: `codex/release-closure-safety-20260831-1dd4188`.
- لم تُلمس `/home/gamer/e-school` ولا `I:\e-school`، ولم يحدث push أو merge أو deployment.
- `e-school-full.tar`: تطابق SHA-256 مع `7ffabd3de3a9e8a5c53b60e152743ea2c045406f79d9fabb2c3739b46769959f`.
- `local-development.postgres.dump`: تطابق SHA-256 مع `174a5488d9242a79366ad2bf0fa726bdacdd5b3c950f167eb64ab1ed46d6621a`.
- نجح `pg_restore --list`، وعدد عناصر الفهرس 788.
- كل اختبارات PostgreSQL استعملت قواعد مؤقتة داخل شبكة/volume معزولين. حُذفت قاعدتا fresh وrestore المؤقتتان بعد التحقق؛ لم تُمس قاعدة المصدر أو dump.

## 3. إعادة إنتاج خط الأساس

مرجع التقرير 08 كان: 1053 passed، 12 failed، 6394 assertions، 160.09s. أعيد إنتاج مجموعات الفشل منفردة ومجمعة قبل الإصلاح.

- Pint: 15 مخالفة في 2638 ملفًا في baseline.
- ESLint 9: لا يوجد flat config صالح.
- PHP lint: لم تتكرر نتيجة 27 ملفًا التاريخية؛ الفحص الفعلي وجد صفر أخطاء syntax.
- PHPStan machine-readable: 1341 file-errors أوليًا. أكبر الأنماط: `property.notFound` (548)، `method.notFound` (369)، `missingType.generics` (183)، `missingType.parameter` (58)، و`missingType.iterableValue` (42).

## 4. الإخفاقات الاثنا عشر

| # | النطاق | السبب الجذري | الإصلاح والإثبات | الحالة |
|---:|---|---|---|---|
| 1 | Assessments update | fixture لا يطابق FormRequest وحالة دورة الحياة | تصحيح fixture دون إرخاء validation، وإضافة valid/invalid/immutable/unauthorized/foreign-tenant cases | مغلق |
| 2 | Attempt start | Policy لا يثبت scope المعلم/التسجيل والحالة بدقة | `AssessmentManagementScope` وPolicy موحدان؛ 401/403/success/foreign/lifecycle مغطاة | مغلق |
| 3 | Attempt submit | actor والpermission/lifecycle في fixture غير متسقة | صلاحيات canonical وعلاقات tenant/enrollment صريحة | مغلق |
| 4 | Attempt grade | grader scope غير مثبت وكان ينتج 403 | assigned-teacher scope واختبارات منع actor الأجنبي | مغلق |
| 5 | Messaging guardian privacy | علاقة القرابة كانت تُعامل كحق قراءة لمحادثة الطالب الخاصة | ولي الأمر يحتاج participant مباشرًا؛ same/foreign tenant والابن المرتبط وغير المرتبط مغطاة | مغلق |
| 6 | `GET /api/conversations` | route list الحقيقي غير مسجل، فكانت الاستجابة 405 | route محمي بـ`auth:sanctum` وcontroller مع participant/tenant scope؛ guest=401 | مغلق |
| 7 | NotificationBell | fixtures/references قديمة إلى نموذج User محذوف | تحديث الاختبارات والمراجع إلى Identity المعتمد، وإثبات رحلة الجرس | مغلق |
| 8 | Notifications import | import قديم خارج العقد الحالي | إزالة المرجع القديم والمحافظة على حدود الموديولات | مغلق |
| 9 | Public registration legacy/published | الاختبار يفترض form منشورًا دون fixture | fixtures منشورة صريحة وحفظ عقد legacy من دون Seeder إنتاجي اصطناعي | مغلق |
| 10 | Public registration no-form | الخلط بين غياب form وفشل النظام المرن | اختبار مستقل لحالة no published form، وإعادة حزمة Students المحمية | مغلق |
| 11 | AccessControl 94/102 | عداد هش لم يعد يطابق قاموس الصلاحيات المستهلكة | قائمة source-of-truth وترجمات ومستهلكون واضحون؛ 102 permission و9 roles بعد seed | مغلق |
| 12 | BigBlueButtonOnlySeeder | Integration child قد يشير إلى organization غير موجودة | parent صالح وidempotency؛ التشغيل منفردًا وبعد fresh seed ومرتين | مغلق |

نتيجة المجموعة المجمعة للإخفاقات مع مصفوفات الأمن: **63 passed، 322 assertions، 14.61s**.

## 5. موانع P0 الأمنية

| النطاق | ما أُغلق | دليل السلوك | الحالة |
|---|---|---|---|
| Sessions | decision point موحد لـassigned/own/children، وفصل read scope عن mutation، ومنع المعلم غير المسند والمراجع والمالية من التعديل | `SessionOperationsHubTest`: guest/unauthorized/same-tenant/foreign-tenant وassigned teacher | مغلق |
| Payroll | tenant-scoped lookup داخل transaction، same-tenant references، locks، canonical permissions، actor/before/after/reason | `PayrollApiAuthorizationTest`: view/propose/approve/reject ومؤسسة أجنبية | مغلق |
| Recordings | decision point لـlist/show/log/download، Ready وغير منتهٍ، assigned teacher، active relation/grant، وصلاحية download مستقلة، ولا audit قبل السماح | `RecordingApiAuthorizationTest` و`RecordingPolicyTest` بما في ذلك Processing/expired/foreign | مغلق |
| Messaging | participant وtenant scope ومنع guardian privacy escalation | `ConversationListAuthorizationTest` و`GuardianPrivacyAuthorizationTest` | مغلق |
| API auth | 401 للضيف، 403 بلا صلاحية، نجاح actor الصحيح، ومنع foreign tenant | مصفوفات Feature المذكورة أعلاه؛ security targeted النهائي 11 passed/97 assertions | مغلق |

لم يُضف Gate bypass، ولم يوضع auth على webhook العام، ولم تُخفف حدود الموديولات. أُزيل كذلك import مباشر لـ`EnrollmentStatus` من Recordings واستُخدم DTO العام.

## 6. الوكلاء والملكية

- `assessments_access`: Assessments وAttempt policies وAccessControl. راجع بعد ذلك الأمن واكتشف أربعة findings: mutation بصلاحية قراءة، list لحالات Recording غير قابلة للعرض، expiry، وعدم تطابق صلاحيات Payroll.
- `messaging_regressions`: Messaging/Guardians وNotifications وlegacy registration وBBB. راجع القائد أي corruption نصي وقع أثناء الدمج وأصلحه قبل الاختبار.
- `security_p0`: Sessions وPayroll وRecordings. عالج أيضًا findings المراجعة الأربعة وأزال مخالفة حدود الموديولات.
- القائد: حاجز الأمان، الدمج، الملفات المشتركة، ESLint/lockfile، Pint/PHP lint/PHPStan، DB drills، الحزمة الكاملة والتقرير.

## 7. التحقق من الشرائح المحمية

- Reporting + Students full protected scope: **124 passed، 387 assertions، 38.21s**.
- Architecture وملكية الجداول: **87 passed، 2459 assertions، 16.31s**.
- لم يُعد تصميم Operational Reports أو Registration Forms. اقتصرت تعديلات Students على regressions مثبتة في legacy fixtures/acceptance.

## 8. بوابات الجودة والاختبار

| البوابة | الأمر/النطاق | النتيجة | المدة/الأرقام | SHA البرمجي |
|---|---|---|---|---|
| targeted 12 + security | Pest files المستهدفة | PASS | 63/322، 14.61s | حتى `d4b45a6` |
| full suite، seed 1788211200 | `php scripts/test-isolated.php --order-by=random --random-order-seed=1788211200 --compact` | PASS | 1099/6636، 170.57s | `d4b45a6` |
| Architecture | `tests/Architecture` مع memory 1G | PASS | 87/2459، 16.31s | حتى `d4b45a6` |
| Pint | full `pint --test` | PASS | 2647 files | حتى `d4b45a6` |
| PHP syntax lint | app/bootstrap/config/database/modules/routes/shared/tests | PASS | 2644 PHP files، 0 failures | حتى `d4b45a6` |
| PHPStan changed production scope | الملفات الإنتاجية المعدلة | PASS | 48 files، 0 results | حتى `d4b45a6` |
| PHPStan full | machine-readable full analysis | **FAIL** | 1396 file-errors، 0 general errors | حتى `d4b45a6` |
| TypeScript | `npm run types` | PASS | 0 errors | حتى `d4b45a6` |
| ESLint | `npm run lint` | PASS | ينتهي طبيعيًا | حتى `d4b45a6` |
| Vite | `npm run build` | PASS | 822 modules، 5.41s | حتى `d4b45a6` |
| Composer validate/audit | validate strict + audit | PASS | 0 advisories | حتى `d4b45a6` |
| npm audit | `npm audit` | **FAIL (moderate)** | ECharts XSS `GHSA-fgmj-fm8m-jvvx`؛ الإصلاح المتاح major 6.1.0 | حتى `d4b45a6` |

أُنشئ `eslint.config.mjs` باستخدام ESLint 9 وTypeScript/React Hooks، ولم تُستبعد مجلدات source. لم يُنشأ PHPStan baseline ولم تُرفع ignore counts أو blanket exclusions.

## 9. قاعدة البيانات والاستعادة

- قاعدة جديدة `eschool_release_fresh_20260901`: نجح `migrate:fresh --seed --force`، وأسفر AccessControl عن 102 permission و9 roles.
- `IntegrationsSeeder` منفردًا مرتين: PASS، 11ms لكل تشغيل.
- قاعدة restore جديدة `eschool_release_restore_20260901`: نجح restore للـdump المتحقق منه، ثم نجح `migrate --force` وطبّق migration اللاحقة `2026_08_31_160000_create_registration_forms_table` في 31.16ms.
- حُذفت القاعدتان المؤقتتان بعد التحقق بأسماء صريحة؛ الحذف نهائي لكنه يخص بيانات اختبار disposable فقط.

## 10. Playwright

لم يبدأ Playwright قبل تصفير targeted/full كما اشترطت الخطة. بعد نجاح Pest بقي PHPStan الكامل أحمر، كما لم تكن بيئة المتصفح/التطبيق الحرجة مثبتة على بوابة واحدة؛ لذلك لم يُدع نجاح E2E. journeys المطلوبة (cross-tenant security، guardian privacy، public forms، operational reports/PDF) تظل بوابة إلزامية غير منفذة، وهذا وحده يفرض NO-GO.

## 11. commits المحلية

- `63841e1 fix(assessments): close authorization regressions`
- `934eeb9 fix(comms): enforce tenant privacy and restore regressions`
- `c9aada2 fix(security): scope session payroll and recording access`
- `81b4b5c chore(quality): clear formatter and eslint blockers`
- `d4b45a6 test(infrastructure): isolate database-backed unit tests`

لم يحدث push أو merge أو deployment. حُذفت نسختان `.msys*` متطابقتان ومتتبعتان من مجلدي Content وGroups؛ يمكن استعادتهما من Git لكنهما artifacts غير صالحة كانت تلوّث lint.

## 12. المتبقي وخطة الإغلاق

| المانع | الخطورة/المالك | الخطوة الدقيقة |
|---|---|---|
| PHPStan full: 1396 file-errors | Release/Module owners | تقسيم buckets حسب module، البدء بـproperty/method notFound، إصلاحها دون baseline، ثم full analysis إلى صفر |
| Playwright critical smoke غير منفذ | QA/Release owner | تشغيل التطبيق وقاعدة disposable، تنفيذ journeys السبعة وخادم-side assertions، وحفظ traces/screenshots |
| reactivation request validation | Discipline + Assessments owners | إعلان Public Contract في Discipline للتحقق من `reactivation_request_id` ثم استهلاكه دون استيراد Model أو table عابر | 
| ECharts moderate advisory | Frontend owner | اختبار ترقية مخطط لها إلى ECharts 6.1.0 في جولة مستقلة لأنها major، مع visual/chart regression | 
| Conversation list scalability | Messaging owner (P2) | استبدال تجميع allowed IDs بمسار pagination/query أكثر كفاءة مع نفس privacy contract |

## 13. الحكم

**NO-GO**. السبب القابل للتحقق: PHPStan الكامل يفشل بـ1396 file-errors وPlaywright critical smoke غير منفذ. الإصدار لا يصبح مؤهلًا حتى تنجح البوابات الست عشرة على مرشح واحد نظيف، بصرف النظر عن نجاح Pest وPint وESLint والبناء وقواعد البيانات.
