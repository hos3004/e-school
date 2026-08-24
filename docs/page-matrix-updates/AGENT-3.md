# AGENT 3 — Sessions, Scheduling and Calendar

## المنجز

- أبقيت مورد الحصص على `index` و`view` دون صفحة تحرير مكسورة، وأضفت صفحة تقويم محلية للحصص (`calendar`) ضمن مورد Sessions.
- طورت ViewSession إلى مركز تشغيل يعرض بيانات الحصة، المجموعة/المقرر بالمعرّفات المتاحة، المعلم الأصلي والفعلي، الوقت والحالة، المشاركين وبيانات الانضمام، وسجل انتقالات الحالة.
- أضفت إجراءي إعادة الجدولة والإلغاء في رأس الصفحة. كلاهما يستدعي Action المجال الحقيقي، يطلب سببًا إلزاميًا، ويظهر فقط عندما تسمح آلة الحالات بالانتقال وPolicy الحالي بالصلاحية.
- أضفت ViewPostponementRequest وربطته بالقائمة، مع عرض تفاصيل الطلب وإجراء اعتماد يستخدم `ApprovePostponement` الحقيقي وموعدًا متفقًا عليه.
- أضفت رابط العرض للقائمة، واستبدلت النص غير المترجم لقيمة المهلة بمفتاح ترجمة.

## التحقق

- فحص PHP وPint مرّا على الملفات المعدلة.
- تشغيل `modules/Sessions/tests/Feature` معزولًا بـ `TEST_AGENT_ID=ag3`: **8 ناجحة، 6 فاشلة (27 assertion)**.
- الفشل الموجود في `Task03AcceptanceTest` سببه إنشاء الاختبارات جلسات بلا `course_id` بينما مخطط قاعدة البيانات يفرضه NOT NULL؛ لم أغيّر اختبارات أو مخطط موديولات أخرى لأنه خارج الملكية الحالية.
- لم أعدّ `/admin/sessions` مستلمًا نهائيًا قبل فحص HTTP بجلسة أدمن في بيئة التشغيل؛ يلزم تنفيذ فحص المتصفح/جلسة الدخول من المنسق.

## تحديثات المصفوفة المقترحة

- H6 Session details: **FUNCTIONAL** للأقسام المتاحة حاليًا، مع ملاحظات Backend أدناه.
- H8 Reschedule: **FUNCTIONAL** عبر Action المجال الحقيقي.
- H10 Substitute assignment: يبقى **PARTIAL**؛ الإجراء الموجود في المورد يعمل، لكن تاريخ البدلاء لا يملك Query/Section مكتملًا عبر Contract مستقل.
- H11 Substitute history: **PARTIAL/KNOWN ISSUE**.
- I2 Postponement view: **FUNCTIONAL** للعرض والاعتماد.
- I3 Substitute picker / I4 Reject: **PARTIAL**؛ لا توجد RejectPostponement Action قابلة لإعادة الاستخدام في الموديول.
- J1 BBB create/retry وJ3 guest links: **KNOWN ISSUE** في نطاق Filament الحالي؛ لا توجد صفحة/Action عرض مخصصة مرتبطة بالحصة يمكن استدعاؤها دون اختلاق Backend.

## Known issues / اقتراحات الدمج

- لا توجد RejectPostponement Action حقيقية حاليًا؛ يجب إضافة Action مجال مدققة وPolicy قبل إظهار زر رفض.
- `ApprovePostponement` ينشئ حصة التلافي عبر `DB::table` ولا يملأ `original_teacher_id`، كما لا يدعم تعيين بديل؛ يلزم إصلاح داخل Scheduling/Sessions قبل بناء picker موثوق.
- أسماء البرنامج/المقرر/المجموعة وأسماء المعلمين تُعرض كمعرّفات لأن عقد Query DTO العابر للموديولات غير متاح ضمن الملكية.
- يلزم للمنسق إضافة permission صريح لعرض/اعتماد طلبات التأجيل إن لم يكن `session.postpone.approve` كافيًا، وإضافة رابط Calendar في التنقل إن كان مطلوبًا بصريًا.
