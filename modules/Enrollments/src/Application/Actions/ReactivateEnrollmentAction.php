<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * اعتماد فك التجميد — الرجل الأخير في المسار الإلزامي:
 * Frozen → ReactivationRequested → UnderAssessment → Active.
 *
 * الاعتماد محصور بصلاحية enrollment.reactivate — لا استثناء ولا دور مكتوب بالاسم.
 * عند النجاح تُمسح حقول التجميد ويعود الطالب للوصول للكورسات.
 */
final readonly class ReactivateEnrollmentAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Gate $gate,
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Enrollment $enrollment, string $reason, ?string $actorId = null): Enrollment
    {
        if ($this->gate->denies('enrollment.reactivate')) {
            throw BusinessRuleViolation::make(
                'enrollments.reactivation_permission_denied',
                'enrollments::errors.reactivation_permission_denied',
                ['permission' => 'enrollment.reactivate'],
            );
        }

        [$event] = $this->transaction->run(function () use ($enrollment, $reason, $actorId): array {
            $from = $enrollment->status;

            $enrollment->frozen_at = null;
            $enrollment->frozen_reason = null;
            $enrollment->freeze_type = null;

            $this->applyTransition($enrollment, EnrollmentStatus::Active, $reason, $this->audit, $actorId);

            return [new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: $from->value,
                toStatus: EnrollmentStatus::Active->value,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
