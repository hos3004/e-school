
| N7 | Session Details (Teacher) | `/teacher/sessions/{id}` | Teacher/Sessions/Show.tsx | TeacherSessionController + attendance/report APIs | attendance.record ◐assigned | Join BBB · Students · Attendance · Session report · Recording link حسب permission | **FUNCTIONAL** | PortalRoutesTest + Sessions tests | إكمال حالات الفراغ والخطأ |
| N8 | Availability | غير موجودة | — | staff availability API موجودة | own | إدارة إتاته وطلبات اعتمادها | **MISSING** | Staff tests جزئي | — |
| N9 | Submit Apology | غير موجودة | — | postpone request API | session.postpone.request ◐assigned | طلب اعتذار بالسبب (لا يلغي الحصة) | **MISSING** | — | مربوط I4 |
| N10 | My Apology Requests | غير موجودة | — | نفس المصدر own scope | own | متابعة طلباته وقراراتها | **MISSING** | — | مربوط I5 |
| N11 | Notifications | L1+L2 | — | مصادر L | auth | إشعاراته | **MISSING** | — | ملفات مشتركة مع Agent 7 |

## المجموعة O — GUARDIAN PORTAL  → **AGENT 6**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| O1 | Dashboard | `/guardian` | Guardian/Dashboard.tsx | GuardianDashboardController (children/selectedChild) | student.view ◐children | أبناؤه المرتبطون الموثقون فقط | **TESTED** | PortalRoutesTest (عبر مؤسسات يرجع 404) | — |
| O2 | Children List | ضمن O1 | نفس الصفحة | guardian_links verified فقط | student.view children | قائمة الأبناء | **FUNCTIONAL** | PortalRoutesTest | — |
| O3 | Child Overview | غير موجودة كصفحة مستقلة | — | تجميع حالة الابن | ◐children | نظرة شاملة لابن | **PARTIAL** | — | تبويب أو صفحة فرعية |
| O4 | Child Attendance | `/guardian/children/{studentId}/attendance` | Guardian/Child/Attendance.tsx | GuardianAttendanceController | attendance.view children | حضور الابن | **TESTED** | PortalRoutesTest | — |
| O5 | Child Reports | `/guardian/children/{studentId}/reports` | Guardian/Child/Reports.tsx | GuardianReportsController | session_report.view children | تقارير الابن | **TESTED** | PortalRoutesTest | — |
| O6 | Child Schedule | غير موجودة | — | schedule queries children | schedule.view children | جدول الأبناء إن سمحت المصفوفة | **MISSING** | — | مسموح دائمًا وفق docs/06 §4 |
| O7 | Notifications | L1+L2 | — | مصادر L | auth | إشعاراته | **MISSING** | — | ملفات مشتركة مع Agent 7 |

**قاعدة صريحة:** لا تُبنى أي واجهة تتيح لولي الأمر قراءة محادثات الطالب الخاصة
مع المعلم أو الزملاء — مهما كانت علاقاته المسجلة.

## المجموعة P — MESSAGING  → **AGENT 7**

الـAPIs والسياسات موجودة (conversations/messages/wall مع ConversationPolicy
وClassWallPostPolicy). الواجهة الأمامية للبوابات **غير موجودة إطلاقًا**.

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| P1 | Inbox | غير موجودة | — | api/conversations endpoints جاهزة | message.send حسب النطاق | صندوق المحادثات | **MISSING** UI | Messaging tests API-side | صفحة مشتركة للبوابات الثلاث بنطاق كل دور |
| P2 | Conversation View | غير موجودة | — | api/conversations/{id}/messages | عضوية فعلية شرط | قراءة/كتابة برسائل | **MISSING** UI | Messaging tests | منع الوصول العابر مثبت API-side ويُختبر UI-side |
| P3 | New Conversation | غير موجودة | — | POST api/conversations | message.send | بدء محادثة مسموحة | **MISSING** UI | — | قيود المسموح لكل دور |
| P4 | Admin/Supervisor Monitoring | `/admin/conversations` + `/admin/messages` | موارد CRUD افتراضية | messaging.inbound.view + message.moderate | المشرفون المصرح لهم | إشراف وإبلاغ وحظر/كتم حيث في العقد | **PARTIAL** | Messaging tests | تحويل CRUD الخام إلى UX إشرافي |
| P5 | Class Wall (إن كان ضمن العقد الحالي) | `/admin/class-wall-posts` | مورد CRUD افتراضي | wall APIs جاهزة | class_wall.post حسب الدور | حائط الصف | **PARTIAL** | Messaging tests | لا توسّع ميزات جديدة |

---
