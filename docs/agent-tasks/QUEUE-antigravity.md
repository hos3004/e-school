# طابور التنفيذ — Antigravity

> **أنت الآن المنفّذ الرئيسي للمشروع.** وكلاء OpenCode أُوقفوا لأنهم لم ينتجوا أي ملف.
> نفّذ الحزم أدناه **واحدة تلو الأخرى بالترتيب**، ولا تبدأ حزمة قبل إغلاق سابقتها بتقريرها.

## من يعمل معك الآن — لا تلمس ملفاتهم

| المنفّذ | يملك حصريًا | الحالة |
|---------|--------------|--------|
| **Codex** | `modules/Notifications/**` · `modules/Integrations/**` | **يعمل الآن** — ممنوع الاقتراب |
| **مدير المشروع (Claude)** | `modules/Sessions/src/{Domain,Application}` · `modules/Scheduling/src/{Domain,Application}` · `modules/VirtualClassroom/**` · `modules/Recordings/src/{Domain,Application}` | يعمل الآن |
| **أنت** | كل ما عدا ذلك، حسب كل حزمة | — |

`modules/Sessions/src/Presentation/Filament/**` **لك أنت** (أنجزته بالفعل).
`modules/Sessions/src/Domain` و`Application` **ليست لك**.

## الترتيب الإلزامي

### ١. الحزمة F — الصلاحيات والخصوصية ← **ابدأ بها**
`docs/agent-tasks/AGENT-F-permissions-privacy.md`

**هذه أعلى أولوية في المشروع كله.** لوحة الإدارة ترجع `403` على **كل** مورد،
فلا يستطيع أحد — لا أنت ولا مدير المشروع — التحقق يدويًا من أي ميزة.

في آخر الملف **تشخيص جاهز مؤكَّد بالفحص**: الـPolicies تفحص `students.view_any`
والموجود فعلًا `student.view`. ابدأ من هناك مباشرة.

بعد إغلاقها يجب أن يفتح `http://localhost:8090/admin/students` فعليًا
بحساب `admin@eschool.test` / `password`.

### ٢. الحزمة A — الطلاب والتسجيل والجغرافيا
`docs/agent-tasks/AGENT-A-students-registration-geography.md`
هجراتك: `2026_08_22_11*` حصرًا.
**ابدأ داخلها بالبند A12** (`teacher_courses` + عقد `TeacherQualificationQueries`) —
مدير المشروع ينتظره لترشيح المدرس البديل. ثم A1–A2 (الجغرافيا).

### ٣. الحزمة B — البرامج والكورسات والأهلية
`docs/agent-tasks/AGENT-B-academics.md`
هجراتك: `2026_08_22_12*` حصرًا. تعتمد على جدولَي `countries`/`regions` من الحزمة A.

### ٤. الحزمة C — إتاحة المعلم والطالب
`docs/agent-tasks/AGENT-C-availability.md`
هجراتك: `2026_08_22_13*` حصرًا.
بما أنك تنفّذ A و C معًا، قيود «لا تلمس `StaffProfile.php`» الواردة في ملف C
**لم تعد سارية** — كانت لمنع التعارض مع وكيل آخر. باقي الملف ساري كما هو.

### ٥. الحزمة G — السيناريوهات الاثنا عشر
`docs/agent-tasks/AGENT-G-scenario-tests.md`
ملفاتك في `tests/` فقط. آخر حزمة لأنها تقيس البقية.

---

## قواعد سارية على كل الحزم

- **كل أوامر PHP/Composer/Artisan داخل Docker** — PHP على الجهاز 5.6:
  `docker compose exec -T app php artisan ...`
- **ممنوع `docker compose exec -T app php artisan test` بلا `--filter`.**
  المستودع فيه ~212 اختبارًا فاشلًا سابقًا لا علاقة له بك، **و Codex يشغّل اختباراته
  الآن على نفس الحاوية** — التشغيل الكامل يفسد نتائجه ونتائجك. استخدم `--filter` دائمًا.
- **لا `git commit` ولا `git push`** مهما كان السبب.
- **ممنوع نص مكتوب مباشرة في الواجهة** — كل النصوص عبر ملفات الترجمة `{ar,en}`.
  (لاحظ: أدخلتَ مخالفتين في `SessionResource.php` سطر 171 و174 — `'متاح'` و`'في إجازة'`.
  صلّحهما ضمن الحزمة F.)
- **ممنوع `if ($user->role === ...)`** — الصلاحيات عبر Policies و`can:` فقط.
- **لا حذف** — SoftDeletes. **التواريخ UTC** في التخزين.
- **حدود الموديولات:** ممنوع `use Modules\X\Domain\Models\*` من موديول آخر.
  التواصل بأحداث Domain أو عقود عامة أو Query Services تعيد DTOs.
- **ممنوع Scope Creep:** لا موديول جديد · لا جدول أو خدمة جديدة بلا حاجة Domain حقيقية.

## تعريف «خلصت» لكل حزمة

الهجرات تعمل · اختباراتك (بـ`--filter`) تمر · `pint` نظيف على ملفاتك ·
**الميزة تعمل من الواجهة حتى قاعدة البيانات** — وجود Model أو Route وحده لا يكفي ·
تقرير `docs/agent-tasks/REPORT-<الحرف>.md` مكتوب.

**في التقرير اكتب الحقيقة:** إن كان بند `Partial` فاكتب `Partial`. لا تكتب أن
«تعريف خلصت تحقق» وهو لم يتحقق — المدير يتحقق من كل ادعاء على القرص وفي المتصفح.

## تحديث يخصك

عمود `original_teacher_id` **نزل بالفعل** على جدول `sessions` (هجرة `2026_08_22_150000`).
المعنى المعتمد: `original_teacher_id` لا يتغيّر أبدًا، و`staff_profile_id` هو المعلم
**الفعلي**. لا يوجد عمود `actual_teacher_id` منفصل — قيد منع الحجز المزدوج مبنيّ على
`staff_profile_id` وهذا هو الصحيح. عدّل واجهة اختيار البديل على هذا الأساس عند العودة إليها.
