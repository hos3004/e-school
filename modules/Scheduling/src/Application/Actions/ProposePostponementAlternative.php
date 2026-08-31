<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementAlternativeProposed;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ProposePostponementAlternative
{
    public function __construct(private Transaction $transaction, private AuditRecorder $audit) {}

    public function execute(
        string $organizationId,
        string $requestId,
        string $actorId,
        CarbonImmutable $proposedStart,
        string $reason,
    ): PostponementRequest {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if ($proposedStart->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make('postponement.proposed_start_in_past', 'scheduling::errors.proposed_start_in_past');
        }

        /** @var PostponementRequest|null $request */
        $request = PostponementRequest::query()->forOrganization($organizationId)->whereKey($requestId)->first();
        if ($request === null) {
            throw BusinessRuleViolation::make('postponement.not_found', 'scheduling::errors.postponement_not_found');
        }
        if (!$request->status->canTransitionTo(PostponementStatus::AlternativeProposed)) {
            throw BusinessRuleViolation::make('postponement.invalid_transition', 'scheduling::errors.postponement_invalid_transition');
        }

        $request = $this->transaction->run(function () use ($organizationId, $request, $actorId, $proposedStart, $reason): PostponementRequest {
            $request->fill([
                'status' => PostponementStatus::AlternativeProposed,
                'proposed_by_teacher_start' => $proposedStart,
                'teacher_note' => $reason,
                'responded_by' => $actorId,
                'responded_at' => CarbonImmutable::now('UTC'),
                'expires_at' => CarbonImmutable::now('UTC')->addHours((int) config('scheduling.postponement.teacher_response_sla_hours')),
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.postponement_alternative_proposed',
                auditableType: 'postponement_requests',
                auditableId: (string) $request->getKey(),
                oldValues: ['status' => PostponementStatus::Requested->value],
                newValues: [
                    'status' => PostponementStatus::AlternativeProposed->value,
                    'proposed_start' => $proposedStart->toIso8601String(),
                ],
                reason: $reason,
            );

            return $request;
        });

        event(new PostponementAlternativeProposed(
            requestId: (string) $request->getKey(),
            sessionId: (string) $request->session_id,
            teacherProposedStart: $proposedStart->toIso8601String(),
            actorId: $actorId,
        ));

        return $request;
    }
}
