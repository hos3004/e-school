<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

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
        private StudentDirectoryQueries $students,
        private AcademicCatalogQueries $academics,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        string $reason,
        ?string $currentLevelId = null,
        ?string $actorId = null,
    ): Enrollment {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.transition_reason_required',
                'enrollments::errors.transition_reason_required',
            );
        }

        if ($this->students->find($organizationId, $studentProfileId) === null) {
            throw BusinessRuleViolation::make(
                'enrollments.student_outside_organization',
                'enrollments::errors.student_outside_organization',
            );
        }

        if (!isset($this->academics->programsByIds($organizationId, [$programId])[$programId])) {
            throw BusinessRuleViolation::make(
                'enrollments.program_outside_organization',
                'enrollments::errors.program_outside_organization',
            );
        }

        if ($currentLevelId !== null) {
            $level = $this->academics->levelsByIds($organizationId, [$currentLevelId])[$currentLevelId] ?? null;
            if ($level === null || $level->programId !== $programId) {
                throw BusinessRuleViolation::make(
                    'enrollments.level_outside_program',
                    'enrollments::errors.level_outside_program',
                );
            }
        }

        $existing = Enrollment::query()
            ->forOrganization($organizationId)
            ->where('student_profile_id', $studentProfileId)
            ->where('program_id', $programId)
            ->exists();

        if ($existing) {
            throw BusinessRuleViolation::make(
                'enrollments.duplicate_active_enrollment',
                'enrollments::errors.duplicate_active_enrollment',
            );
        }

        [$enrollment, $event] = $this->transaction->run(function () use ($organizationId, $studentProfileId, $programId, $currentLevelId, $actorId, $reason): array {
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

            $resolvedActorId = $actorId ?? (auth()->id() === null ? 'system' : (string) auth()->id());

            EnrollmentStatusHistory::create([
                'enrollment_id' => $enrollment->id,
                'from_status' => null,
                'to_status' => EnrollmentStatus::Applied->value,
                'reason' => $reason,
                'changed_by' => $resolvedActorId,
                'changed_at' => CarbonImmutable::now('UTC'),
            ]);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'enrollments.created',
                auditableType: 'enrollments',
                auditableId: (string) $enrollment->getKey(),
                oldValues: null,
                newValues: [
                    'student_profile_id' => $studentProfileId,
                    'program_id' => $programId,
                    'current_level_id' => $currentLevelId,
                    'status' => EnrollmentStatus::Applied->value,
                ],
                reason: $reason,
            );

            return [$enrollment, new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: null,
                toStatus: EnrollmentStatus::Applied->value,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
