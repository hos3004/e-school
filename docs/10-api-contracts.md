# 10 — عقود الواجهة البرمجية

الواجهة البرمجية ليست منتجًا عامًا في المرحلة الأولى. هي عقد داخلي بين
الخادم وواجهات Inertia، وأساس تطبيق الموبايل لاحقًا.

---

## 1. المبادئ

| المبدأ | التطبيق |
|--------|---------|
| البادئة | `/api/v1/` — الإصدار في المسار لا في الترويسة |
| المصادقة | Sanctum — كوكي للجلسة على الويب · توكن للموبايل لاحقًا |
| الصيغة | JSON فقط · `Accept: application/json` إلزامية |
| التسمية | جمع بحروف صغيرة وشرطة: `/session-participants` |
| التوقيتات | ISO 8601 بـ UTC دائمًا: `2026-08-21T15:00:00Z` |
| المبالغ | كائن `{ "minor_units": 60000, "currency": "EGP" }` — **لا رقم عشري** |
| المعرّفات | ULID نصي |
| اللغة | `Accept-Language: ar` — والرد يترجم النصوص والأخطاء |
| التتبّع | `X-Correlation-Id` تُمرَّر وتُعاد في كل رد |

### مسارات الموديولات

كل موديول يعرّف مساراته في `modules/<Name>/routes/api.php`، وتُحمَّل
تلقائيًا ببادئة `api` من `ModuleRegistry`. لا مسار موديول في `routes/api.php` الجذري.

---

## 2. شكل الرد الموحد

### نجاح — عنصر واحد

```json
{
  "data": {
    "id": "01JBX7M2K9P4QW8N6R3T5V7Y2A",
    "type": "session",
    "attributes": { }
  },
  "meta": { "correlation_id": "01JBX..." }
}
```

### نجاح — قائمة مع ترقيم

```json
{
  "data": [ ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 340,
    "last_page": 14
  },
  "links": { "next": "/api/v1/sessions?page=2", "prev": null }
}
```

### خطأ

```json
{
  "error": {
    "code": "session.cancellation_notice_not_met",
    "message": "لا يمكن إلغاء الحصة قبل موعدها بأقل من 60 دقيقة.",
    "details": {
      "required_notice_minutes": 60,
      "actual_notice_minutes": 22
    }
  },
  "meta": { "correlation_id": "01JBX..." }
}
```

**`code` ثابت لا يُترجم** — الواجهة تتفرع عليه.
**`message` مترجَم** — للعرض المباشر.

### رموز الحالة

| الرمز | المعنى |
|-------|--------|
| 200 / 201 | نجاح |
| 204 | نجاح بلا محتوى (حذف ناعم) |
| 401 | غير مسجَّل دخول |
| 403 | مسجَّل لكن بلا صلاحية |
| 404 | غير موجود **أو** خارج نطاق المستخدم (لا نكشف الوجود) |
| 409 | تعارض حالة (انتقال غير مسموح · تعارض مواعيد) |
| **422** | **خرق قاعدة عمل** — `BusinessRuleViolation` |
| 429 | تجاوز الحد |

> الفرق بين 409 و 422 مقصود: 409 يعني "الحالة الحالية تمنع"،
> و422 يعني "المدخل يخالف سياسة المدرسة".

---

## 3. المسارات الأساسية

### الحصص

```http
GET    /api/v1/sessions
GET    /api/v1/sessions/{id}
POST   /api/v1/sessions
PATCH  /api/v1/sessions/{id}
POST   /api/v1/sessions/{id}/confirm
POST   /api/v1/sessions/{id}/cancel
POST   /api/v1/sessions/{id}/assign-substitute
POST   /api/v1/sessions/{id}/finalize
GET    /api/v1/sessions/{id}/join            → رابط دخول الفصل
```

**`GET /sessions` — المُصفّيات القياسية:**

```
?from=2026-08-01&to=2026-08-31
&status=scheduled,confirmed
&teacher_id=01J...
&student_id=01J...
&group_id=01J...
&course_id=01J...
&program_id=01J...
&session_type=group
&sort=-scheduled_start
&page=1&per_page=25
&include=participants,attendance,classroom
```

**`POST /sessions/{id}/cancel`:**

```json
{ "reason": "ظرف طارئ" }
```
- 422 `session.cancellation_notice_not_met` لو أقل من ٦٠ دقيقة.
- 422 `feature.student_cancellation_disabled` لو الطالب هو الفاعل والمفتاح مطفأ.
- 409 `session.invalid_transition` لو الحالة لا تسمح.

**`POST /sessions/{id}/finalize`:**
- 409 `session.attendance_incomplete` لو مشارك بلا حضور معتمد.

**`GET /sessions/{id}/join`:**

```json
{ "data": { "join_url": "https://...", "expires_at": "...", "role": "viewer" } }
```
- 403 `classroom.join_window_closed` خارج النافذة.
- 403 `enrollment.frozen` لو قيد الطالب مجمّد.

### طلبات التأجيل

```http
POST   /api/v1/sessions/{id}/postponement-requests
GET    /api/v1/postponement-requests
POST   /api/v1/postponement-requests/{id}/approve
POST   /api/v1/postponement-requests/{id}/propose-alternative
POST   /api/v1/postponement-requests/{id}/reject
DELETE /api/v1/postponement-requests/{id}          → سحب الطلب
```

```json
POST /sessions/{id}/postponement-requests
{ "proposed_start": "2026-08-25T15:00:00Z", "reason": "ظرف عائلي" }
```
- 422 `postponement.notice_not_met` لو أقل من ١٥ دقيقة.
- 422 `postponement.monthly_limit_reached` عند تجاوز الحد الشهري.

### الحضور

```http
GET   /api/v1/sessions/{id}/attendance
PATCH /api/v1/sessions/{id}/attendance
POST  /api/v1/sessions/{id}/attendance/confirm
```

```json
PATCH
{
  "records": [
    { "student_id": "01J...", "status": "late", "override_reason": "عطل بالشبكة" }
  ]
}
```
- 422 `attendance.override_reason_required` لو خالف المشتق بلا سبب.

### القيود والانضباط

```http
GET   /api/v1/enrollments
POST  /api/v1/enrollments/{id}/pause
POST  /api/v1/enrollments/{id}/freeze
POST  /api/v1/enrollments/{id}/reactivation-requests
POST  /api/v1/reactivation-requests/{id}/decide
GET   /api/v1/enrollments/{id}/violations
```

- `POST /enrollments/{id}/pause` يتطلب `expected_return_date`.
- **لا يوجد مسار** ينقل القيد من `frozen` إلى `active` مباشرة.
  المسار الوحيد عبر `reactivation-requests`.

### المستحقات

```http
GET   /api/v1/payroll/periods
GET   /api/v1/payroll/periods/{id}
POST  /api/v1/payroll/periods/{id}/calculate
POST  /api/v1/payroll/periods/{id}/review
POST  /api/v1/payroll/periods/{id}/approve
POST  /api/v1/payroll/periods/{id}/pay
GET   /api/v1/payroll/periods/{id}/statements/{staffId}
POST  /api/v1/payroll/adjustments
POST  /api/v1/payroll/adjustments/{id}/approve
```

- **لا مسار** لتعديل أو حذف `payroll_entry`. غير موجود عمدًا.
- `POST /payroll/adjustments/{id}/approve` يرد 403
  `payroll.approver_must_differ` لو المعتمد هو المقترح.
- 422 `payroll.period_locked` لأي كتابة على فترة مقفلة.

### التسجيلات

```http
GET    /api/v1/recordings
GET    /api/v1/recordings/{id}/playback     → رابط موقّع مؤقت
POST   /api/v1/recordings/{id}/deletion-requests
```

- كل استدعاء لـ `playback` يكتب سطرًا في `recording_views` وسجل التدقيق.
- 403 `recording.expired` بعد انتهاء مدة الاحتفاظ.

---

## 4. الترقيم والفرز والتضمين

| المعامل | القاعدة |
|---------|---------|
| `page` · `per_page` | الافتراضي 25 · الأقصى 100 |
| `sort` | `-` للتنازلي: `sort=-scheduled_start,title` |
| `include` | علاقات محددة مسبقًا فقط — لا تضمين حر |
| `fields[type]` | تحديد الحقول لتقليل الحمولة |

**منع N+1:** كل `include` مسموح له `with()` معرَّف مسبقًا في المورد.
تضمين غير معرَّف يرد 400 `query.include_not_allowed`.

---

## 5. الحدود والحماية

| المسار | الحد |
|--------|------|
| `POST /login` | 5 محاولات / 15 دقيقة لكل IP+بريد |
| `GET /sessions/{id}/join` | 10 / دقيقة لكل مستخدم |
| القراءة عمومًا | 120 / دقيقة لكل مستخدم |
| الكتابة عمومًا | 40 / دقيقة لكل مستخدم |
| التصدير | 5 / ساعة لكل مستخدم |

كل عملية كتابة تقبل ترويسة `Idempotency-Key`؛ تكرار نفس المفتاح خلال
٢٤ ساعة يعيد نفس الرد بلا تنفيذ ثانٍ.

---

## 6. الويب هوك الواردة

```http
POST /webhooks/bigbluebutton     ← توقيع BBB
POST /webhooks/whatsapp          ← توقيع Meta
GET  /webhooks/whatsapp          ← تحقق hub.challenge
```

**قواعد إلزامية:**
1. التحقق من التوقيع **قبل أي معالجة**؛ الفشل يرد 401 بلا تفاصيل.
2. الرد 200 فورًا، والمعالجة في طابور.
3. المعالجة idempotent بمعرّف الحدث الخارجي.
4. الحمولة الخام تُحفظ للتشخيص لمدة ٣٠ يومًا.
5. المسارات معفاة من CSRF ومحمية بحد معدل مستقل.

---

## 7. البث اللحظي (Reverb)

| القناة | من يشترك | ما يُبث |
|--------|----------|---------|
| `private-user.{userId}` | صاحب الحساب | إشعاراته |
| `private-session.{sessionId}` | معلمها ومشاركوها | تغيّر الحالة · دخول وخروج |
| `private-group.{groupId}` | أعضاء المجموعة | حائط الصف |
| `private-org.{orgId}.admin` | صلاحية `system.alerts` | تنبيهات النظام |

كل قناة تتحقق من الصلاحية عبر Policy الموديول المالك في `routes/channels.php`.

---

## 8. التوثيق

- مواصفة OpenAPI 3.1 تُولَّد من الكود في `storage/api-docs/openapi.json`.
- كل مسار جديد يحتاج: مثال طلب · مثال رد ناجح · مثال خطأ واحد على الأقل.
- التوليد جزء من CI؛ اختلاف المواصفة عن الكود يُسقط البناء.
