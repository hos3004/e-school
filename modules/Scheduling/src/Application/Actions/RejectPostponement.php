<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Services\PostponementRecipientResolver;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementRejected;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class RejectPostponement
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
        private PostponementRecipientResolver $recipients,
    ) {}

    public function execute(
        string $organizationId,
        string $requestId,
        string $rejectedBy,
        string $reason,
    ): PostponementRequest {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make('postponement.rejection_reason_required', 'scheduling::errors.rejection_reason_required');
        }

        /** @var PostponementRequest|null $request */
        $request = PostponementRequest::query()->forOrganization($organizationId)->whereKey($requestId)->first();
        if ($request === null) {
            throw BusinessRuleViolation::make('postponement.not_found', 'scheduling::errors.postponement_not_found');
        }
        if (!$request->status->canTransitionTo(PostponementStatus::Rejected)) {
            throw BusinessRuleViolation::make(
                'postponement.invalid_transition',
                'scheduling::errors.postponement_invalid_transition',
                ['from' => $request->status->value, 'to' => PostponementStatus::Rejected->value],
            );
        }

        $from = $request->status->value;
        $request = $this->transaction->run(function () use ($organizationId, $request, $rejectedBy, $reason, $from): PostponementRequest {
            $request->fill([
                'status' => PostponementStatus::Rejected,
                'admin_note' => $reason,
                'responded_by' => $rejectedBy,
                'responded_at' => CarbonImmutable::now('UTC'),
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $rejectedBy,
                actorType: 'user',
                action: 'scheduling.postponement_rejected',
                auditableType: 'postponement_requests',
                auditableId: (string) $request->getKey(),
                oldValues: ['status' => $from],
                newValues: ['status' => PostponementStatus::Rejected->value],
                reason: $reason,
            );

            return $request;
        });

        $recipients = $this->recipients->forSession($organizationId, (string) $request->session_id, $request->requested_for_student_id);
        event(new PostponementRejected(
            requestId: (string) $request->getKey(),
            sessionId: (string) $request->session_id,
            reason: $reason,
            organizationId: $organizationId,
            studentUserIds: $recipients['student_user_ids'],
            teacherUserId: $recipients['teacher_user_id'],
            actorId: $rejectedBy,
        ));

        return $request;
    }
}
