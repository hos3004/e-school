<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Events\ReactivationReviewed;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * سحب طلب إعادة التفعيل من مقدِّمه قبل حسمه — انتقال عبر canTransitionTo.
 */
final readonly class CancelReactivationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(ReactivationRequest $request): ReactivationRequest
    {
        if (!$request->status->canTransitionTo(ReactivationStatus::Cancelled)) {
            throw BusinessRuleViolation::make(
                'discipline.reactionation_cancellation_not_allowed',
                'discipline::errors.reactivation_cancellation_not_allowed',
                ['status' => $request->status->label()],
            );
        }

        $this->transaction->run(function () use ($request): void {
            $request->forceFill([
                'status' => ReactivationStatus::Cancelled,
                'reviewed_at' => null,
            ])->save();
        });

        $this->events->dispatch(new ReactivationReviewed(
            reactivationRequestId: (string) $request->getKey(),
            organizationId: (string) $request->organization_id,
            enrollmentId: (string) $request->enrollment_id,
            decision: ReactivationStatus::Cancelled,
            assessmentAttemptId: $request->assessment_attempt_id !== null
                ? (string) $request->assessment_attempt_id
                : null,
            reviewerId: auth()->id() !== null ? (string) auth()->id() : null,
        ));

        return $request;
    }
}
