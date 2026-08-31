<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementScheduled;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** اعتماد الموعد عبر عقد Sessions؛ لا كتابة في جدول يملكه موديول آخر. */
final readonly class ApprovePostponement
{
    public function __construct(
        private Transaction $transaction,
        private SessionSchedulingQueries $sessionQueries,
        private SessionSchedulingGateway $sessions,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $requestId,
        string $approvedBy,
        CarbonImmutable $agreedStart,
        string $reason,
    ): PostponementRequest {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('scheduling.reason_required', 'scheduling::errors.reason_required');
        }
        if ($agreedStart->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make('postponement.proposed_start_in_past', 'scheduling::errors.proposed_start_in_past');
        }

        /** @var PostponementRequest|null $request */
        $request = PostponementRequest::query()
            ->forOrganization($organizationId)
            ->whereKey($requestId)
            ->first();
        if ($request === null) {
            throw BusinessRuleViolation::make('postponement.not_found', 'scheduling::errors.postponement_not_found');
        }
        if (!$request->status->canTransitionTo(PostponementStatus::Scheduled)) {
            throw BusinessRuleViolation::make(
                'postponement.invalid_transition',
                'scheduling::errors.postponement_invalid_transition',
                ['from' => $request->status->value, 'to' => PostponementStatus::Scheduled->value],
            );
        }

        $original = $this->sessionQueries->find($organizationId, (string) $request->session_id);
        if ($original === null) {
            throw BusinessRuleViolation::make('session.not_found', 'scheduling::errors.session_not_found');
        }
        $windowDays = (int) config('scheduling.postponement.makeup_window_days');
        if ($agreedStart->greaterThan($original->scheduledStart->addDays($windowDays))) {
            throw BusinessRuleViolation::make(
                'postponement.outside_makeup_window',
                'scheduling::errors.outside_makeup_window',
                ['days' => $windowDays],
            );
        }

        $old = ['status' => $request->status->value, 'agreed_start' => $request->agreed_start?->toIso8601String()];
        $updated = $this->transaction->run(function () use (
            $organizationId,
            $request,
            $approvedBy,
            $agreedStart,
            $reason,
            $old,
        ): PostponementRequest {
            $makeupId = $this->sessions->scheduleMakeup(
                $organizationId,
                (string) $request->session_id,
                $agreedStart,
                $approvedBy,
                $reason,
            );
            $request->fill([
                'status' => PostponementStatus::Scheduled,
                'agreed_start' => $agreedStart,
                'makeup_session_id' => $makeupId,
                'responded_by' => $approvedBy,
                'responded_at' => CarbonImmutable::now('UTC'),
                'admin_note' => $reason,
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $approvedBy,
                actorType: 'user',
                action: 'scheduling.postponement_approved',
                auditableType: 'postponement_requests',
                auditableId: (string) $request->getKey(),
                oldValues: $old,
                newValues: [
                    'status' => PostponementStatus::Scheduled->value,
                    'agreed_start' => $agreedStart->toIso8601String(),
                    'makeup_session_id' => $makeupId,
                ],
                reason: $reason,
            );

            return $request;
        });

        event(new PostponementScheduled(
            requestId: (string) $updated->getKey(),
            sessionId: (string) $updated->session_id,
            makeupSessionId: (string) $updated->makeup_session_id,
            agreedStart: $agreedStart->toIso8601String(),
            actorId: $approvedBy,
        ));

        return $updated;
    }
}
