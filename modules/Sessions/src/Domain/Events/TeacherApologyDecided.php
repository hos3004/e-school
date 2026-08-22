<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * بتّ المشرف في اعتذار المعلم.
 *
 * عند الاعتماد `substituteRequired = true` — وهذه إشارة **بدء البحث عن بديل**
 * لا إشارة إلغاء. الحصة قائمة بموعدها وحالتها لم تتغيّر
 * (docs/client-answers.md §ي).
 *
 * `escalationAction` و`occurrenceInWindow` مؤشرات إخطارية فقط: `record` أو
 * `warning` أو `escalation`. **لا تُترجَم إلى أي عقوبة آلية** — لا تعليق ولا
 * إنهاء ولا تغيير حالة المعلم. القرار النهائي للإدارة يدوي (§ك).
 *
 * `teacherUserId` مضمَّن لأن موديول Notifications لا يستطيع تحويل
 * staff_profile_id إلى مستقبِل دون كسر حدود الموديولات.
 */
final class TeacherApologyDecided extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $apologyId,
        public readonly ?string $teacherUserId,
        public readonly string $decision,
        public readonly bool $substituteRequired,
        public readonly ?int $occurrenceInWindow,
        public readonly ?int $windowDays,
        public readonly ?string $escalationAction,
        public readonly bool $createsEscalation,
        public readonly string $scheduledStart,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.teacher_apology.'.$this->decision;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'apology_id' => $this->apologyId,
            'teacher_user_id' => $this->teacherUserId,
            'decision' => $this->decision,
            'substitute_required' => $this->substituteRequired,
            'occurrence_in_window' => $this->occurrenceInWindow,
            'window_days' => $this->windowDays,
            'escalation_action' => $this->escalationAction,
            'creates_escalation' => $this->createsEscalation,
            'scheduled_start' => $this->scheduledStart,
        ];
    }
}
