<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class RevokeRecordingAccessAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Recording $recording,
        string $grantId,
        string $actorId,
        string $reason,
    ): RecordingAccessGrant {
        if (trim($actorId) === '' || trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'recordings.revocation_context_required',
                'recordings::errors.revocation_context_required',
            );
        }

        return $this->transaction->run(function () use ($recording, $grantId, $actorId, $reason): RecordingAccessGrant {
            /** @var RecordingAccessGrant|null $grant */
            $grant = RecordingAccessGrant::query()
                ->where('organization_id', (string) $recording->organization_id)
                ->where('recording_id', (string) $recording->getKey())
                ->whereKey($grantId)
                ->lockForUpdate()
                ->first();
            if ($grant === null) {
                throw BusinessRuleViolation::make(
                    'recordings.grant_not_found',
                    'recordings::errors.grant_not_found',
                );
            }

            if ($grant->revoked_at !== null) {
                throw BusinessRuleViolation::make(
                    'recordings.grant_already_revoked',
                    'recordings::errors.grant_already_revoked',
                );
            }

            $grant->forceFill(['revoked_at' => now('UTC')])->save();
            $this->audit->record(
                organizationId: (string) $recording->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'recordings.access_revoked',
                auditableType: 'recordings',
                auditableId: (string) $recording->getKey(),
                oldValues: ['grant_id' => (string) $grant->getKey(), 'revoked_at' => null],
                newValues: ['grant_id' => (string) $grant->getKey(), 'revoked_at' => $grant->revoked_at?->toIso8601String()],
                reason: trim($reason),
            );

            return $grant;
        });
    }
}
