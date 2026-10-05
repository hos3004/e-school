# Page Completion Matrix — مصفوفة اكتمال الصفحات

> **الأولوية الحاكمة لهذه الموجة:** إكمال كل صفحة مطلوبة في Scope المرحلة الأولى
> كمنتج قابل للاستعراض من المتصفح. ليس الهدف: PHPStan شامل، refactor، موديولات جديدة.
>
> **مصدر هذه المصفوفة:** فحص فعلي على القرص بتاريخ 2026-08-22 —
> `route:list` (339 مسارًا)، 49 مورد Filament في `AdminPanelProvider`، صفحات
> Inertia تحت `resources/js/Pages`، متحكمات `app/Http/Controllers/Portal` و
> `app/Http/Controllers/Auth`، والاختبارات الآلية الموجودة. ليس نسخًا من الوثائق القديمة.
>
> **قاعدة الصفحة المكتملة (DONE):** Route يعمل · تصريح Server-side · تصميم كامل ·
> RTL صحيح · Responsive · بيانات حقيقية · Create/Edit/Delete/Actions تعمل ·
> Validation واضح · Loading/Empty/Error states · Feedback واضح · Search/Filters حيث
> تلزم · لا translation keys ظاهرة · لا أزرار بلا وظيفة · لا 500 · لا placeholders ·
> Happy Path مُختبَر · Permission path مُختبَر · مرتبطة بالـNavigation.
>
> **الحالات فقط:** MISSING / SKELETON / PARTIAL / FUNCTIONAL / TESTED / DONE
>
> | الرمز | المعنى |
> |---|---|
> | MISSING | لا وجود لها إطلاقًا |
> | SKELETON | Route/Component موجود لكنه غير صالح للاستخدام |
> | PARTIAL | تعمل جزئيًا وتنقصها عناصر من قاعدة DONE |
> | FUNCTIONAL | تعمل كاملة وظيفيًا؛ لم تُختبر يدويًا بالقائمة الكاملة |
> | TESTED | FUNCTIONAL + اختبارات آلية للـhappy وforbidden paths |
> | DONE | TESTED + تحقق يدوي موثق (تصفح/RTL/responsive/states) |

---

## 0. خارج النطاق (لا يفتحها أي Agent في هذه الموجة)

موارد موجودة اليوم في اللوحة لكن Approved Scope §6 يخرجها من المرحلة الأولى.
توصية: تُخفى خلف feature flags كما فُعل مع Payroll (`config/features.php`) —
قرار الإخفاء يعتمده Codex ولا ينفذه الوكلاء من تلقاء أنفسهم:

| العنصر | الوضع الحالي |
|---|---|
| Assessments (AssessmentResource, AssessmentAttemptResource) | ظاهرة حاليًا رغم أنها خارج النطاق |
| Certificates/Badges (4 موارد) | ظاهرة حاليًا |
| Payroll (Entry/Period) | مخفية خلف flag ✓ |
| Integrations CRUD العام | تشغيلي داخلي؛ لا صفحات جديدة تُبنى له |

---

## المجموعة A — PUBLIC / AUTH  → **AGENT 6**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| A1 | Login | GET/POST `/login` | `Auth/Login.tsx` | Fortify + `AuthServiceProvider::authenticateUsing` (username/email/phone) + limiter 5/15د | زائر | مصادقة موحدة، رسالة فشل موحدة | **TESTED** | `FortifyAuthPagesTest` (12) | لا تُعاد كتابتها إلا لعيب فعلي |
| A2 | Forgot Password | GET/POST `/forgot-password` | `Auth/ForgotPassword.tsx` | Password broker + رسالة محايدة تمنع enumeration | زائر | إرسال رابط استعادة | **TESTED** | `FortifyAuthPagesTest` | — |
| A3 | Reset Password | GET/POST `/reset-password/{token}` | `Auth/ResetPassword.tsx` | `ResetUserPassword` action + حدث `PasswordResetCompleted` | حامل توكن | تغيير كلمة + إبطال جلسات | **TESTED** | `FortifyAuthPagesTest` | — |
| A4 | Two Factor Challenge | GET/POST `/two-factor-challenge` | `Auth/TwoFactorChallenge.tsx` | عرض مربوط فقط؛ ميزة TOTP غير مفعّلة | مستخدم 2FA | تحدٍ فعلي غير مفعّل | **SKELETON** | — | تفعيل 2FA قرار ما قبل الإنتاج (docs/15) — لا تُبنى تسجيل أجهزة في هذه الموجة |
| A5 | Public Student Registration | GET/POST `/register/student` | `Auth/RegisterStudent.tsx` (نموذج حر) | `PublicStudentRegistrationController@store` → `RegistrationApplication` | زائر | إنشاء طلب pending | **TESTED** | لا يوجد | ثغرات مطلوب إصلاحها: country/city نص حر بدل Selector جغرافيا؛ fallback مؤسسة بقيمة وهمية في config؛ بلا rate limit؛ تفرد البريد يفحص users لا registration_applications |
| A6 | Registration Submitted | `/register/submitted` | `Auth/RegistrationSubmitted.tsx` | نفس المتحكم | زائر | تأكيد التقديم | **FUNCTIONAL** | لا يوجد | اختبار آلي مع A5 |
| A7 | Application Status | `/register/status/{id}` | `Auth/ApplicationStatus.tsx` | قراءة مباشرة بالمعرف | حامل الرابط | عرض حالة الطلب | **FUNCTIONAL** | لا يوجد | بلا rate limit؛ يفضل رقم متابعة آمن بدل id خام |

## المجموعة B — ADMIN DASHBOARD  → **AGENT 8**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| B1 | Admin Dashboard | `/admin` | Filament Dashboard + Widgets: `PlatformOverview` · `NeedsAttention` · `SessionsTrend` · `UpcomingSessions` · `QuickActions` | بيانات حقيقية + كل البطاقات قابلة للنقر تفتح وجهات موجودة | panel access | عدادات + انتباه + إجراءات سريعة | **FUNCTIONAL** | PortalRoutesTest 22✓ | ترجمة كاملة عبر dashboard.php؛ تسليم AGENT-8؛ يرقى لـDONE بعد التحقق اليدوي النهائي (Agent 8 §8.2) |


## المجموعة C — STUDENTS ADMIN  → **AGENT 1**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| C1 | Registration Applications List | `/admin/registration-applications` | ListRegistrationApplications | RegistrationApplicationResource (index+view) | student.view.any + review policy | قائمة + فلاتر الحالة | **FUNCTIONAL** | Feature tests جزئية | فلتر «منتظرة» افتراضي |
| C2 | Registration Review | `/admin/registration-applications/{record}` | ViewRegistrationApplication | Actions submit/review/accept/reject مع Policy | review policy | القبول يولّد StudentProfile داخل معاملة | **FUNCTIONAL** | `RegistrationApplicationTest` جزئي | تحقق يدوي من UX الرفض بالسبب |
| C3 | Students List | `/admin/students` | ListStudentProfiles | StudentProfileResource (CRUD كامل) | student.view.any | جدول + بحث/فلاتر | **FUNCTIONAL** | عامة | — |
| C4 | Create Student | `/admin/students/create` | CreateStudentProfile Wizard | `CreateStudentOnboardingAction` | student.create | حساب جديد أو موجود → طلب → قبول → ملف → دور طالب داخل معاملة | **TESTED** | `StudentOnboardingActionTest` + `AdminProfileHubTest` | Selector جغرافيا + برنامج/كورس + سبب قبول؛ لا حسابات يتيمة عند الفشل |
| C5 | Student Profile Hub | `/admin/students/{record}` | ViewStudentProfile | `ProfileAdministrationQueryService` عبر DTO Query Contracts | student.view | Overview + Account/Programs/Groups/Sessions Tabs | **TESTED** | `AdminProfileHubTest` | إتاحة الطالب تظل غير موجودة Domain-wise ولا يُخترع لها جدول |
| C6 | Edit Student | `/admin/students/{record}/edit` | EditStudentProfile | form | student.update | تعديل | **FUNCTIONAL** | لا يوجد خاص | — |
| C7 | Student Account | عبر `/admin/users/{record}/edit` | UserResource edit | Identity UserResource (create/edit) | identity.users.* | ربط حساب↔ملف، حالة، reset password | **PARTIAL** | `ChangeUserStatus*` tests | تنظيم الربط داخل Hub C5 |
| C8 | Student Programs | Tab في C5 | EnrollmentAdministrationQueries | Enrollments DTOs | enrollment.view | برامج الطالب وحالة القيد | **FUNCTIONAL** | `AdminProfileHubTest` | قراءة فقط داخل Hub |
| C9 | Student Groups | Tab في C5 | GroupAdministrationQueries | Groups DTOs | group.view | مجموعات الطالب وحالة العضوية | **FUNCTIONAL** | `AdminProfileHubTest` | قراءة فقط داخل Hub |
| C10 | Student Sessions | Tab في C5 | SessionAdministrationQueries | Sessions DTOs | session.view | آخر حصص الطالب والحضور | **FUNCTIONAL** | `AdminProfileHubTest` | الحد الأقصى من config لا رقم مكتوب في الواجهة |
| C11 | Student Availability | Tab في C5 | — | تحقق من وجود نموذج إتاحة الطالب أولًا | enrollment.view | إتاحة الطالب | **MISSING** | — | Backend gap rule: إن لم يوجد نموذج، أوقف وبلّغ قبل اختراع جدول |
| C12 | Assign Student to Program/Group | Action في C3/C5 | `AssignStudentToGroupAction` نفسه المستخدم بالـAPI | Groups/Enrollments Contracts | enrollment.create + group.manage | برنامج → كورس → مجموعات متاحة بالسعة والمعلم؛ سبب وتدقيق | **TESTED** | `Task02AcceptanceTest` | لا كتابة مباشرة في `group_memberships`؛ التراجع الذري واختبارات المؤسسة/الأهلية/التأهيل ناجحة |
| C13 | Freeze / Status Management | ضمن C7 + `/admin/discipline-reactivations` | ReactivationRequests (index/view) + حالة المستخدم | ChangeUserStatus + Discipline reactivations | enrollment.freeze / reactivate | تجميد وفك وفق قاعدة العميل (تقييم ثم قرار مصرح) | **PARTIAL** | Discipline tests | فك التجميد يمر بالتقييم لا زرًا مباشرًا |

## المجموعة D — TEACHERS ADMIN  → **AGENT 1**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| D1 | Teachers List | `/admin/staff-profiles` | ListStaffProfiles | StaffProfileResource (index/view/edit، **بلا create**) | staff.view.any | جدول + فلاتر | **FUNCTIONAL** | عامة | — |
| D2 | Create Teacher | غير موجودة | getPages بلا create | ImportStaffAction API-side موجود | staff create permission؟ | ملف معلم + contract + rates | **MISSING** | لا يوجد | Backend gap rule: صفحة create فوق الموارد الحالية |
| D3 | Teacher Profile Hub | `/admin/staff-profiles/{record}` | ViewStaffProfile | `ProfileAdministrationQueryService` عبر DTO Query Contracts | staff.view | Overview + Account/Qualifications/Groups/Availability/Sessions Tabs | **FUNCTIONAL** | `AdminProfileHubTest` | تاريخ البدلاء والاعتذارات ما زالا خارج هذا الإكمال |
| D4 | Edit Teacher | `.../{record}/edit` | EditStaffProfile | form | تعديل staff | تعديل | **FUNCTIONAL** | لا يوجد خاص | — |
| D5 | Teacher Account | Tab في D3 | UserAccountDirectory | Identity DTO | identity.users.* | عرض الحساب المرتبط | **FUNCTIONAL** | `AdminProfileHubTest` | التعديل يظل عبر UserResource |
| D6 | Qualifications / Subjects | Tab في D3 | TeacherQualificationQueries | Staff + AcademicCatalogQueries | staff.contract.view | مؤهلات المعلم ومواده | **FUNCTIONAL** | `AdminProfileHubTest` | قراءة مجمعة بلا استيراد Models عبر الحدود |
| D7 | Teacher Programs | Tab في D3 | — | روابط البرامج-المعلمين | program.manage | ارتباط بالبرامج | **MISSING** | — | — |
| D8 | Teacher Groups | Tab في D3 | GroupAdministrationQueries | Groups DTOs | group.view | مجموعات المعلم ودوره والكورس | **FUNCTIONAL** | `AdminProfileHubTest` | الإسناد/التغيير يظل في G |
| D9 | Teacher Availability (view) | Tab في D3 | StaffAdministrationQueries | Staff DTOs | schedule.view | إتاحة المعلم وحالة الاعتماد | **FUNCTIONAL** | `AdminProfileHubTest` | عرض فقط؛ الاعتماد D10 مستقل |
| D10 | Availability Approval | Action ضمن D9 | ApproveTeacherAvailabilityAction موجود (عمل Task03) | Staff | إدارة الإتاحة | اعتماد/رفض مع سبب | **PARTIAL** | جزئي | أكمل UI الاعتماد الجماعي والسبب والتدقيق |
| D11 | Teacher Sessions | Tab في D3 | SessionAdministrationQueries | Sessions DTOs | session.view.assigned | آخر حصص المعلم | **FUNCTIONAL** | `AdminProfileHubTest` | حد العرض من config |
| D12 | Substitute History | Tab في D3 | original_teacher_id vs actual teacher | Sessions schema يدعم ذلك | session.view | تاريخ البدلاء | **MISSING** | — | — |
| D13 | Apology History | Tab في D3 | PostponementRequestResource index-only | Scheduling | session.postpone.request | اعتذارات المعلم وقراراتها | **PARTIAL** | Scheduling tests | — |



## المجموعة E — GEOGRAPHY  → **AGENT 1**

الواقع المثبت: `GeographyQueries` قراءة فقط (دول/مناطق مملوكة لـOrganization)،
**لا يوجد أي مورد Filament لإدارة الدول/المناطق**، ونموذج التسجيل العام يستخدم
حقول نص حر. ممنوع إبقاء text field حر للدولة والمحافظة في أي نموذج.

| ID | الصفحة/المكوّن | Route | Status | Notes |
|---|---|---|---|---|
| E1 | Countries Management | غير موجودة | **MISSING** | مورد Filament بسيط + بيانات مرجعية؛ صلاحية settings.manage |
| E2 | Regions/Governorates Management | غير موجودة | **MISSING** | تابعة لفلتر دولة |
| E3 | Geography Selector في Registration | ضمن A5 | **PARTIAL** | استبدال النص الحر بقائمتين مترابطتين |
| E4 | Geography Selector في نماذج Student/Teacher | ضمن C4/C6/D2/D4 | **PARTIAL** | نفس المكوّن المعاد استخدامه |
| E5 | Geography Filters في القوائم | C3/D1 | **PARTIAL** | لا regression في البحث والفلاتر بعد التجميع |

## المجموعة F — PROGRAMS / COURSES  → **AGENT 2**

الأساس موجود: ProgramFilamentResource وCourseFilamentResource وLevelFilamentResource
كلها CRUD كامل (index/create/edit/view) مع صفحات مسجلة فعليًا في getPages.

| ID | الصفحة | Route | Status | Notes |
|---|---|---|---|---|
| F1 | Programs List | `/admin/program-filaments` | **FUNCTIONAL** | — |
| F2 | Create Program | `.../create` | **FUNCTIONAL** | — |
| F3 | Program Details | `/admin/program-filaments/{record}` | **PARTIAL** | يجب أن تعرض: basic info · fixed/ongoing · dates · age rules · gender · geography eligibility · courses · levels · groups · teachers · admission rules |
| F4 | Edit Program | `.../{record}/edit` | **FUNCTIONAL** | — |
| F5 | Courses List | `/admin/course-filaments` | **FUNCTIONAL** | — |
| F6 | Create Course | `.../create` | **FUNCTIONAL** | إنشاء داخل فئة برنامج قائمة (scope §4.3) |
| F7 | Course Details | `.../{record}` | **PARTIAL** | يجب أن تعرض: description · objectives · curriculum/topics · categories · levels · individual/group · duration · عدد الحصص · language · eligible teachers · groups · recording configuration |
| F8 | Edit Course | `.../{record}/edit` | **FUNCTIONAL** | — |
| F9 | Categories / Specializations | تحقق Agent 2 من وجود taxonomy في الـdomain | **MISSING?** | إن لم يوجد أساس، يبلّغ قبل اختراع نطاق جديد |
| F10 | Levels / Tracks | `/admin/level-filaments` | **FUNCTIONAL** | مع reorder API موجود |

## المجموعة G — GROUPS  → **AGENT 2**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| G1 | Groups List | `/admin/groups` | ListGroups | GroupResource (CRUD+View) | group.view | جدول + فلاتر + TrashedFilter | **FUNCTIONAL** | Groups tests | — |
| G2 | Create Group | `.../create` | CreateGroup | form + علاقات | group.manage | صف تشغيلي مستقل many-to-many | **FUNCTIONAL** | Groups tests | — |
| G3 | Group Details Hub | `.../{record}` | ViewGroup | — | group.view | يعرض Program/Course/Level/Students/Teachers/Capacity/Schedule/Upcoming/Status | **PARTIAL** | لا يوجد خاص | أكمل المحتوى والعلاقات |
| G4 | Edit Group | `.../{record}/edit` | EditGroup | form | group.manage | تعديل | **FUNCTIONAL** | Groups tests | — |
| G5 | Actions داخل G3: Add/Remove Student · Assign/Change Teacher · Schedule Sessions | داخل G3 | recordActions جزئية | APIs موجودة (group students/teachers) | group.manage / enrollment.create | إدارة عضوية الصف | **PARTIAL** | Groups tests | كل زر يُربط بمساره الحقيقي |



## المجموعة H — SCHEDULING / SESSIONS  → **AGENT 3**

أهم مجموعة. الواقع المثبت بعد شريحة Sessions: لا يوجد تعديل CRUD عام للحصة؛
كل تغيير حالة يمر عبر Action وآلة الحالات وسبب وAudit. القائمة والتقويم ومركز
التشغيل موجودة، وتكامل Attendance وVirtualClassroom وRecordings وNotifications
أصبح داخل المركز. بقي إنشاء الحصة المنفردة ومعاينة التكرار والفحص البصري.

| ID | الصفحة | Route | Status | Notes |
|---|---|---|---|---|
| H1 | Main Calendar | `/admin/sessions/calendar` | **TESTED** | أسبوع/شهر وفلاتر وأسماء فعلية للمجموعة والمعلم؛ بقي QA بصري |
| H2 | Sessions List | `/admin/sessions` | **TESTED** | مقرر/مجموعة/معلم وعدد مشاركين بأسماء فعلية، وفلاتر حالة/تاريخ/معلم |
| H3 | Create Session | عبر API فقط حاليًا | **PARTIAL** | صفحة إنشاء Filament فوق منطق API الحالي + منع التعارض |
| H4 | Create Recurring Sessions | `/admin/schedules/create` | **TESTED** | هدف مجموعة/طالب، كورس، معلم، أيام، وقت محلي، مدة ونطاق؛ يولد Sessions ومشاركين عبر عقد Sessions |
| H5 | Recurrence Preview | — | **MISSING** | معاينة الحصص الناتجة قبل الحفظ |
| H6 | Session Details (Operations Hub) | `/admin/sessions/{record}` | **TESTED** | يعرض Program · Course · Group · Students · Original/Actual Teacher · timezone · Status · Attendance · Classroom lifecycle/health/events · Recording status/grants/views · substitutions · apologies · notifications · history/Audit |
| H7 | Edit Session | لا يوجد عمدًا | **N/A** | أزيل مسار CRUD المكسور؛ التعديل حصريًا عبر إجراءات الحالات وإعادة الجدولة والبديل بسبب وتدقيق |
| H8 | Reschedule Session | داخل H6 | **TESTED** | يثبت مدة الأصل، يمنع التعارض، ينسخ المشاركين، ويكتب history/Audit |
| H9 | Conflict Display | داخل create/edit للقالب | **FUNCTIONAL** | يمنع تعارض المعلم والمجموعة والطالب؛ رسالة BusinessRuleViolation، وتبقى معاينة التعارض قبل الحفظ تحسينًا بصريًا |
| H10 | Substitute Assignment | داخل H6 والقائمة | **TESTED** | picker يقرأ التأهيل والنشاط والإجازة من عقود Staff ويعرض التعارض؛ التجاوز يحتاج سببًا وصلاحية |
| H11 | Substitute History | ضمن H6 | **FUNCTIONAL** | الأصل والمنفذ والسبب والتجاوز ووقته بأسماء فعلية؛ بقي ضمه إلى ملف المعلم D12 |

## المجموعة I — TEACHER APOLOGY / SUBSTITUTE  → **AGENT 3**

القرار الحاكم: اعتماد الاعتذار **لا يلغي الحصة** — يفتح مسار اختيار بديل،
والمعلم الأصلي يظل محفوظًا والمنفذ الفعلي واضح.

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| I1 | Admin Postponement Requests | `/admin/postponement-requests` | قائمة مؤسسية بأسماء الحصة والطالب وحالة «على من الدور» | Scheduling | session.postpone.approve | قائمة الطلبات والتصعيد الشهري | **TESTED** | SchedulingOperationsTest | QA بصري |
| I2 | Postponement Details + Decision | `/admin/postponement-requests/{record}` | ViewPostponementRequest | Actions عبر SessionSchedulingGateway | session.postpone.approve | اعتماد/رفض/اقتراح بديل بسبب وتدقيق وإنشاء تلافي | **TESTED** | SchedulingOperationsTest | قبول البديل من بوابة الطالب وjob انتهاء SLA متبقيان |
| I3 | Substitute Candidate Picker | ضمن H10 | picker تشغيلي | مؤهلات وتوافر وإجازات وتعارضات | session.assign_substitute | تجاوز إداري بسبب، مع حفظ الأصلي والمنفذ | **FUNCTIONAL** | TeacherApologyFlowTest | QA بصري |
| I4 | Submit Apology (Teacher Portal) | غير موجودة | — | SubmitTeacherApologyAction كامل ومدقق | session.postpone.request | الاعتذار لا يلغي الحصة؛ بوابة المعلم متبقية | **PARTIAL** | TeacherApologyFlowTest | بوابة المعلم N9 |
| I5 | My Apology Requests (Teacher Portal) | غير موجودة | — | نفس المصدر | own scope | متابعة حالة الطلبات | **MISSING** | — | — |

## المجموعة J — BBB / CLASSROOM UI  → **AGENT 3** (ضمن Session Details)

لا dashboard مستقل. كل ما يلزم يعيش داخل H6:

| ID | العنصر داخل H6 | Backend | Status | Notes |
|---|---|---|---|---|
| J1 | Create classroom / Retry creation | VirtualClassroomProvider + BigBlueButtonProvider | **TESTED** | pending/provisioned/running/ended/failed، محاولات وخطأ أخير وتدقيق؛ زر إعادة المحاولة وتجهيز آلي قبل الموعد بـ20 دقيقة |
| J2 | Join links (teacher/student/supervisor) | أدوار BBB: moderator/attendee/observer حسب الصلاحية | **FUNCTIONAL** (البوابات تعرض joinUrl بشروط canJoin) | التحقق أن admin/supervisor يحصلان observer/moderate حسب docs |
| J3 | Guest link generation + revoke | guest tokens بمهل وانتهاء | **PARTIAL** | classroom.guest.invite/revoke موجودتان في المصفوفة؛ UI مطلوب |
| J4 | Meeting status + Recording status | classroom events + recordings | **TESTED** | حالة الفصل وصحته وأحداثه وأسماء المشاركين، وحالة التسجيل ومدته ومنحه ومشاهداته داخل H6 |

## المجموعة K — RECORDINGS  → **AGENT 7**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| K1 | Recordings List | `/admin/recordings` | قائمة قراءة فقط بالحصة والكورس والمعلم والحالة والمدة والمنح والمشاهدات | RecordingAdministrationQueries | recording.view.any | لا إنشاء/تعديل CRUD يدوي | **TESTED** | Recordings tests | QA بصري فقط |
| K2 | Recording Details | `/admin/recordings/{record}` | مركز تشغيل: سياق الحصة، مؤشرات، منح، مشاهدة، Audit | RecordingOperationsQueryService | recording.view / view.any | لا يعرض path أو أسرار المزوّد | **TESTED** | RecordingOperationsHubTest | QA بصري فقط |
| K3 | Recording Player/View | `/recordings/{recording}/watch` | redirect محمي | رابط موقّع مؤقتًا + RecordingAccessCoordinator + recording_views | recording.view + grant/assignment | رفض الرابط غير الموقّع، عزل مؤسسة، معلم الحصة أو منحة طالب نشط | **TESTED** | RecordingOperationsHubTest | اختبار رابط موقّع وتسجيل مشاهدة |
| K4 | Recording Access Grants | داخل K2 | منح لمستخدم أو مجموعة + إلغاء | Grant/Revoke Actions | recording.grant | تحقق المؤسسة، منع التكرار، انتهاء لا يتجاوز عمر التسجيل، سبب وAudit | **TESTED** | RecordingOperationsHubTest | — |
| K5 | Grant Access Modal | ضمن K2 | بحث مستخدم أو مجموعة ومدة وسبب | نفس المصدر | recording.grant | اختيار متسلسل ولا معرّفات خام | **TESTED** | render test | QA بصري فقط |
| K6 | Recording status/history | ضمن K2 وH6 | status/retention/views/downloads/audit | lifecycle actions + hourly retention command | recording.view.any / recording.delete | أرشفة ثم انتهاء فعلي، soft delete بسبب | **TESTED** | Recordings tests | — |

صلاحيات العميل المطبقة: Admin كامل · Supervisor حسب منح · Teacher حصته فقط
بلا تنزيل افتراضيًا · Student/Guardian بلا وصول افتراضيًا.

## مجموعة التعلم — ASSIGNMENTS

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| AS1 | Assignments List | `/admin/assignments` | Filament list بأسماء ومؤشرات | AssignmentAdministrationQueryService | assignment.manage / grade | مقرر/مجموعة/معلم/حالة/مستهدفون/بانتظار التصحيح | **CLIENT_READY** | AssignmentOperationsHubTest + browser RTL | زر الإنشاء مثبت وفلاتر المقرر والتأخير موجودة |
| AS2 | Create Assignment | `/admin/assignments/create` | برنامج ← مقرر ← مجموعة ← معلم | CreateAssignmentAction | assignment.manage | تحقق المؤسسة والأهلية ثم roster ذري للطلاب النشطين | **CLIENT_READY** | happy + invalid teacher + browser cascade | لا كتابة مباشرة في جدول التسليمات |
| AS3 | Assignment Operations Hub | `/admin/assignments/{record}` | ملخص ومؤشرات وتسليمات وAudit | query contracts + DTOs | policy object scope | أسماء حقيقية، حالة تشغيلية، عدم تسليم وتأخير وتصحيح | **CLIENT_READY** | render + browser realistic record | فُحص واجب عرض لطالبين بلا Console errors |
| AS4 | Edit / Archive | داخل AS3 | نماذج سبب | Update/Archive Actions | assignment.manage | قفل الجمهور بعد عمل الطالب وSoftDelete مع منع غير المصحح | **TESTED** | AssignmentOperationsHubTest | لا DeleteAction مباشر |
| AS5 | Submit / Grade | بوابة الطالب + relation manager | Inertia submit + grade modal | Submit/Grade Actions | submit / grade | late server-side، raw score، penalty، final score، Audit | **TESTED** | Assignments + Task04 + PortalWriteRoutes | إشعارات الأحداث متكاملة |



## المجموعة L — NOTIFICATIONS  → **AGENT 7**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| L1 | Notification Bell (portals) | مشترك في Layout | `NotificationBell.tsx` | unread-count خلف Sanctum stateful | auth | عداد غير المقروء وفتح المركز | **DONE** | NotificationOperationsHubTest + browser RTL | حالات التحميل/الخطأ/الفراغ موحدة |
| L2 | Notification Center (user side) | `/{student,teacher,guardian}/notifications` | `Shared/Notifications.tsx` | list + mark-one + mark-all | auth | قائمة مقروء/غير مقروء، pagination وروابط عمق | **DONE** | NotificationOperationsHubTest + browser RTL | نفس المكوّن للبوابات الثلاث |
| L3 | Notification Deep link | ضمن L2 | resolver مركزي | أهداف session/student/teacher/assignment/program | auth + target permission | رابط داخلي آمن أو null | **TESTED** | NotificationOperationsHubTest | لا يقبل URL من payload مباشرة |
| L4 | Delivery Log (admin) | `/admin/notification-outboxes` | قائمة تشغيل بأسماء المستلمين | Outbox + attempts + administration query | system.alerts / audit.view | القناة والحالة والمحاولات والخطأ والمستلم | **TESTED** | Notifications 103/523 | بقي QA بصري للإدارة |
| L5 | Delivery Details | `/admin/notification-outboxes/{record}` | ViewNotificationOutbox | المحتوى المحلي والمحاولات وAudit | نفسها | تفاصيل كل محاولة والخطأ والهدف | **TESTED** | NotificationOperationsHubTest | — |
| L6 | Failed Notifications | فلتر داخل L4 | فلتر فعلي | status=failed | نفسها | قائمة الفاشل | **TESTED** | Notifications 103/523 | — |
| L7 | Manual Retry / Cancel | Actions داخل L4/L5 | نماذج سبب | Retry/Cancel actions | notifications.retry | انتقال مدقق قبل/بعد بسبب مكتوب | **TESTED** | NotificationOperationsHubTest | لا كتابة CRUD مباشرة |
| L8 | Notification Settings (user prefs) | `/admin/notification-preferences` | NotificationPreferenceResource (CRUD افتراضي) | preferences API | مالك الحساب | تفضيلات القنوات وساعات الهدوء | **PARTIAL** | جزئي | التأكد أن التعديل محصور بالمالك |

## المجموعة M — STUDENT PORTAL  → **AGENT 4**

الموجود يعمل ببيانات حقيقية عبر `PortalData` ولا يُعاد كتابته إلا لنقص:

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| M1 | Dashboard | `/student` | Student/Dashboard.tsx | StudentDashboardController (nextSession/weekSessions/attendanceRate/openAssignments) | session.view | لوحة الطالب | **TESTED** | PortalRoutesTest | إثراء ببطاقات Programs/Group لاحقًا من C |
| M2 | My Profile | غير موجودة | — | api/identity/me + PATCH + password endpoints جاهزة | own | عرض/تعديل بياناته وكلمة مروره | **--FUNCTIONAL--** | ProfileEndpointsTest للـAPI | صفحة Inertia فوق APIs قائمة |
| M3 | My Programs | غير موجودة | — | enrollment queries | ◐own | برامج الطالب وحالتها | **FUNCTIONAL** | — | — |
| M4 | My Group | غير موجودة | — | membership queries | ◐own | مجموعته وزملاؤه ومدرسوه | **FUNCTIONAL** | — | — |
| M5 | Schedule | `/student/schedule` | Student/Schedule.tsx | StudentScheduleController | schedule.view | جدوله | **TESTED** | PortalRoutesTest | — |
| M6 | Session Details | `/student/sessions/{id}` | Student/Sessions/Show.tsx (join logic موجود) | StudentSessionController + classroom join | session.view + session.join | تفاصيل ودخول BBB بشروط النافذة | **FUNCTIONAL** | PortalRoutesTest | إكمال حالة الخطأ عند تعذر الدخول |
| M7 | My Availability | غير موجودة | — | تحقق من نموذج إتاحة الطالب أولًا | own | إتاحته الذاتية | **MISSING** | — | إن لم يوجد أساس backend يُبلّغ (مربوط C11) |
| M8 | Notifications | `/student/notifications` + L1 | Shared/Notifications.tsx | مصادر L | auth | إشعاراته | **TESTED** | NotificationOperationsHubTest | الواجهة المشتركة نفسها المختبرة في بوابة المعلم |

## المجموعة N — TEACHER PORTAL  → **AGENT 5**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| N1 | Dashboard | `/teacher` | Teacher/Dashboard.tsx | TeacherDashboardController (todaysSessions/pendingAttendance/lateReports) | session.view | لوحة المعلم | **TESTED** | PortalRoutesTest | — |
| N2 | My Profile | غير موجودة | — | identity/me + staff profile APIs | own | ملفه | **FUNCTIONAL** | — | — |
| N3 | My Subjects | ضمن N2 | — | TeacherQualificationQueries | own | موادّه وتأهيله (قراءة فقط) | **FUNCTIONAL** | — | — |
| N4 | My Groups | غير موجودة | — | group-teachers queries | assigned | مجموعاته | **FUNCTIONAL** | — | — |
| N5 | My Students | غير موجودة | — | عبر مجموعاته فقط | ◐assigned | طلاب مجموعاته لا غير | **FUNCTIONAL** | — | صرامة النطاق assigned |
| N6 | Schedule | `/teacher/schedule` | Teacher/Schedule.tsx | TeacherScheduleController | schedule.view | جدوله | **TESTED** | PortalRoutesTest | — |



| N7 | Session Details (Teacher) | `/teacher/sessions/{id}` | Teacher/Sessions/Show.tsx | TeacherSessionController + attendance/report APIs | attendance.record ◐assigned | Join BBB · Students · Attendance · Session report · Recording link حسب permission | **FUNCTIONAL** | PortalRoutesTest + Sessions tests | إكمال حالات الفراغ والخطأ |
| N8 | Availability | غير موجودة | — | staff availability API موجودة | own | إدارة إتاته وطلبات اعتمادها | **FUNCTIONAL** | Staff tests جزئي | — |
| N9 | Submit Apology | غير موجودة | — | postpone request API | session.postpone.request ◐assigned | طلب اعتذار بالسبب (لا يلغي الحصة) | **MISSING** | — | مربوط I4 |
| N10 | My Apology Requests | غير موجودة | — | نفس المصدر own scope | own | متابعة طلباته وقراراتها | **MISSING** | — | مربوط I5 |
| N11 | Notifications | `/teacher/notifications` + L1 | Shared/Notifications.tsx | مصادر L | auth | إشعاراته | **DONE** | Automated + browser RTL | فحص فعلي للحالة الفارغة والجرس بلا أخطاء console |

## المجموعة O — GUARDIAN PORTAL  → **AGENT 6**

| ID | الصفحة | Route | Frontend | Backend | Permissions | Business Logic | Status | Automated Test | Notes |
|---|---|---|---|---|---|---|---|---|---|
| O1 | Dashboard | `/guardian` | Guardian/Dashboard.tsx | GuardianDashboardController (children/selectedChild) | student.view ◐children | أبناؤه المرتبطون الموثقون فقط | **TESTED** | PortalRoutesTest (عبر مؤسسات يرجع 404) | — |
| O2 | Children List | ضمن O1 | نفس الصفحة | guardian_links verified فقط | student.view children | قائمة الأبناء | **FUNCTIONAL** | PortalRoutesTest | — |
| O3 | Child Overview | غير موجودة كصفحة مستقلة | — | تجميع حالة الابن | ◐children | نظرة شاملة لابن | **PARTIAL** | — | تبويب أو صفحة فرعية |
| O4 | Child Attendance | `/guardian/children/{studentId}/attendance` | Guardian/Child/Attendance.tsx | GuardianAttendanceController | attendance.view children | حضور الابن | **TESTED** | PortalRoutesTest | — |
| O5 | Child Reports | `/guardian/children/{studentId}/reports` | Guardian/Child/Reports.tsx | GuardianReportsController | session_report.view children | تقارير الابن | **TESTED** | PortalRoutesTest | — |
| O6 | Child Schedule | غير موجودة | — | schedule queries children | schedule.view children | جدول الأبناء إن سمحت المصفوفة | **PARTIAL** | — | مسموح دائمًا وفق docs/06 §4 |
| O7 | Notifications | `/guardian/notifications` + L1 | Shared/Notifications.tsx | مصادر L | auth | إشعاراته | **TESTED** | NotificationOperationsHubTest | نفس التنفيذ المشترك مع عزل الحساب |

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


---

## 1. توزيع الوكلاء وترتيب التنفيذ

| Agent | النطاق | المهام الصغيرة بالترتيب |
|---|---|---|
| **8** | Dashboard B1 + Navigation contract + UI QA شاملة | 8.0 نشر Navigation contract أولًا (قبل 4/5/6/7) → 8.1 بطاقات B1 قابلة للنقر + Quick actions + ترجمة widgets → 8.2 جولة RTL/responsive/states/translations موحدة |
| **3** | H + I + J | 3.1 إصلاح EditAction المكسور في SessionResource فورًا → 3.2 Main Calendar → 3.3 Create/Recurring/Preview/Conflicts → 3.4 Session Operations Hub + J → 3.5 Apology admin + Substitute picker |
| **1** | C + D + E | 1.1 Geography Selectors في كل النماذج → 1.2 Countries/Regions admin → 1.3 Create Teacher + Teacher Hub Tabs → 1.4 Student Hub Tabs → 1.5 Assign Student + Freeze flow → 1.6 Qualifications عرض |
| **2** | F + G | 2.1 Program Details كامل → 2.2 Course Details كامل → 2.3 Categories تحقق وبناء → 2.4 Group Hub + Actions |
| **4** | M Student Portal | فوق APIs الجاهزة فقط؛ لا يعيد كتابة الموجود |
| **5** | N Teacher Portal | نفس القاعدة |
| **6** | O Guardian + A Public/Auth | إصلاحات A5/A7 ثم O3/O6/O7 |
| **7** | L + K + P | Notifications (bell مشترك أولًا) → Recordings → Messaging UX |
| **9** | اختبارات صفحات آلية | يتبع كل تسليم: happy + forbidden لكل صفحة جديدة |

## 2. خريطة ملكية الملفات — منع التضارب

**ملفات مشتركة محرّمة على الجميع إلا صاحبها:**

| الملف/المسار | المالك الوحيد |
|---|---|
| `resources/js/Layouts/AppLayout.tsx` (بنية nav) | AGENT 8 |
| `app/Filament/Widgets/*` + صفحة Dashboard | AGENT 8 |
| `docs/page-completion-matrix.md` | Claude (يدمج تحديثات الوكلاء يدويًا) |
| `resources/js/lib/i18n.ts` · `types/index.d.ts` | Claude فقط |
| `routes/web.php` | Claude (الوكلاء يقترحون diff في سجل التسليم) |

**ملكية حصرية لكل وكيل:**
- AGENT 1: `modules/{Students,Staff,Organization}/...Filament/**` + صفحاتها
- AGENT 2: `modules/{Academics,Groups}/...Filament/**`
- AGENT 3: `modules/{Sessions,Scheduling,VirtualClassroom}/...Filament/**`
- AGENT 4: `resources/js/Pages/Student/**` + متحكمات Portal/Student*
- AGENT 5: `resources/js/Pages/Teacher/**` + متحكمات Portal/Teacher*
- AGENT 6: `resources/js/Pages/Guardian/**` + `Pages/Auth/**` (عدا ما كتبه غيره) + Portal/Guardian*
- AGENT 7: `modules/{Notifications,Recordings,Messaging}/...Filament/**` + مكوّن Notification المشترك `resources/js/Components/NotificationBell.tsx`
- AGENT 9: `tests/Feature/PageCompletion/**` فقط

**قواعد تصادم:**
1. حاجة عابرة للملكية (مثل إضافة route أو permission) = تُسجّل في سجل التسليم ولا تُنفّذ؛ أدمجها أنا.
2. تعديل ملف مالكه وكيل آخر = ممنوع؛ يُفتح بند تنسيق لي.
3. `resources/lang/{ar,en}/portal.php`: كل وكيل يضيف مفاتيحه تحت prefix مساحته (`student.*`, `teacher.*`, `guardian.*`, `auth.*`, `notifications.*`...) في commit منفصل عن الكود لتقليل التعارض، وأنا أحل أي تداخل عند الدمج.

## 3. UI Contracts — نظام تصميم واحد

- **Layouts:** `AppLayout` للبوابات، `GuestLayout` للمجهول، Filament panel للإدارة. ممنوع layout جديد.
- **مكونات معاد استخدامها:** Button · Card · DataTable · StatusPill · Badge · PageHeader · LoadingState · EmptyState · ErrorState (+ NotificationBell جديد لـAgent 7).
- **كل نص عبر `t()`** بمفاتيح portal.php — نص hardcoded = رفض تسليم.
- RTL-first، ومتوافق LTR، والعرض من 375px فما فوق.
- حالات الصفحة الثلاث إلزامية: Loading (skeleton/spinner) · Empty (EmptyState بنص ودعوة فعل) · Error (ErrorState مع retry).
- Feedback: نجاح/فشل كل action مرئي (toast/inline) — لا زر صامت.
- Forms: validation errors من السيرفر تُعرض تحت الحقول بلغة المستخدم.

## 4. قاعدة Backend Gap

زر بلا Action حقيقي = صفحة غير مكتملة. إذا احتاجت الصفحة action غير موجود:
نفّذ الحد الأدنى الصحيح (controller endpoint + policy + اختبار) داخل موديول المالك،
ولا تحوّله إلى تطوير موديول كامل أو mock أو placeholder. إن اكتشفت أن الأساس
domain غير موجود أصلًا (مثل إتاحة الطالب) — **توقف وبلّغ** بدل اختراع جدول.

## 5. بروتوكول تسليم الوكيل

لكل مهمة صغيرة، الوكيل:
1. ينفذ داخل ملكيته فقط.
2. يشغّل: الاختبارات المتأثرة عبر `scripts/test-isolated.php` + جولة HTTP حية للصفحة.
3. يكتب `docs/page-matrix-updates/AGENT-N.md` (إلحاقي): لكل صفحة — Route، وصف يدوي، Actions، Permissions، Automated tests، Known issues.
4. يقترح أسطر تحديث المصفوفة (ID → Status جديد).
5. **لا DONE بدون اختبار.** FUNCTIONAL→TESTED→DONE فقط بدليل.

أنا بعد كل تسليم: أعيد تشغيل الأدلة، أفحص الحدود، أدمج المصفوفة، أقبل أو أرفض.

## 6. ملخص عددي للحالة الراهنة (قبل بدء الوكلاء)

من 119 صفحة مطلوبة في المصفوفة:

| Status | العدد |
|---|---|
| TESTED | 11 |
| FUNCTIONAL | 22 |
| PARTIAL | 30 |
| SKELETON | 2 |
| MISSING | 53 |
| MISSING? (تحقق Agent) | 1 |

الأثقل: AGENT 3 (~16) وAGENT 1 (~19) وAGENT 5 (~9).

## 7. بوابة الإقفال النهائية

- 100% من الصفحات REQUIRED بحالة DONE.
- الصفحات الحرجة (A*, B1, C2, G*, H*, I*, K3, M*, N7, O*) تحقق يدوي موثق.
- `docs/page-completion-report.md`: Total/DONE/TESTED/PARTIAL/MISSING + نسبة Page coverage = DONE / REQUIRED.
- بعدها: توقف. لا Audit شامل ولا Phase 2.

---

## 8. تحديث 2026-08-24 — إكمال صفحات الطالب والمعلم وربط مسارات الكتابة

> هذا القسم يعدّل حالات المجموعتين M وN أعلاه. عند التعارض، هذا القسم هو الأحدث.

### 8.1 السبب الجذري الذي كان يعطّل كل كتابة من البوابات

مجموعة الوسائط `api` في هذا المشروع تحتوي على `SubstituteBindings` فقط — بلا
`StartSession` وبلا كوكيز. ومسارات الموديولات كلها خلف `auth:sanctum`، وحارس
Sanctum يسأل حارس `web` أولًا، فيجده فارغًا لغياب الجلسة. النتيجة: **كل نموذج
كتابة في البوابات كان يُرفض 401**، حتى لو ظهر الزر يعمل في الواجهة. ويزيد الأمر
أن تلك المسارات تُرجع JSON لا تفهمه Inertia أصلًا.

الحل المعتمد: مسارات كتابة داخل `web` تستدعي **نفس Application Actions ونفس
FormRequests**، وتُرجع `redirect` مع `flash`. لم تُنسخ قاعدة عمل واحدة، ولم
يتغيّر عقد الـAPI لعملائه.

### 8.2 مسارات الكتابة الجديدة

| المسار | المتحكم | الإجراء المستدعى |
|--------|---------|-------------------|
| `POST /student/assignments/{assignment}/submit` | `StudentAssignmentSubmissionController` | `SubmitAssignmentAction` |
| `PATCH /student/profile` · `PATCH /teacher/profile` | `PortalProfileController@update` | `UpdateUserProfile` |
| `PUT /student/profile/password` · `PUT /teacher/profile/password` | `PortalProfileController@password` | `UpdatePassword` |
| `POST /teacher/availability` | `TeacherAvailabilityWriteController@store` | `SetTeacherAvailability` |
| `DELETE /teacher/availability/{availability}` | `TeacherAvailabilityWriteController@destroy` | `RemoveTeacherAvailability` (جديد) |

**حماية النطاق:** مسار البوابة **يشتق الملف من الجلسة ويتجاهل أي قيمة مرسلة**.
أما `POST /api/staff/availability` فيتحقق من المؤسسة، ولا يسمح للمعلم بالكتابة
إلا على ملفه الشخصي؛ ويقتصر إنشاء إتاحة لمعلم آخر داخل المؤسسة على من يملك
`staff.view.any`. الاختبارات تثبت المسارين ومنع العبور بين المؤسسات.

### 8.3 الحالات المحدَّثة

| ID | الصفحة | الحالة السابقة | الحالة الآن |
|----|--------|----------------|--------------|
| M2 | My Profile (student) | هيكل 17 سطرًا للعرض فقط | **TESTED** — عرض + تعديل الحساب + تغيير كلمة المرور |
| M3 | My Programs | هيكل 6 أسطر | **FUNCTIONAL** — الحالة والمستوى والتواريخ وسبب التجميد وموعد العودة |
| M4 | My Group | هيكل 4 أسطر لمجموعة واحدة | **FUNCTIONAL** — كل المجموعات مع المعلمين والبرامج وزملاء الصف والحصة القادمة |
| M-new | Assignments submission | عرض فقط | **TESTED** — تسليم فعلي مع احتساب التأخير خادميًا |
| N2 | My Profile (teacher) | هيكل 3 أسطر | **TESTED** — الملف + المؤهلات + ملخص الإتاحة + تعديل الحساب وكلمة المرور |
| N3 | My Subjects | ضمن N2 بلا بيانات | **FUNCTIONAL** — `teacher_courses` مع الدورة والبرنامج وتاريخ الاعتماد |
| N4 | My Groups | هيكل 3 أسطر | **FUNCTIONAL** — السعة والدور والبرامج والحصة القادمة ورابطها |
| N5 | My Students | هيكل 3 أسطر | **FUNCTIONAL** — بحث وفلترة بالمجموعة ونسبة حضور وتكليفات غير مسلَّمة |
| N8 | Availability | نموذج مصغّر بلا حذف ولا تحقق | **TESTED** — إضافة وحذف ومنع حذف المعتمدة وحصر النطاق على الملف الذاتي |

### 8.4 عيوب إنتاجية اكتُشفت وأُصلحت أثناء العمل

1. **`AssignmentSubmission` كان يشير إلى `Enums\AssignmentSubmissionStatus`**
   داخل namespace الـModels، فيتحوّل إلى
   `Modules\Assignments\Domain\Models\Enums\...` غير الموجود. النتيجة:
   `Call to undefined cast` عند أي تسليم — **عبر الـAPI أيضًا لا البوابة فقط**.
2. **`assignment_submissions.attachments` عمود NOT NULL بلا default**، و
   `firstOrCreate` في مساري التسليم لا يمرره، فكل إنشاء صف يسقط على مستوى
   قاعدة البيانات. أُصلح على مستوى النموذج ليُغطى كل مسارات الإنشاء.
   **العيبان معًا يعنيان أن تسليم التكليفات لم يعمل قط.**
3. **`staff::errors` كان يعرّف مفتاحًا واحدًا من 17 مستخدمًا**، فكل مخالفة قاعدة
   عمل في Staff تظهر للمستخدم كمفتاح خام — مخالفة صريحة لقاعدة اللغة في
   `AGENTS.md`. أُضيفت المفاتيح الـ17 بالعربية والإنجليزية.
4. **`BusinessRuleViolation` كان يُعاد دائمًا كـJSON 422** حتى لطلبات الويب،
   فتظهر لمستخدم Inertia ككتلة JSON خام. صار الطلب الذي لا ينتظر JSON يعود
   `back()` برسالة الخطأ، مع بقاء عقد 422 لعملاء الـAPI كما هو.
5. ثمانية أخطاء TypeScript سابقة كانت تُسقط `npm run types` (StatusPill بلا
   `colorMap`، استيرادات غير مستخدمة، و`header` غير معرّف على `AppLayout`).
   `tsc --noEmit` يمر الآن نظيفًا تمامًا.

### 8.5 أدلة القبول

| الفحص | النتيجة |
|-------|---------|
| `tests/Feature/PortalWriteRoutesTest.php` (جديد) | **9 ناجحة · 44 توكيدًا** |
| `tests/Feature/PortalRoutesTest.php` | **22 ناجحة · 157 توكيدًا** |
| `tsc --noEmit` | **صفر أخطاء** |
| `vite build` | **ينجح** |
| Pint على الملفات المتغيّرة | **يمر** |
| تطابق مفاتيح الترجمة ar/en | **504 = 504، بلا فروق** |

## 9. تحديث 2026-08-24 — إنشاء الطالب الإداري وملفا العمليات

- صفحة `/admin/students/create` أصبحت Wizard يدعم **إنشاء شخص جديد بالكامل** أو
  ربط حساب موجود من المؤسسة نفسها. الرحلة كلها داخل معاملة واحدة، وتشمل الطلب
  والقبول وملف الطالب ودور `student`، مع سبب وتدقيق.
- أُزيلت الكتابة المباشرة من Filament في `group_memberships`. التسكين المتسلسل
  (برنامج → كورس → مجموعة متاحة) يستدعي `AssignStudentToGroupAction` ويطبّق
  الأهلية والسعة وتأهيل المعلم والقيد والتدقيق نفسها المستخدمة في الـAPI.
- ملف الطالب يعرض الحساب والقيود والمجموعات والحصص، وملف المعلم يعرض الحساب
  والمؤهلات والمجموعات والتوافر والحصص. التجميع يتم في طبقة التطبيق عبر عقود
  Query تعيد DTOs، من دون joins أو Eloquent Models عابرة لحدود الموديولات.
- الأدلة: **15 اختبارًا ناجحًا و123 توكيدًا** للمسارات المتأثرة، وPHPStan الموجّه
  بلا أخطاء، واختبار حدود `AccessControl` بعد التصحيح **3 ناجحة و112 توكيدًا**.
- المتبقي المقصود: نموذج إتاحة الطالب غير موجود في المجال؛ وتاريخ بدلاء/اعتذارات
  المعلم لم يُضم إلى الـHub في هذه الدفعة.

**إخفاقات سابقة لم تنتج عن هذا العمل** (أُثبت بإرجاع `bootstrap/app.php` إلى
HEAD وإعادة التشغيل، فبقيت فاشلة كما هي): `PublicStudentRegistrationTest`،
`PageCompletion/Comms` (٣)، `Task04AcceptanceTest`، وإخفاقا حدود الموديولات
`Notifications→Integrations` و`Reporting→Payroll`.

### 8.6 ما لم يُنجز بعد في هاتين البوابتين

- **إتاحة الطالب (M7)** — محجوبة بغياب جدول `student_availability` (الحزمة J-1).
- **صفحة تكليفات المعلم** (إنشاء ورصد الدرجات) — الـAPI موجود، الواجهة لا.
- **جدول المنهج داخل الصف** — الحزمة J-5.
- **كشف أجر المعلم** — الحزمة J-10 بعد ADR-017.
- **التذاكر** — الحزمة J-6.
- **صفحات المراسلات المشتركة** ما زالت تحمل نصوصًا عربية مكتوبة مباشرة في
  الكود، وهي مخالفة قائمة لقاعدة اللغة تحتاج موجة توطين مستقلة.
- `npm run lint` معطّل في المستودع: ESLint 9 يطلب `eslint.config.js` وهو غير
  موجود، فبوابة الـlint غير فاعلة أصلًا.
