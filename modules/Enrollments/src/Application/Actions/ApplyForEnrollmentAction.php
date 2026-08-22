<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Shared\Support\Transaction;
use Shared\Support\BusinessRuleViolation;

/**
 * تقديم طلب التحاق ببرنامج — نقطة دخول دورة حياة القيد.
 *
 * قاعدة التفرّد: طالب واحد لبرنامج واحد بقيد حيّ (غير محذوف) —
 * يفرضها الفهرس الجزئي enrollments_student_program_active_unique، ونتحقق منها هنا برسالة مفهومة.
 */
final readonly class ApplyForEnrollmentAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        ?string $currentLevelId = null,
        ?string $actorId = null,
    ): Enrollment {
        $existing = Enrollment::query()
            ->where('student_profile_id', $studentProfileId)
            ->where('program_id', $programId)
            ->exists();

        if ($existing) {
            throw BusinessRuleViolation::make(
                'enrollments.duplicate_active_enrollment',
                'enrollments::errors.duplicate_active_enrollment',
            );
        }

        [$enrollment, $event] = $this->transaction->run(function () use ($organizationId, $studentProfileId, $programId, $currentLevelId, $actorId): array {
            $enrollment = new Enrollment;
            $enrollment->fill([
                'organization_id' => $organizationId,
                'student_profile_id' => $studentProfileId,
                'program_id' => $programId,
                'status' => EnrollmentStatus::Applied,
                'applied_at' => CarbonImmutable::now('UTC'),
                'current_level_id' => $currentLevelId,
            ]);
            $enrollment->save();

            EnrollmentStatusHistory::create([
                'enrollment_id' => $enrollment->id,
                'from_status' => null,
                'to_status' => EnrollmentStatus::Applied->value,
                'reason' => __('enrollments::reasons.applied'),
                'changed_by' => $actorId ?? (string) auth()->id(),
                'changed_at' => CarbonImmutable::now('UTC'),
            ]);

            return [$enrollment, new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: null,
                toStatus: EnrollmentStatus::Applied->value,
                reason: __('enrollments::reasons.applied'),
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
