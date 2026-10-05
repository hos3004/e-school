
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
