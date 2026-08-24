# CODEX TASK 1 — بوابة الطالب الكاملة (AGENT 4)

> اقرأ قبل أي فعل، بالترتيب: `AGENTS.md` ثم `docs/page-completion-matrix.md` قسمي «UI Contracts» و«خريطة ملكية الملفات» ثم هذا الملف كاملًا.
> ممنوع commit/push. كل أوامر PHP/Pest داخل Docker عبر `docker compose exec -T`.

## هويتك وملكيتك الحصرية (ممنوع لمس غيرها)
- `resources/js/Pages/Student/**`
- `app/Http/Controllers/Portal/Student*.php` و`app/Http/Controllers/Portal/Support/PortalData.php` (إضافات قراءة فقط)
- مفاتيح ترجمة تحت بادئة `student.*` فقط في `resources/lang/ar/portal.php` و`en/portal.php`
- اختبارات جديدة: `tests/Feature/PageCompletion/Student/*`
- سجل تسليمك: `docs/page-matrix-updates/AGENT-4.md` (أنشئه)

## ممنوعات مطلقة
- لمس `routes/web.php` أو `AppLayout.tsx` أو `i18n.ts` أو `types/index.d.ts`: أي حاجة فيها تُسجَّل في سجل التسليم بـdiff مقترح ولا تُنفَّذ.
- لمس موارد Filament أو موديولات backend إلا قراءة.
- Mock أو placeholder أو زر بلا فعل حقيقي.
- إعادة كتابة الصفحات العاملة (M1/M5/M6) إلا لإضافة ناقص محدد.
- نصوص hardcoded: كل شيء عبر `t()` بمفاتيح `student.*`.
- تشغيل pest على قاعدة التطوير: حصرًا `docker compose exec -T -e TEST_AGENT_ID=codex4 app php scripts/test-isolated.php <path>`.

## السياق الجاهز لك
- البوابات تعمل ببيانات حقيقية عبر خدمة `PortalData` (اقرأها لتعرف أسلوب الاستعلام والـDTOs).
- APIs جاهزة تستخدمها دون تعديل: `GET api/me` · `PATCH api/me` · `PUT api/me/password` · `api/devices*` · `api/students/{student}` · `api/enrollments*` · `api/groups` · `api/notifications` (+mark-as-read/unread-count) · `api/student-dashboards/{enrollmentId}`.
- حساب طالب للفحص اليدوي: `student1@demo.local` / كلمة المرور `password` — التطبيق http://localhost:8090 من المضيف.
- نمط تسجيل دخول curl للفحص اليدوي: GET `/login` لأخذ كوكيز XSRF ثم POST `/login` مع هيدر `X-XSRF-TOKEN` وحفظ الجلسة.
- RTL عربي أولًا ومتوافق LTR، عرض أدنى 375px، استخدم مكوّنات النظام: Button/Card/DataTable/StatusPill/PageHeader/LoadingState/EmptyState/ErrorState/Badge.
- حالات الصفحة الثلاث إلزامية: Loading · Empty · Error مع retry.

## مهامك

### T4.1 صفحة My Profile ‏(M2)
مسار جديد `/student/profile` (Route جديد يُقترح في سجل التسليم بصيغة جاهزة للصق — لا تعدل web.php بنفسك):
- بطاقة بيانات الحساب (اسم/username/بريد/هاتف/لغة/توقيت) + نموذج تعديل يمر على `PATCH api/me` مع رسائل خطأ الحقول من السيرفر.
- تغيير كلمة مرور عبر `PUT api/me/password` بقوة كلمة المرور المعتمدة (10+ أحرف وأرقام).
- أجهزتي المسجلة عبر `api/devices` مع زر إبطال جهاز (revoke) بتأكيد Modal.
- Feedback نجاح/فشل واضح لكل عملية + تدقيق أن الطالب لا يرى حقلاً لا يملكه.

### T4.2 صفحة My Programs ‏(M3)
`/student/programs`: قائمة برامج الطالب من قيوده الفعلية (حالة القيد نشط/مجمّد…) عبر قراءة من PortalData (استعلام قراءة فقط داخل ملكيتك)، Status pill للحالة، Empty state إن لا قيود.

### T4.3 صفحة My Group ‏(M4)
`/student/group`: مجموعة الطالب الحالية — اسمها وزملاؤها ومعلموها وجدولها المختصر. إن كان في أكثر من مجموعة اعرض قائمة ثم تفاصيل. بيانات من العضوية الفعلية لا hardcoded.

### T4.4 My Availability ‏(M7)
افحص أولًا هل يوجد نموذج إتاحة طالب في الـdomain (ابحث في modules/Enrollments وmodules/Students عن availability). إن وُجد ابنِ صفحته فوق API موجود؛ **إن لم يوجد: توقف عن هذه المهمة وسجلها Known issue مع ما وجدته** — ممنوع اختراع جدول جديد.

### T4.5 Notifications ‏(M8)
صفحة `/student/notifications` فوق `api/notifications`: قائمة مقروء/غير مقروء، زر تعليم كمقروء وفردًا وجميعًا، deep link حسب نوع الإشعار حيث يتوفر target، عداد غير المقروء يظهر في الصفحة (الجرس المركزي ملك Agent 7 — لا تنشئه).

### T4.6 Navigation entries
روابط الصفحات الجديدة يجب أن تصل إليها من التنقل: سجّل في سجل التسليم أسطر `{ href, labelKey }` الجاهزة لإضافتها في AppLayout (سيضيفها المنسق). لا تعدل AppLayout بنفسك.

## قبول صارم لكل صفحة
1. Route يعمل 200 بعد الدخول كطالب، ودور معلم يحصل 403 على مساراتك (اختبره).
2. Happy path يدويًا موثقًا في سجل التسليم (خطوات + نتائج HTTP).
3. Loading/Empty/Error ظاهرة فعليًا (اختبر قطع البيانات بإرجاع فارغ مؤقتًا؟ لا — اختبر بحساب بلا بيانات).
4. لا translation key خام في أي حالة.
5. اختبار آلي Pest واحد على الأقل لكل صفحة: happy + forbidden (دور غير مصرح) — ضمن testsuite المعزول أخضر.
6. `npx tsc --noEmit` نظيف على ملفاتك (إن توفر) وبناء Vite لا ينكسر.

## التسليم
حدّث `docs/page-matrix-updates/AGENT-4.md`: المنجز، نتائج HTTP والاختبارات بالأرقام، أسطر تحديث مصفوفة مقترحة (M2→FUNCTIONAL…)، Known issues، مقترحات routes/nav للمدمج. ثم أعِد ملخصًا نهائيًا بنفس المحاور.
