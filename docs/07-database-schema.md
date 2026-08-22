# 07 — مخطط قاعدة البيانات

**PostgreSQL 17.** كل الجداول تتبع القواعد التالية بلا استثناء:

| القاعدة | التفصيل |
|---------|---------|
| المفتاح الأساسي | `id CHAR(26)` — ULID |
| الانتماء | `organization_id CHAR(26)` في كل جدول تشغيلي |
| التوقيتات | `TIMESTAMPTZ` بـ UTC · `created_at` · `updated_at` |
| الحذف | `deleted_at TIMESTAMPTZ NULL` على كل جدول بشري |
| المبالغ | `BIGINT` بالوحدة الصغرى + `CHAR(3)` للعملة |
| النصوص متعددة اللغات | `JSONB` بالشكل `{"ar": "...", "en": "..."}` |
| الحالات | `VARCHAR` مع `CHECK` يطابق قيم الـ enum |

---

## 1. الهوية والصلاحيات

```sql
organizations (
  id, name JSONB, slug, logo_path,
  default_timezone, default_currency CHAR(3), default_locale,
  supported_locales JSONB, week_starts_on,
  settings JSONB,                    -- تجاوزات config لكل مؤسسة
  feature_overrides JSONB,           -- تجاوزات مفاتيح الميزات
  created_at, updated_at
)

users (
  id, organization_id,
  name, email CITEXT UNIQUE, username CITEXT UNIQUE NULL,
  phone, phone_country CHAR(2),
  password, remember_token,
  locale, timezone,
  email_verified_at, phone_verified_at,
  two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at,
  avatar_path, status,               -- active | suspended | invited
  last_login_at, last_login_ip,
  created_at, updated_at, deleted_at
)
-- INDEX (organization_id, status)
-- INDEX (organization_id, email)

user_devices (
  id, user_id, device_name, platform, push_token,
  last_used_at, revoked_at, created_at
)

roles (id, organization_id, name, guard_name, is_system BOOLEAN, created_at)
permissions (id, name, guard_name, module, description JSONB)
role_has_permissions (role_id, permission_id)
model_has_roles (role_id, model_type, model_id)
```

> `roles` و `permissions` من حزمة `spatie/laravel-permission` مع إضافة
> `organization_id` و `module`. `is_system` يمنع حذف الأدوار الأساسية.

---

## 2. الأشخاص

```sql
student_profiles (
  id, organization_id, user_id UNIQUE,
  student_code UNIQUE,               -- رقم الطالب المعروض
  date_of_birth DATE,
  gender, nationality CHAR(2), country CHAR(2), city,
  preferred_language,
  joined_at DATE,
  notes TEXT,
  created_at, updated_at, deleted_at
)
-- INDEX (organization_id, student_code)
-- INDEX USING GIN (to_tsvector) على الاسم للبحث التقريبي

guardian_profiles (
  id, organization_id, user_id UNIQUE,
  national_id_last4, occupation, preferred_contact_channel,
  created_at, updated_at, deleted_at
)

guardian_links (
  id, guardian_profile_id, student_profile_id,
  relationship,                      -- father | mother | guardian | other
  is_primary BOOLEAN,
  can_act_for BOOLEAN,               -- ينوب عن الطالب في القرارات
  visible_sections JSONB,            -- ما يراه بالضبط
  verified_at, created_at, updated_at
)
-- UNIQUE (guardian_profile_id, student_profile_id)
-- INDEX (student_profile_id, is_primary)

staff_profiles (
  id, organization_id, user_id UNIQUE,
  staff_code UNIQUE, employment_type,   -- per_session | salaried | volunteer
  hired_at DATE, terminated_at DATE NULL,
  bio JSONB, specializations JSONB,
  created_at, updated_at, deleted_at
)

teacher_contracts (
  id, organization_id, staff_profile_id,
  basis,                             -- per_session | monthly_with_deductions
                                     -- | course_fixed | salaried
  effective_from DATE, effective_to DATE NULL,
  base_amount BIGINT NULL, currency CHAR(3),
  monthly_target_sessions INT NULL,
  target_admin_tasks INT NULL,
  target_training_sessions INT NULL,
  terms JSONB,
  created_at, updated_at
)
-- CHECK (effective_to IS NULL OR effective_to > effective_from)
-- EXCLUDE USING gist (staff_profile_id WITH =, daterange(effective_from, effective_to) WITH &&)
--   ← يمنع تداخل عقدين لنفس المعلم

teacher_rates (
  id, teacher_contract_id,
  scope,                             -- course | program | session_type | default
  program_id NULL, course_id NULL, session_type NULL,
  amount BIGINT, currency CHAR(3),
  effective_from DATE, effective_to DATE NULL,
  created_at
)
-- INDEX (teacher_contract_id, scope, effective_from)

teacher_availability (
  id, staff_profile_id,
  weekday SMALLINT,                  -- 0=الأحد
  start_time TIME, end_time TIME, timezone,
  effective_from DATE, effective_to DATE NULL,
  created_at
)

teacher_leaves (
  id, staff_profile_id, starts_at, ends_at,
  reason, status, approved_by, approved_at, created_at
)
```

---

## 3. البنية الأكاديمية

```sql
programs (
  id, organization_id, code UNIQUE, name JSONB, description JSONB,
  duration_weeks INT NULL, default_session_minutes INT,
  default_rate BIGINT NULL, currency CHAR(3),
  is_active BOOLEAN, sort_order INT,
  created_at, updated_at, deleted_at
)

levels (id, program_id, code, name JSONB, sort_order, created_at)

courses (
  id, organization_id, level_id, code UNIQUE, name JSONB,
  description JSONB, total_sessions INT NULL,
  completion_rules JSONB, is_active BOOLEAN,
  created_at, updated_at, deleted_at
)

course_materials (
  id, course_id, title JSONB, type,   -- pdf | video | link | worksheet
  disk, path, external_url, size_bytes,
  visible_from, visible_to, uploaded_by, created_at, deleted_at
)

groups (
  id, organization_id, code UNIQUE, name JSONB,
  capacity INT, timezone, status,     -- forming | active | completed | archived
  starts_on DATE, ends_on DATE NULL,
  created_at, updated_at, deleted_at
)
-- CHECK (capacity BETWEEN 1 AND 25)

group_programs (id, group_id, program_id, created_at)
-- UNIQUE (group_id, program_id)

group_teachers (
  id, group_id, staff_profile_id, course_id NULL,
  role,                               -- primary | assistant
  assigned_from DATE, assigned_to DATE NULL, created_at
)
-- UNIQUE (group_id, staff_profile_id, course_id)

group_memberships (
  id, group_id, student_profile_id,
  joined_at, left_at NULL, status,    -- active | waitlisted | left
  created_at
)
-- UNIQUE (group_id, student_profile_id) WHERE left_at IS NULL

enrollments (
  id, organization_id, student_profile_id, program_id,
  status,                             -- EnrollmentStatus
  applied_at, activated_at, completed_at, withdrawn_at,
  current_level_id NULL,
  frozen_at NULL, frozen_reason, freeze_type,  -- disciplinary | voluntary
  expected_return_date DATE NULL,
  created_at, updated_at, deleted_at
)
-- INDEX (organization_id, status)
-- UNIQUE (student_profile_id, program_id) WHERE deleted_at IS NULL

enrollment_status_history (
  id, enrollment_id, from_status, to_status,
  reason TEXT, changed_by, changed_at, metadata JSONB
)
```

---

## 4. الجدولة والحصص

```sql
schedules (
  id, organization_id, group_id NULL, student_profile_id NULL,
  course_id, staff_profile_id,
  session_type,                       -- individual | group | webinar
  rrule TEXT,                         -- RFC 5545
  start_time TIME, duration_minutes INT, timezone,
  starts_on DATE, ends_on DATE NULL,
  materialized_until DATE,
  is_active BOOLEAN, created_by, created_at, updated_at
)
-- CHECK (group_id IS NOT NULL OR student_profile_id IS NOT NULL)

sessions (
  id, organization_id, schedule_id NULL,
  group_id NULL, course_id, staff_profile_id,
  substitute_for_staff_id NULL,       -- المعلم الأساسي عند وجود بديل
  makeup_for_session_id NULL,         -- الحصة الأصلية عند حصة التلافي
  session_type, status,               -- SessionStatus
  scheduled_start TIMESTAMPTZ, scheduled_end TIMESTAMPTZ,
  actual_start TIMESTAMPTZ NULL, actual_end TIMESTAMPTZ NULL,
  time_range TSTZRANGE GENERATED ALWAYS AS
      (tstzrange(scheduled_start, scheduled_end, '[)')) STORED,
  title JSONB, notes TEXT,
  cancelled_by NULL, cancelled_at NULL, cancellation_reason TEXT,
  finalized_at NULL, finalized_by NULL,
  created_at, updated_at, deleted_at
)
-- INDEX (organization_id, status, scheduled_start)
-- INDEX (staff_profile_id, scheduled_start)
-- EXCLUDE USING gist (
--     staff_profile_id WITH =, time_range WITH &&
-- ) WHERE (status NOT IN ('cancelled_by_student','cancelled_by_teacher',
--                          'cancelled_by_school','postponed') AND deleted_at IS NULL)
--   ← منع ازدواج حجز المعلم على مستوى قاعدة البيانات، لا التطبيق وحده

session_status_history (
  id, session_id, from_status, to_status,
  reason TEXT, changed_by, changed_at, metadata JSONB
)

session_participants (
  id, session_id, student_profile_id, enrollment_id,
  join_url_token, invited_at,
  first_joined_at NULL, last_left_at NULL,
  attended_minutes INT DEFAULT 0,
  created_at
)
-- UNIQUE (session_id, student_profile_id)

attendances (
  id, session_participant_id UNIQUE,
  status,                             -- AttendanceStatus
  derived_status,                     -- ما اقترحه النظام قبل اعتماد المعلم
  attended_minutes INT, joined_after_minutes INT, left_before_minutes INT,
  confirmed_by NULL, confirmed_at NULL,
  override_reason TEXT NULL,          -- إلزامي عند مخالفة المشتق
  created_at, updated_at
)
-- CHECK (status = derived_status OR override_reason IS NOT NULL)
--   ← لا تعديل على الحضور بلا سبب مكتوب

postponement_requests (
  id, session_id, requested_by, requested_for_student_id,
  status,                             -- PostponementStatus
  proposed_start TIMESTAMPTZ, proposed_by_teacher_start TIMESTAMPTZ NULL,
  agreed_start TIMESTAMPTZ NULL,
  makeup_session_id NULL,
  reason TEXT, teacher_note TEXT, admin_note TEXT,
  responded_by NULL, responded_at NULL, expires_at,
  created_at, updated_at
)
-- INDEX (session_id), INDEX (status, expires_at)
```

### قيد منع التعارض — لماذا على مستوى قاعدة البيانات؟

التحقق في التطبيق يفشل عند طلبين متزامنين. قيد `EXCLUDE USING gist`
يجعل ازدواج حجز المعلم **مستحيلًا فيزيائيًا** مهما كان التزامن.
يحتاج امتداد `btree_gist` المثبَّت في `docker/postgres/init`.

---

## 5. الفصل المباشر والتسجيلات

```sql
classrooms (
  id, session_id UNIQUE, provider,    -- bigbluebutton | zoom | ...
  external_id, external_meta JSONB,
  moderator_secret, attendee_secret,
  created_remote_at, started_at NULL, ended_at NULL,
  max_concurrent_participants INT DEFAULT 0,
  health_status, last_error TEXT,
  created_at, updated_at
)

classroom_events (
  id, classroom_id, event_type,       -- created | started | user_joined | ...
  external_user_id, user_id NULL,
  occurred_at, payload JSONB, created_at
)
-- INDEX (classroom_id, occurred_at)
--   ← مصدر احتساب الحضور الآلي

recordings (
  id, session_id, classroom_id, provider, external_recording_id,
  status,                             -- processing | ready | archived | deleted
  duration_seconds INT, size_bytes BIGINT,
  disk, path, thumbnail_path,
  archive_driver, archive_path, archived_at NULL,
  available_from, expires_at,          -- available_from + retention_days
  deleted_at NULL, deleted_by NULL, deletion_reason TEXT,
  created_at, updated_at
)
-- INDEX (expires_at) WHERE deleted_at IS NULL
--   ← المهمة اليومية لفرض مدة الاحتفاظ

recording_views (
  id, recording_id, user_id, viewed_at, ip_address, user_agent,
  action                              -- view | download
)
--   ← كل مشاهدة تُسجَّل: مطلب خصوصية لوجود قُصّر
```

---

## 6. التعلّم

```sql
assignments (
  id, organization_id, course_id, group_id NULL, staff_profile_id,
  title JSONB, instructions JSONB, attachments JSONB,
  assigned_at, due_at, max_score INT,
  allows_late BOOLEAN, late_penalty_percent INT,
  created_at, updated_at, deleted_at
)

assignment_submissions (
  id, assignment_id, student_profile_id,
  submitted_at NULL, is_late BOOLEAN,
  content TEXT, attachments JSONB,
  score INT NULL, feedback TEXT, graded_by NULL, graded_at NULL,
  status,                             -- pending | submitted | late | graded
  created_at, updated_at
)
-- UNIQUE (assignment_id, student_profile_id)

assessments (
  id, organization_id, course_id NULL,
  type,                               -- quiz | exam | placement | reactivation
  title JSONB, instructions JSONB,
  total_score INT, passing_score INT,
  duration_minutes INT NULL, max_attempts INT,
  available_from, available_to,
  created_by, created_at, updated_at, deleted_at
)

questions (
  id, assessment_id, type,            -- mcq | true_false | short_answer | essay
  body JSONB, options JSONB, correct_answer JSONB,
  score INT, sort_order, created_at
)

assessment_attempts (
  id, assessment_id, student_profile_id,
  reactivation_request_id NULL,        -- الربط عند اختبار فك التجميد
  attempt_number INT, started_at, submitted_at NULL,
  score INT NULL, passed BOOLEAN NULL,
  graded_by NULL, graded_at NULL, answers JSONB,
  created_at
)

session_reports (
  id, session_id UNIQUE, staff_profile_id,
  topics_covered TEXT, homework_assigned TEXT,
  general_notes TEXT, supervisor_private_note TEXT,
  next_session_plan TEXT,
  submitted_at NULL, is_late BOOLEAN,
  created_at, updated_at
)

session_report_students (
  id, session_report_id, student_profile_id,
  participation SMALLINT, performance SMALLINT, commitment SMALLINT,
  strengths TEXT, weaknesses TEXT, note TEXT
)

monthly_reports (
  id, organization_id, student_profile_id, enrollment_id,
  period_year INT, period_month INT,
  metrics JSONB,                      -- مجمّع الحضور والدرجات والمخالفات
  supervisor_summary TEXT,
  status,                             -- draft | approved | sent
  approved_by NULL, approved_at NULL, sent_at NULL,
  created_at, updated_at
)
-- UNIQUE (student_profile_id, period_year, period_month)
```

---

## 7. الانضباط

```sql
violation_events (
  id, organization_id, enrollment_id, student_profile_id,
  session_id NULL, type,              -- no_show | unexcused_absence
                                      -- | late_cancellation
  occurred_at, window_key,            -- '2026-08' للنافذة الشهرية
  is_countable BOOLEAN DEFAULT TRUE,
  waived_by NULL, waived_at NULL, waiver_reason TEXT,
  created_at
)
-- INDEX (enrollment_id, window_key) WHERE is_countable AND waived_at IS NULL
--   ← عدّاد النافذة يُحسب من هنا ولا يُخزَّن رقمًا

discipline_actions (
  id, enrollment_id, triggered_by_event_id,
  action,                             -- notice | warning | freeze_enrollment
  threshold_reached INT, window_key,
  is_automatic BOOLEAN,
  applied_at, applied_by NULL, notes TEXT,
  created_at
)

reactivation_requests (
  id, enrollment_id, requested_by,
  status,                             -- pending | assessment | approved
                                      -- | rejected
  attempt_number INT, assessment_attempt_id NULL,
  student_statement TEXT,
  reviewer_id NULL, reviewed_at NULL, decision_note TEXT,
  created_at, updated_at
)
```

---

## 8. المستحقات

```sql
payroll_periods (
  id, organization_id,
  year INT, month INT, starts_on DATE, ends_on DATE,
  status,                             -- PayrollPeriodStatus
  calculated_at NULL, reviewed_by NULL, reviewed_at NULL,
  approved_by NULL, approved_at NULL,
  paid_at NULL, locked_at NULL,
  totals JSONB,
  created_at, updated_at
)
-- UNIQUE (organization_id, year, month)

payroll_entries (
  id, organization_id, payroll_period_id, staff_profile_id,
  session_id NULL, teacher_contract_id,
  entry_type,                         -- session_earning | monthly_base
                                      -- | deduction | deferred | adjustment
  outcome_key,                        -- مفتاح مصفوفة config/payroll.php
  amount BIGINT, currency CHAR(3),
  rate_snapshot JSONB,                -- السعر والقاعدة وقت الإنشاء
  status,                             -- pending | released | deferred
                                      -- | cancelled
  deferred_until_session_id NULL,
  description JSONB,
  created_at
  -- لا يوجد updated_at ولا deleted_at عمدًا: دفتر أستاذ
)
-- INDEX (payroll_period_id, staff_profile_id)
-- INDEX (session_id)
-- UNIQUE (session_id, staff_profile_id, entry_type)
--   ← الحماية من ازدواج القيد عند إعادة تشغيل مهمة

payroll_adjustments (
  id, payroll_period_id, staff_profile_id,
  type,                               -- bonus | deduction | correction
  amount BIGINT, currency CHAR(3),
  reason TEXT NOT NULL,
  references_period_id NULL,          -- عند تصحيح فترة مقفلة
  proposed_by, proposed_at,
  approved_by NULL, approved_at NULL,
  rejected_by NULL, rejected_at NULL, rejection_reason TEXT,
  created_at
)
-- CHECK (approved_by IS NULL OR approved_by <> proposed_by)
--   ← من اقترح لا يعتمد — قيد قاعدة بيانات لا تحقق واجهة

staff_obligations (
  id, organization_id, staff_profile_id, payroll_period_id,
  obligation_type,                    -- salary | stipend
  amount BIGINT, currency CHAR(3),
  target_teaching INT, achieved_teaching INT,
  target_admin INT, achieved_admin INT,
  target_training INT, achieved_training INT,
  status, created_at, updated_at
)
```

---

## 9. الإشعارات والمراسلات

```sql
notification_outbox (
  id, organization_id, user_id,
  category, channel, locale,
  event_name, event_id, correlation_id,
  subject JSONB, body JSONB, payload JSONB,
  idempotency_key UNIQUE,
  scheduled_for, status,              -- queued | sending | sent | failed
                                      -- | suppressed
  attempts INT DEFAULT 0, last_error TEXT,
  sent_at NULL, created_at, updated_at
)
-- INDEX (status, scheduled_for)
-- INDEX (user_id, created_at DESC)

notification_delivery_attempts (
  id, outbox_id, attempt_number, attempted_at,
  provider_response JSONB, succeeded BOOLEAN, error TEXT
)

notification_preferences (
  id, user_id, category, channel, enabled BOOLEAN, updated_at
)
-- UNIQUE (user_id, category, channel)

conversations (
  id, organization_id, subject, type,  -- direct | group | support
  is_moderated BOOLEAN DEFAULT TRUE,
  related_type NULL, related_id NULL,
  created_by, last_message_at, created_at, deleted_at
)

conversation_participants (
  id, conversation_id, user_id, role,  -- member | moderator | observer
  joined_at, last_read_at, muted_until NULL
)

messages (
  id, conversation_id, user_id, body TEXT, attachments JSONB,
  is_flagged BOOLEAN, flagged_reason,
  moderated_by NULL, moderated_at NULL,
  created_at, edited_at NULL, deleted_at NULL
)
-- INDEX (conversation_id, created_at DESC)

whatsapp_inbound (
  id, organization_id, from_phone, message_id UNIQUE,
  body TEXT, media JSONB, received_at,
  matched_user_id NULL, handled_by NULL, handled_at NULL,
  created_at
)
--   ← الوارد يُخزَّن ولا يُوجَّه آليًا: يراه الأدمن والمشرف فقط

class_wall_posts (
  id, group_id, user_id, body TEXT, attachments JSONB,
  is_pinned BOOLEAN, created_at, deleted_at
)

class_wall_comments (
  id, post_id, user_id, body TEXT, created_at, deleted_at
)
```

---

## 10. التدقيق

```sql
audit_log (
  id, organization_id,
  actor_id NULL, actor_type,          -- user | system | scheduled
  acting_for_user_id NULL,            -- ولي الأمر ينوب عن ابنه
  action, auditable_type, auditable_id,
  old_values JSONB, new_values JSONB,
  reason TEXT,
  ip_address INET, user_agent, correlation_id,
  created_at
)
-- INDEX (organization_id, auditable_type, auditable_id, created_at DESC)
-- INDEX (actor_id, created_at DESC)
-- PARTITION BY RANGE (created_at)  — تقسيم شهري عند تجاوز 10 مليون سطر
```

**ما يجب أن يظهر هنا إجباريًا:**
تعديل الحضور · تغيير حالة القيد · كل قيدة وتسوية مالية · تغيير الأدوار
والصلاحيات · مشاهدة وتنزيل وحذف التسجيلات · الانتحال · تعديل الإعدادات.

---

## 11. الفوترة (خلف مفتاح الميزة)

الجداول تُنشأ في المرحلة الأولى وتبقى فارغة، حتى لا نهاجر بيانات لاحقًا.

```sql
invoices (id, organization_id, student_profile_id, enrollment_id,
          number UNIQUE, status, subtotal BIGINT, discount BIGINT,
          total BIGINT, currency, issued_at, due_at, paid_at, ...)

invoice_lines (id, invoice_id, description JSONB, quantity,
               unit_amount BIGINT, total BIGINT, ...)

student_packages (id, student_profile_id, program_id, total_sessions,
                  consumed_sessions, remaining_sessions, expires_at, ...)

payments (id, invoice_id, amount BIGINT, method, provider_reference,
          status, paid_at, ...)

coupons (id, organization_id, code UNIQUE, type, value,
         max_uses, used_count, valid_from, valid_to, ...)

refunds (id, payment_id, session_id NULL, amount BIGINT,
         reason, approved_by, created_at)
```

---

## 12. قواعد الهجرات

1. هجرة كل موديول في `modules/<Name>/database/migrations/` — لا في الجذر.
2. اسم الملف يحمل الترتيب الزمني الصحيح عبر الموديولات: الجداول المرجعية أولًا.
3. **ممنوع** تعديل هجرة مُنفَّذة على الإنتاج — هجرة جديدة دائمًا.
4. كل `foreign key` يعلن `ON DELETE` صراحةً: `RESTRICT` هو الافتراضي،
   و`CASCADE` فقط لجداول الربط الخالصة.
5. كل فهرس مذكور أعلاه إلزامي — لا يُترك للأداء أن يُكتشف في الإنتاج.
6. الجداول المالية **بلا** `deleted_at` عمدًا: لا حذف ناعم لدفتر أستاذ.
