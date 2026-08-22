# 09 — سجل الأحداث

الأحداث هي الجهاز العصبي للمنصة. هذا السجل هو **العقد**: من ينشر، وماذا
يحمل الحدث، ومن يستمع. أي حدث غير مذكور هنا لا يوجد.

---

## قواعد الأحداث

1. **الاسم بصيغة الماضي:** `SessionFinalized` لا `FinalizeSession`.
2. **المفتاح المستقر:** `module.event` بحروف صغيرة — `sessions.finalized`.
3. **الحمولة معرّفات وقيَم فقط** — لا Eloquent Models ولا كائنات ثقيلة.
4. **بعد نجاح المعاملة فقط:** الأحداث تُجمَّع وتُنشر بعد `commit`.
5. **غير قابلة للتعديل** بعد الإنشاء.
6. **المستمع لا يفشل الناشر:** المستمعون يعملون في طوابير، وفشل مستمع
   يُعاد محاولته دون التأثير على العملية الأصلية.
7. **idempotent:** المستمع قد يُستدعى مرتين — يجب أن يعطي نفس النتيجة.

---

## 1. القيد والانضباط

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `enrollments.created` | enrollmentId, studentId, programId | Notifications · Reporting |
| `enrollments.activated` | enrollmentId, studentId, programId, activatedAt | Scheduling · Notifications · Reporting |
| `enrollments.paused` | enrollmentId, reason, expectedReturnDate | Scheduling · Notifications |
| `enrollments.frozen` | enrollmentId, freezeType, reason, triggeredByEventId | **Scheduling** (يلغي الحصص المستقبلية) · **Content** (يقطع الوصول) · Notifications · Reporting |
| `enrollments.reactivation_requested` | enrollmentId, requestId, attemptNumber | Assessments · Notifications |
| `enrollments.reactivated` | enrollmentId, approvedBy, assessmentScore | Scheduling · Content · Notifications · Reporting |
| `enrollments.withdrawn` | enrollmentId, reason | Scheduling · Payroll · Notifications |
| `discipline.violation_recorded` | eventId, enrollmentId, type, windowKey, countInWindow | Notifications · Reporting |
| `discipline.action_applied` | actionId, enrollmentId, action, threshold | **Enrollments** (ينفّذ التجميد) · Notifications |

**السلسلة الحرجة:**
```
sessions.finalized (status=NoShow)
  → discipline.violation_recorded (count=3)
    → discipline.action_applied (action=freeze_enrollment)
      → enrollments.frozen
        → scheduling: إلغاء الحصص المستقبلية
        → content: قطع الوصول
        → notifications: إخطار الطالب وولي الأمر والمشرف
```
لا موديول في هذه السلسلة يستدعي التالي مباشرة.

---

## 2. الجدولة

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `scheduling.schedule_created` | scheduleId, groupId, teacherId, rrule | Sessions (توليد الحصص) · Notifications |
| `scheduling.schedule_changed` | scheduleId, changes, effectiveFrom | Sessions · Notifications |
| `scheduling.conflict_detected` | type, entityId, conflictingSessionIds | Notifications (تنبيه الإدارة) |
| `scheduling.postponement_requested` | requestId, sessionId, studentId, proposedStart | **Notifications** (معلم + إدارة) |
| `scheduling.postponement_alternative_proposed` | requestId, teacherProposedStart | Notifications (طالب) |
| `scheduling.postponement_scheduled` | requestId, sessionId, makeupSessionId, agreedStart | **Sessions** · **Payroll** (تأجيل القيدة) · Notifications |
| `scheduling.postponement_rejected` | requestId, reason | Notifications |
| `scheduling.postponement_expired` | requestId | Notifications (تصعيد للإدارة) |

---

## 3. الحصص

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `sessions.scheduled` | sessionId, teacherId, participants, startsAt | VirtualClassroom · Notifications |
| `sessions.confirmed` | sessionId, confirmedBy | Notifications |
| `sessions.started` | sessionId, actualStart | Attendance · Notifications |
| `sessions.ended` | sessionId, actualEnd | Attendance (احتساب أولي) · Recordings |
| **`sessions.finalized`** | sessionId, status, outcome, teacherId, substituteForTeacherId, participants[] | **Payroll · Discipline · AcademicReports · Reporting · Notifications** |
| `sessions.cancelled` | sessionId, cancelledBy, actorRole, reason, noticeMinutes | Payroll · Discipline · Notifications · Reporting |
| `sessions.postponed` | sessionId, makeupSessionId | Payroll · Notifications |
| `sessions.substitute_assigned` | sessionId, originalTeacherId, substituteTeacherId | **Payroll** · Notifications |
| `sessions.makeup_completed` | makeupSessionId, originalSessionId | **Payroll** (تحرير المؤجَّل) |

### `sessions.finalized` — الحدث الأكثر أهمية

```php
final class SessionFinalized extends DomainEvent
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $teacherId,
        public readonly ?string $substituteForTeacherId,
        public readonly string $status,        // SessionStatus value
        public readonly string $outcome,       // مفتاح مصفوفة payroll
        public readonly string $sessionType,
        public readonly ?string $courseId,
        public readonly ?string $groupId,
        public readonly array $participants,   // [{studentId, enrollmentId,
                                               //   attendanceStatus}]
        public readonly string $occurredOn,    // تاريخ الحصة — لا تاريخ الإقفال
        ?string $actorId = null,
    ) { parent::__construct($actorId); }
}
```

> `occurredOn` تاريخ **الحصة** لا الإقفال. عليه تُحدَّد فترة المستحقات
> ونافذة عدّاد الانضباط. الخلط بينهما يعني قيدة في الشهر الخطأ.

---

## 4. الحضور والفصل والتسجيلات

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `attendance.computed` | sessionId, derived[] | Notifications (تنبيه المعلم) |
| `attendance.confirmed` | sessionId, confirmedBy, records[] | **Sessions** (يسمح بالإقفال) · Reporting |
| `attendance.overridden` | attendanceId, from, to, reason, actorId | **Audit** · Notifications |
| `classroom.created` | sessionId, provider, externalId | — |
| `classroom.started` | sessionId, startedAt | **Sessions** |
| `classroom.participant_joined` | sessionId, userId, joinedAt | Attendance |
| `classroom.participant_left` | sessionId, userId, leftAt | Attendance |
| `classroom.ended` | sessionId, endedAt, peakParticipants | **Sessions** · Recordings |
| `classroom.provider_unhealthy` | provider, error | Notifications (تنبيه فوري للإدارة) |
| `recordings.ready` | recordingId, sessionId, duration, size | Notifications · Reporting |
| `recordings.archived` | recordingId, archiveDriver, archivePath | — |
| `recordings.expired` | recordingId | — |
| `recordings.deleted` | recordingId, deletedBy, reason | **Audit** |
| `recordings.viewed` | recordingId, userId, action | **Audit** |

---

## 5. التعلّم

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `assignments.assigned` | assignmentId, courseId, groupId, dueAt | Notifications |
| `assignments.submitted` | submissionId, studentId, isLate | Notifications |
| `assignments.graded` | submissionId, score, gradedBy | Notifications · Reporting |
| `assessments.attempt_submitted` | attemptId, assessmentId, studentId | Notifications |
| `assessments.graded` | attemptId, score, passed | **Discipline** (لاختبار فك التجميد) · Notifications · Reporting |
| `academic_reports.session_report_submitted` | reportId, sessionId, isLate | **Discipline** (مخالفة معلم عند التأخير) · Reporting |
| `academic_reports.monthly_drafted` | reportId, studentId, period | Notifications (تنبيه المشرف) |
| `academic_reports.monthly_approved` | reportId, approvedBy | Notifications (إرسال للطالب وولي الأمر) |
| `certificates.issued` | certificateId, studentId, issuedBy | Notifications |
| `certificates.badge_awarded` | badgeAwardId, userId, badgeId, tier | Notifications |

---

## 6. المستحقات والمراسلات

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `payroll.entry_created` | entryId, staffId, sessionId, amount, outcome | Reporting |
| `payroll.entry_deferred` | entryId, deferredUntilSessionId | — |
| `payroll.entry_released` | entryId, releasedBySessionId | Notifications |
| `payroll.adjustment_proposed` | adjustmentId, staffId, type, amount, proposedBy | **Notifications** (تنبيه المعتمد) |
| `payroll.adjustment_approved` | adjustmentId, approvedBy | Notifications |
| `payroll.period_calculated` | periodId, totals | Notifications |
| `payroll.period_approved` | periodId, approvedBy | Notifications |
| `payroll.period_paid` | periodId, paidAt | Notifications (كشف لكل معلم) |
| `messaging.message_sent` | messageId, conversationId, senderId, recipientIds | Notifications |
| `messaging.message_flagged` | messageId, reason | Notifications (تنبيه الإشراف) |
| `messaging.whatsapp_inbound_received` | inboundId, fromPhone, matchedUserId | Notifications (للأدمن والمشرف فقط) |

---

## 7. الهوية والنظام

| الحدث | الحمولة | المستمعون |
|-------|---------|-----------|
| `identity.user_registered` | userId, role | Notifications · Audit |
| `identity.login_succeeded` | userId, ip, device | Audit |
| `identity.login_failed` | email, ip, attemptCount | Audit · Notifications عند التكرار |
| `identity.password_changed` | userId | Notifications · Audit |
| `identity.two_factor_enabled` | userId | Audit |
| `access_control.role_assigned` | userId, roleId, assignedBy | **Audit** · Notifications |
| `access_control.permission_changed` | roleId, changes, actorId | **Audit** |
| `organization.settings_changed` | key, oldValue, newValue, actorId | **Audit** |
| `organization.feature_toggled` | feature, enabled, actorId | **Audit** |

---

## 8. مسؤوليات المستمعين

### التصنيف

| النوع | التنفيذ | مثال |
|-------|---------|------|
| **حرج متزامن** | داخل نفس المعاملة | إنشاء قيدة المستحقات |
| **مؤجَّل** | طابور مع إعادة محاولة | إرسال الإشعارات |
| **تحليلي** | طابور منخفض الأولوية | تحديث Read Models |

### قواعد

- المستمع الحرج يُعلن `ShouldBeEncrypted` ولا يُعلن `ShouldQueue`.
- المستمع المؤجَّل يُعلن `ShouldQueue` مع `$tries` و `$backoff`.
- كل مستمع يفحص idempotency بمفتاح `event_id` قبل التنفيذ.
- المستمع لا ينشر حدثًا من نفس موديوله (منعًا للحلقات) — إلا بتوثيق صريح.

### الطوابير

| الطابور | الأولوية | ما يعمل فيه |
|---------|----------|-------------|
| `critical` | 1 | المستحقات · الانضباط · الفصل المباشر |
| `default` | 2 | الإشعارات · التقارير الأكاديمية |
| `low` | 3 | Read Models · الأرشفة · التنظيف |

---

## 9. سجل الأحداث للتدقيق

كل حدث يُكتب في `audit_log` عبر مستمع عام واحد في موديول `Audit`،
بالحمولة الكاملة و `correlation_id`. هذا يجعل السؤال
*"ما الذي حدث بعد أن ضغط المشرف زر الاعتماد؟"* قابلًا للإجابة بسطر SQL واحد.
