<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Concerns;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Shared\Support\BusinessRuleViolation;

/**
 * تنفيذ انتقال حالة القيد بكتابته في السجل التاريخي إلزاميًا.
 *
 * كل انتقال يمر عبر canTransitionTo — لا استثناءات.
 * كل انتقال يُسجَّل في enrollment_status_history بـ (من، إلى، السبب، الفاعل، الوقت)،
 * والسبب المكتوب إلزامي وفق قاعدة التدقيق — لا انتقال بلا سبب.
 */
trait TransitionsEnrollmentStatus
{
    protected function applyTransition(
        Enrollment $enrollment,
        EnrollmentStatus $to,
        string $reason,
        AuditRecorder $audit,
        ?string $actorId = null,
    ): void {
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.transition_reason_required',
                'enrollments::errors.transition_reason_required',
            );
        }

        $from = $enrollment->status;

        if (!$from->canTransitionTo($to)) {
            throw BusinessRuleViolation::make(
                'enrollments.invalid_transition',
                'enrollments::errors.invalid_transition',
                ['from' => $from->value, 'to' => $to->value],
            );
        }

        $enrollment->status = $to;

        match ($to) {
            EnrollmentStatus::Active => $enrollment->activated_at ??= CarbonImmutable::now('UTC'),
            EnrollmentStatus::Completed => $enrollment->completed_at = CarbonImmutable::now('UTC'),
            EnrollmentStatus::Withdrawn => $enrollment->withdrawn_at = CarbonImmutable::now('UTC'),
            default => null,
        };

        $enrollment->save();

        $resolvedActorId = $actorId ?? (auth()->id() === null ? 'system' : (string) auth()->id());

        EnrollmentStatusHistory::create([
            'enrollment_id' => $enrollment->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'changed_by' => $resolvedActorId,
            'changed_at' => CarbonImmutable::now('UTC'),
        ]);

        $audit->record(
            organizationId: (string) $enrollment->organization_id,
            actorId: $actorId,
            actorType: $actorId === null ? 'system' : 'user',
            action: 'enrollments.status_changed',
            auditableType: 'enrollments',
            auditableId: (string) $enrollment->getKey(),
            oldValues: ['status' => $from->value],
            newValues: ['status' => $to->value],
            reason: $reason,
        );
    }
}
