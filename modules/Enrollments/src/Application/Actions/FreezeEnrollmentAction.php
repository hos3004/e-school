<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentFrozen;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تجميد تأديبي — آليًا بعد استيفاء سُلَّم المخالفات أو يدويًا من الإدارة.
 *
 * التجميد بلا موعد عودة: يُمسح أي موعد عودة سابق، ولا يُفك إلا عبر
 * المسار ReactivationRequested → UnderAssessment → Active.
 * الحساب لا يُحذف ولا تُمس بياناته — يُمنع الوصول للكورسات فقط.
 */
final readonly class FreezeEnrollmentAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Enrollment $enrollment, string $reason, ?string $freezeType = null, ?string $actorId = null): Enrollment
    {
        $allowedTypes = array_values((array) config('enrollments.freeze_types', ['automatic', 'manual']));
        $type = $freezeType ?? $allowedTypes[0];

        if (! in_array($type, $allowedTypes, true)) {
            throw BusinessRuleViolation::make(
                'enrollments.invalid_freeze_type',
                'enrollments::errors.invalid_freeze_type',
                ['types' => implode(', ', $allowedTypes)],
            );
        }

        [$event] = $this->transaction->run(function () use ($enrollment, $type, $reason, $actorId): array {
            $from = $enrollment->status;

            $enrollment->frozen_at = CarbonImmutable::now('UTC');
            $enrollment->frozen_reason = $reason;
            $enrollment->freeze_type = $type;
            $enrollment->expected_return_date = null;

            $this->applyTransition($enrollment, EnrollmentStatus::Frozen, $reason, $actorId);

            return [new EnrollmentFrozen(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: $from->value,
                freezeType: $type,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
