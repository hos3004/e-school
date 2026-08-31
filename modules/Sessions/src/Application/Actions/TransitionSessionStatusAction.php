<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** بوابة واحدة لانتقالات الحصة: آلة الحالات + التاريخ + التدقيق في معاملة واحدة. */
final readonly class TransitionSessionStatusAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $metadata
     */
    public function execute(
        Session $session,
        SessionStatus $to,
        string $actorId,
        string $reason,
        string $auditAction,
        array $attributes = [],
        array $metadata = [],
    ): Session {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'sessions.reason_required',
                'sessions::errors.reason_required',
            );
        }

        return $this->transaction->run(function () use (
            $session,
            $to,
            $actorId,
            $reason,
            $auditAction,
            $attributes,
            $metadata,
        ): Session {
            /** @var Session $locked */
            $locked = Session::query()
                ->forOrganization((string) $session->organization_id)
                ->lockForUpdate()
                ->findOrFail((string) $session->getKey());
            $from = $locked->status;
            $before = [
                'status' => $from->value,
                'actual_start' => $locked->actual_start?->toIso8601String(),
                'actual_end' => $locked->actual_end?->toIso8601String(),
            ];

            $this->guardNotTerminal($locked);
            $this->applyTransition(
                $locked,
                $to,
                $attributes,
                $reason,
                $actorId,
                $metadata,
            );
            $this->audit->record(
                organizationId: (string) $locked->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: $auditAction,
                auditableType: 'sessions',
                auditableId: (string) $locked->getKey(),
                oldValues: $before,
                newValues: [
                    'status' => $to->value,
                    ...$attributes,
                    'metadata' => $metadata === [] ? null : $metadata,
                ],
                reason: $reason,
            );

            return $locked->refresh();
        });
    }
}
