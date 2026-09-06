<?php

declare(strict_types=1);

namespace App\Listeners;

use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Enrollments\Application\Actions\FreezeEnrollmentAction;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;

/** Coordinates the automatic discipline freeze across module boundaries. */
final readonly class ApplyAutomaticDisciplineFreeze
{
    public function __construct(private FreezeEnrollmentAction $freeze) {}

    public function handle(DisciplineActionApplied $event): void
    {
        if (
            $event->action !== DisciplineActionType::FreezeEnrollment
            || !$event->isAutomatic
        ) {
            return;
        }

        $enrollment = Enrollment::query()
            ->forOrganization($event->organizationId)
            ->find($event->enrollmentId);

        if (
            $enrollment === null
            || $enrollment->status === EnrollmentStatus::Frozen
            || !$enrollment->status->canTransitionTo(EnrollmentStatus::Frozen)
        ) {
            return;
        }

        $this->freeze->execute(
            enrollment: $enrollment,
            reason: (string) __('enrollments::messages.automatic_discipline_freeze', [
                'threshold' => $event->thresholdReached,
                'window' => $event->windowKey,
            ]),
            freezeType: 'automatic',
            actorId: $event->actorId,
        );
    }
}
