<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Events\ReactivationReviewed;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * حسم طلب إعادة التفعيل: قبول أو رفض.
 *
 * القبول يشترط ربط نتيجة اختبار الجدية إذا كان الإعداد requires_assessment
 * مفعّلًا. القرار يمر حتمًا عبر canTransitionTo — لا كتابة حالة مباشرة.
 */
final readonly class DecideReactivationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array{decision: ReactivationStatus, decision_note: string,
     *               assessment_attempt_id?: ?string}  $data
     */
    public function execute(ReactivationRequest $request, array $data): ReactivationRequest
    {
        $decision = $data['decision'];

        if ($decision !== ReactivationStatus::Approved && $decision !== ReactivationStatus::Rejected) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_invalid_decision',
                'discipline::errors.reactivation_invalid_decision',
            );
        }

        if (!$request->status->canTransitionTo($decision)) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_invalid_transition',
                'discipline::errors.reactivation_invalid_transition',
                ['from' => $request->status->label(), 'to' => $decision->label()],
            );
        }

        $assessmentAttemptId = isset($data['assessment_attempt_id'])
            ? (string) $data['assessment_attempt_id']
            : null;

        if ($decision === ReactivationStatus::Approved
            && (bool) config('discipline.reactivation.requires_assessment', true)
            && ($assessmentAttemptId === null || $assessmentAttemptId === '')
        ) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_assessment_required',
                'discipline::errors.reactivation_assessment_required',
            );
        }

        $this->transaction->run(function () use ($request, $decision, $assessmentAttemptId, $data): void {
            $request->forceFill([
                'status' => $decision,
                'reviewer_id' => auth()->id(),
                'reviewed_at' => CarbonImmutable::now('UTC'),
                'decision_note' => (string) $data['decision_note'],
                'assessment_attempt_id' => $assessmentAttemptId,
            ])->save();
        });

        $this->events->dispatch(new ReactivationReviewed(
            reactivationRequestId: (string) $request->getKey(),
            organizationId: (string) $request->organization_id,
            enrollmentId: (string) $request->enrollment_id,
            decision: $decision,
            assessmentAttemptId: $assessmentAttemptId !== '' ? $assessmentAttemptId : null,
            reviewerId: auth()->id() !== null ? (string) auth()->id() : null,
        ));

        return $request;
    }
}
