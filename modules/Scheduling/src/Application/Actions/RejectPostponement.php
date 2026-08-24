<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementRejected;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class RejectPostponement
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(string $requestId, string $rejectedBy, string $reason): PostponementRequest
    {
        $request = PostponementRequest::query()->findOrFail($requestId);
        $reason = trim($reason);

        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'postponement.rejection_reason_required',
                'scheduling::errors.rejection_reason_required',
            );
        }

        if (!$request->status->canTransitionTo(PostponementStatus::Rejected)) {
            throw BusinessRuleViolation::make(
                'postponement.invalid_transition',
                'scheduling::errors.postponement_invalid_transition',
                ['from' => $request->status->value, 'to' => PostponementStatus::Rejected->value],
            );
        }

        $request = $this->transaction->run(function () use ($request, $rejectedBy, $reason): PostponementRequest {
            $request->fill([
                'status' => PostponementStatus::Rejected,
                'admin_note' => $reason,
                'responded_by' => $rejectedBy,
                'responded_at' => CarbonImmutable::now('UTC'),
            ]);
            $request->save();

            return $request;
        });

        event(new PostponementRejected(
            requestId: (string) $request->getKey(),
            sessionId: (string) $request->session_id,
            reason: $reason,
            actorId: $rejectedBy,
        ));

        return $request;
    }
}
