<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Shared\Support\BusinessRuleViolation;

final readonly class GrantRecordingAccessAction
{
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

        $expiresAt = $expiresAt ?? CarbonImmutable::now('UTC')->addDays(7);

        if (!$expiresAt->isFuture()) {
            throw BusinessRuleViolation::make(
                'recordings.grant_expiry_invalid',
                'recordings::errors.grant_expiry_invalid',
            );
        }

        return DB::transaction(function () use ($recording, $grantedByUserId, $grantedToUserId, $grantedToGroupId, $expiresAt, $reason): RecordingAccessGrant {
            return RecordingAccessGrant::query()->create([
                'organization_id' => $recording->organization_id,
                'recording_id' => $recording->id,
                'granted_to_user_id' => $grantedToUserId,
                'granted_to_group_id' => $grantedToGroupId,
                'granted_by_user_id' => $grantedByUserId,
                'expires_at' => $expiresAt,
                'reason' => trim($reason),
            ]);
        });
    }
}
