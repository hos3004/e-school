
## المجموعة L — NOTIFICATIONS  → **AGENT 7**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| L1 | Notification Bell (portals) | — | غير موجود | api/notifications/unread-count موجودة | auth | عداد غير المقروء | **MISSING** | لا يوجد | مكوّن مشترك واحد لكل البوابات |
| L2 | Notification Center (user side) | غير موجود | — | api/notifications + mark-as-read + mark-all-as-read موجودة | auth | قائمة مقروء/غير مقروء بروابط عمق | **MISSING** | لا يوجد | صفحة أو drawer مشترك |
| L3 | Notification Deep link | ضمن L2 | — | نفس المصدر | auth | فتح الهدف حسب نوع الإشعار | **MISSING** | — | — |
| L4 | Delivery Log (admin) | `/admin/notification-outboxes` | ListNotificationOutboxes (index فقط) | Outbox + attempts | system.alerts / audit.view | سجل: In-App/Email/WhatsApp · status · attempts · last error · external id | **PARTIAL** | Notifications tests | أكمل الأعمدة والفلاتر |
| L5 | Delivery Details | view page غير مسجلة | — | attempts API موجودة | نفسها | تفاصيل المحاولات والخطأ الأخير | **MISSING** | — | تسجيل صفحة view |
| L6 | Failed Notifications | فلتر داخل L4 | — | status=failed | نفسها | قائمة الفاشل | **PARTIAL** | Task04 tests (فاشلة حاليًا) | — |
| L7 | Manual Retry | Actions داخل L4/L5 | retry/cancel API موجودة | notifications.retry | نفسها | إعادة إرسال يدوي بالتدقيق | **PARTIAL** | جزئي | ربط أزرار Filament بالـAPI الحقيقية |
| L8 | Notification Settings (user prefs) | `/admin/notification-preferences` | NotificationPreferenceResource (CRUD افتراضي) | preferences API | مالك الحساب | تفضيلات القنوات وساعات الهدوء | **PARTIAL** | جزئي | التأكد أن التعديل محصور بالمالك |

## المجموعة M — STUDENT PORTAL  → **AGENT 4**

الموجود يعمل ببيانات حقيقية عبر `PortalData` ولا يُعاد كتابته إلا لنقص:

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| M1 | Dashboard | `/student` | Student/Dashboard.tsx | StudentDashboardController (nextSession/weekSessions/attendanceRate/openAssignments) | session.view | لوحة الطالب | **TESTED** | PortalRoutesTest | إثراء ببطاقات Programs/Group لاحقًا من C |
| M2 | My Profile | غير موجودة | — | api/identity/me + PATCH + password endpoints جاهزة | own | عرض/تعديل بياناته وكلمة مروره | **MISSING** UI | ProfileEndpointsTest للـAPI | صفحة Inertia فوق APIs قائمة |
| M3 | My Programs | غير موجودة | — | enrollment queries | ◐own | برامج الطالب وحالتها | **MISSING** | — | — |
| M4 | My Group | غير موجودة | — | membership queries | ◐own | مجموعته وزملاؤه ومدرسوه | **MISSING** | — | — |
| M5 | Schedule | `/student/schedule` | Student/Schedule.tsx | StudentScheduleController | schedule.view | جدوله | **TESTED** | PortalRoutesTest | — |
| M6 | Session Details | `/student/sessions/{id}` | Student/Sessions/Show.tsx (join logic موجود) | StudentSessionController + classroom join | session.view + session.join | تفاصيل ودخول BBB بشروط النافذة | **FUNCTIONAL** | PortalRoutesTest | إكمال حالة الخطأ عند تعذر الدخول |
| M7 | My Availability | غير موجودة | — | تحقق من نموذج إتاحة الطالب أولًا | own | إتاحته الذاتية | **MISSING** | — | إن لم يوجد أساس backend يُبلّغ (مربوط C11) |
| M8 | Notifications | L1+L2 | — | مصادر L | auth | إشعاراته | **MISSING** | — | ملفات مشتركة مع Agent 7 (قاعدة تنسيق أدناه) |

## المجموعة N — TEACHER PORTAL  → **AGENT 5**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| N1 | Dashboard | `/teacher` | Teacher/Dashboard.tsx | TeacherDashboardController (todaysSessions/pendingAttendance/lateReports) | session.view | لوحة المعلم | **TESTED** | PortalRoutesTest | — |
| N2 | My Profile | غير موجودة | — | identity/me + staff profile APIs | own | ملفه | **MISSING** | — | — |
| N3 | My Subjects | ضمن N2 | — | TeacherQualificationQueries | own | موادّه وتأهيله (قراءة فقط) | **MISSING** | — | — |
| N4 | My Groups | غير موجودة | — | group-teachers queries | assigned | مجموعاته | **MISSING** | — | — |
| N5 | My Students | غير موجودة | — | عبر مجموعاته فقط | ◐assigned | طلاب مجموعاته لا غير | **MISSING** | — | صرامة النطاق assigned |
| N6 | Schedule | `/teacher/schedule` | Teacher/Schedule.tsx | TeacherScheduleController | schedule.view | جدوله | **TESTED** | PortalRoutesTest | — |
