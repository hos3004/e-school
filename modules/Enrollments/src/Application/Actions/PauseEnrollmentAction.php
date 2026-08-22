<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إيقاف اختياري بطلب الطالب.
 *
 * الفرق الجوهري عن التجميد: الإيقاف بطلب الطالب وله موعد عودة إلزامي،
 * ويعود آليًا إلى Active عند حلول الموعد (عبر المجدول — خارج نطاق هذا الإجراء).
 */
final readonly class PauseEnrollmentAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Enrollment $enrollment, string $expectedReturnDate, string $reason, ?string $actorId = null): Enrollment
    {
        $returnDate = CarbonImmutable::parse($expectedReturnDate, 'UTC')->startOfDay();

        if ($returnDate->lessThan(CarbonImmutable::now('UTC')->startOfDay())) {
            throw BusinessRuleViolation::make(
                'enrollments.pause_return_date_in_past',
                'enrollments::errors.pause_return_date_in_past',
            );
        }

        [$event] = $this->transaction->run(function () use ($enrollment, $returnDate, $reason, $actorId): array {
            $from = $enrollment->status;

            $enrollment->expected_return_date = $returnDate;

            $this->applyTransition($enrollment, EnrollmentStatus::Paused, $reason, $actorId);

            return [new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: $from->value,
                toStatus: EnrollmentStatus::Paused->value,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
