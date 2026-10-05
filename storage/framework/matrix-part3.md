
## المجموعة H — SCHEDULING / SESSIONS  → **AGENT 3**

أهم مجموعة. الواقع المثبت: SessionResource ‏index+view فقط. **ملاحظة حرجة:
`EditAction::make()` مسجّل في الجدول (SessionResource.php:143) بينما صفحة edit
غير معرّفة في getPages — زر مكسور يجب إصلاحه فورًا (إما تسجيل صفحة edit أو
إزالة الزر).** لا يوجد مكوّن تقويم (Calendar) في المشروع إطلاقًا.

| ID | الصفحة | Route | Status | Notes |
|---|---|---|---|---|
| H1 | Main Calendar | غير موجودة | **MISSING** | صفحة تقويم Filament (شهر/أسبوع) تعرض الحصص بالنقر للتفاصيل |
| H2 | Sessions List | `/admin/sessions` | **FUNCTIONAL** | فلاتر حالة/تاريخ/معلم/مجموعة |
| H3 | Create Session | عبر API فقط حاليًا | **PARTIAL** | صفحة إنشاء Filament فوق منطق API الحالي + منع التعارض |
| H4 | Create Recurring Sessions | API يدعم التكرار؟ تحقق Agent 3 | **MISSING** UI | نموذج تكرار أسبوعي بنطاق زمني |
| H5 | Recurrence Preview | — | **MISSING** | معاينة الحصص الناتجة قبل الحفظ |
| H6 | Session Details (Operations Hub) | `/admin/sessions/{record}` | **PARTIAL** | يجب أن تعرض: Program · Course · Group · Students · Original Teacher · Actual Teacher · Date/time + timezone · Status · BBB · Recording · Attendance metadata · Notifications · Changes/Audit |
| H7 | Edit Session | صفحة edit غير مسجلة والزر مكسور | **SKELETON** | إصلاح التناقض أعلاه أولًا |
| H8 | Reschedule Session | postpone API موجودة | **PARTIAL** | تدفق تأجيل بالسبب من الواجهة |
| H9 | Conflict Display | منطق التعارض في backend | **MISSING** UI | عرض التعارضات عند الإنشاء/التعديل قبل الحفظ |
| H10 | Substitute Assignment | Action ‏assign_substitute موجود فعليًا في SessionResource:151 | **PARTIAL** | استكمال picker المرشحين المؤهلين |
| H11 | Substitute History | ضمن H6/D12 | **MISSING** | سجل البدلاء لكل حصة/معلم |

## المجموعة I — TEACHER APOLOGY / SUBSTITUTE  → **AGENT 3**

القرار الحاكم: اعتماد الاعتذار **لا يلغي الحصة** — يفتح مسار اختيار بديل،
والمعلم الأصلي يظل محفوظًا والمنفذ الفعلي واضح.

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| I1 | Admin Apology Requests | `/admin/postponement-requests` | ListPostponementRequests (index فقط) | Scheduling | session.postpone.approve | قائمة الطلبات وقراراتها | **PARTIAL** | Scheduling tests | إضافة view page بالقرار بالسبب |
| I2 | Apology Details + Decision | `.../{record}` غير موجودة | — | postpone approve API موجودة | session.postpone.approve | قرار مع سبب وتدقيق | **MISSING** | — | — |
| I3 | Substitute Candidate Picker | ضمن I2/H10 | assign_substitute action جزئي | مؤهلات المعلم عبر TeacherQualificationQueries | session.assign_substitute | مرشحون مؤهلون بلا تعارض | **PARTIAL** | — | — |
| I4 | Submit Apology (Teacher Portal) | غير موجودة | — | postpone request API موجودة (student/guardian/teacher ◐assigned) | session.postpone.request | طلب اعتذار بالسبب | **MISSING** | — | بوابة المعلم N9 |
| I5 | My Apology Requests (Teacher Portal) | غير موجودة | — | نفس المصدر | own scope | متابعة حالة الطلبات | **MISSING** | — | — |

## المجموعة J — BBB / CLASSROOM UI  → **AGENT 3** (ضمن Session Details)

لا dashboard مستقل. كل ما يلزم يعيش داخل H6:

| ID | العنصر داخل H6 | Backend | Status | Notes |
|---|---|---|---|---|
| J1 | Create classroom / Retry creation | VirtualClassroomProvider + BigBlueButtonProvider موجودان | **PARTIAL** | أزرار إنشاء/إعادة المحاولة بحالة وأخطاء واضحة |
| J2 | Join links (teacher/student/supervisor) | أدوار BBB: moderator/attendee/observer حسب الصلاحية | **FUNCTIONAL** (البوابات تعرض joinUrl بشروط canJoin) | التحقق أن admin/supervisor يحصلان observer/moderate حسب docs |
| J3 | Guest link generation + revoke | guest tokens بمهل وانتهاء | **PARTIAL** | classroom.guest.invite/revoke موجودتان في المصفوفة؛ UI مطلوب |
| J4 | Meeting status + Recording status | classroom events + recordings | **PARTIAL** | عرض الحالة الأخيرة ومصدرها |

## المجموعة K — RECORDINGS  → **AGENT 7**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| K1 | Recordings List | `/admin/recordings` | RecordingResource (افتراضي index/create/edit — بلا getPages) | Recordings module | recording.view.any | قائمة بحالات التسجيلات | **PARTIAL** | Recordings unit tests | create/edit يدوي غير منطقي للأعمال — استبداله بview + actions نظامية |
| K2 | Recording Details | غير موجودة | — | Recording model + status/history | recording.view | تفاصيل وحالة وسجل | **MISSING** | — | صفحة view مسجلة |
| K3 | Recording Player/View | غير موجودة | — | روابط موقعة 120 دقيقة + recording_views log | recording.view + منح | تشغيل محمي بتسجيل مشاهدة | **MISSING** | — | ممنوع رابط عام |
| K4 | Recording Access Grants | جدول grants موجود (عمل Task03) | GrantRecordingAccessAction موجود API-side | recording.grant | منح وصول بانتهاء | **PARTIAL** | Task03 acceptance tests | Modal منح داخل K2 |
| K5 | Grant Access Modal | ضمن K2 | — | نفس المصدر | recording.grant | اختيار مستلم + مدة | **MISSING** | — | — |
| K6 | Recording status/history | ضمن K2 | — | report_event_log | recording.view.any | مشاهدات وتنزيلات وحذف بالسبب | **MISSING** | — | — |

صلاحيات العميل المطبقة: Admin كامل · Supervisor حسب منح · Teacher حصته فقط
بلا تنزيل افتراضيًا · Student/Guardian بلا وصول افتراضيًا.
