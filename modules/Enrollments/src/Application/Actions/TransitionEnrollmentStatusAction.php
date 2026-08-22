<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Enrollments\Application\Concerns\TransitionsEnrollmentStatus;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * انتقال حالة عام لكل الانتقالات "النظيفة" التي لا تحمل حقولًا إضافية.
 *
 * يخدم: مراجعة الطلب، القبول، الرفض، التفعيل، العودة من الإيقاف،
 * بدء التقييم، الإكمال، والانسحاب.
 *
 * لا يخدم أبدًا:
 *  - Paused  → إجراء PauseEnrollmentAction (موعد عودة إلزامي).
 *  - Frozen  → إجراء FreezeEnrollmentAction.
 *  - أي خروج من Frozen إلا عبر RequestReactivationAction أو الانسحاب هنا؟ لا —
 *    الانسحاب من Frozen مسموح هنا لأنه انتقال مسموح في الـ enum ولا يحمل حقولًا.
 *  - UnderAssessment → Active تمر حصرًا عبر ReactivateEnrollmentAction بصلاحية enrollment.reactivate.
 */
final readonly class TransitionEnrollmentStatusAction
{
    use TransitionsEnrollmentStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Enrollment $enrollment, EnrollmentStatus $target, string $reason, ?string $actorId = null): Enrollment
    {
        if ($target === EnrollmentStatus::Paused) {
            throw BusinessRuleViolation::make(
                'enrollments.use_pause_action',
                'enrollments::errors.use_pause_action',
            );
        }

        if ($target === EnrollmentStatus::Frozen) {
            throw BusinessRuleViolation::make(
                'enrollments.use_freeze_action',
                'enrollments::errors.use_freeze_action',
            );
        }

        if ($enrollment->status === EnrollmentStatus::UnderAssessment && $target === EnrollmentStatus::Active) {
            throw BusinessRuleViolation::make(
                'enrollments.reactivation_requires_permission',
                'enrollments::errors.reactivation_requires_permission',
                ['permission' => 'enrollment.reactivate'],
            );
        }

        [$event] = $this->transaction->run(function () use ($enrollment, $target, $reason, $actorId): array {
            $from = $enrollment->status;

            $this->applyTransition($enrollment, $target, $reason, $actorId);

            return [new EnrollmentStatusChanged(
                enrollmentId: $enrollment->id,
                organizationId: $enrollment->organization_id,
                studentProfileId: $enrollment->student_profile_id,
                programId: $enrollment->program_id,
                fromStatus: $from->value,
                toStatus: $target->value,
                reason: $reason,
                actorId: $actorId,
            )];
        });

        $this->events->dispatch($event);

        return $enrollment->refresh();
    }
}
