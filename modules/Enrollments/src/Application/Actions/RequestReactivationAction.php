<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\Transaction;

/**
 * تقديم طلب فك التجميد — الخطوة الأولى والوحيدة للخروج من Frozen.
 *
 * قاعدة حاكمة: لا انتقال مباشر من Frozen إلى Active أبدًا.
 * المسار الإلزامي: Frozen → ReactivationRequested → UnderAssessment → Active.
 */
final readonly class RequestReactivationAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Enrollment $enrollment, string $reason, ?string $actorId = null): Enrollment
    {
        [$event] = $this->transaction->run(function () use ($enrollment, $reason, $actorId): array {
            $from = $enrollment->status;

            $this->applyTransition($enrollment, EnrollmentStatus::ReactivationRequested, $reason, $actorId);

            return [new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: $from->value,
                toStatus: EnrollmentStatus::ReactivationRequested->value,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
