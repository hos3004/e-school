<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class GrantRecordingAccessAction
{
    public function __construct(
        private Transaction $transaction,
        private UserAccountDirectory $accounts,
        private GroupAdministrationQueries $groups,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Recording $recording,
        string $grantedByUserId,
        ?string $grantedToUserId = null,
        ?string $grantedToGroupId = null,
        ?CarbonImmutable $expiresAt = null,
        ?string $reason = null,
    ): RecordingAccessGrant {
        if (($grantedToUserId === null) === ($grantedToGroupId === null)) {
            throw BusinessRuleViolation::make(
                'recordings.grant_target_invalid',
                'recordings::errors.grant_target_invalid',
            );
        }

        if ($reason === null || trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'recordings.grant_reason_required',
                'recordings::errors.grant_reason_required',
            );
        }

        if ($recording->trashed() || !in_array($recording->status, [RecordingStatus::Processing, RecordingStatus::Ready], true)) {
            throw BusinessRuleViolation::make(
                'recordings.grant_status_invalid',
                'recordings::errors.grant_status_invalid',
                ['status' => $recording->status->label()],
            );
        }

        if (trim($grantedByUserId) === ''
            || $this->accounts->find((string) $recording->organization_id, $grantedByUserId) === null) {
            throw BusinessRuleViolation::make(
                'recordings.granter_required',
                'recordings::errors.granter_required',
            );
        }

        $this->assertTargetBelongsToOrganization($recording, $grantedToUserId, $grantedToGroupId);

        $defaultDays = max(0, (int) config('recordings.grants.default_expires_days'));
        $expiresAt ??= $defaultDays === 0
            ? CarbonImmutable::instance($recording->expires_at)
            : CarbonImmutable::now('UTC')->addDays($defaultDays);

        if ((bool) config('recordings.grants.capped_by_recording_expiry', true)
            && $expiresAt->gt($recording->expires_at)) {
            $expiresAt = CarbonImmutable::instance($recording->expires_at);
        }

        if (!$expiresAt->isFuture()) {
            throw BusinessRuleViolation::make(
                'recordings.grant_expiry_invalid',
                'recordings::errors.grant_expiry_invalid',
            );
        }

        return $this->transaction->run(function () use ($recording, $grantedByUserId, $grantedToUserId, $grantedToGroupId, $expiresAt, $reason): RecordingAccessGrant {
            $duplicate = RecordingAccessGrant::query()
                ->where('recording_id', (string) $recording->getKey())
                ->where('granted_to_user_id', $grantedToUserId)
                ->where('granted_to_group_id', $grantedToGroupId)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->exists();
            if ($duplicate) {
                throw BusinessRuleViolation::make(
                    'recordings.grant_duplicate',
                    'recordings::errors.grant_duplicate',
                );
            }

            /** @var RecordingAccessGrant $grant */
            $grant = RecordingAccessGrant::query()->create([
                'organization_id' => $recording->organization_id,
                'recording_id' => $recording->id,
                'granted_to_user_id' => $grantedToUserId,
                'granted_to_group_id' => $grantedToGroupId,
                'granted_by_user_id' => $grantedByUserId,
                'expires_at' => $expiresAt,
                'reason' => trim($reason),
            ]);

            $this->audit->record(
                organizationId: (string) $recording->organization_id,
                actorId: $grantedByUserId,
                actorType: 'user',
                action: 'recordings.access_granted',
                auditableType: 'recordings',
                auditableId: (string) $recording->getKey(),
                oldValues: null,
                newValues: [
                    'grant_id' => (string) $grant->getKey(),
                    'granted_to_user_id' => $grantedToUserId,
                    'granted_to_group_id' => $grantedToGroupId,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
                reason: trim($reason),
            );

            return $grant;
        });
    }

    private function assertTargetBelongsToOrganization(
        Recording $recording,
        ?string $userId,
        ?string $groupId,
    ): void {
        $organizationId = (string) $recording->organization_id;
        $valid = $userId !== null
            ? $this->accounts->find($organizationId, $userId) !== null
            : isset($this->groups->groupsByIds($organizationId, [(string) $groupId])[(string) $groupId]);

        if (!$valid) {
            throw BusinessRuleViolation::make(
                'recordings.grant_target_not_found',
                'recordings::errors.grant_target_not_found',
            );
        }
    }
}
