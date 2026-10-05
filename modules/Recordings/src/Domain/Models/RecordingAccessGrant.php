<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $recording_id
 * @property string|null $granted_to_user_id
 * @property string|null $granted_to_group_id
 * @property string $granted_by_user_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string $reason
 */
final class RecordingAccessGrant extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'recording_access_grants';

    protected $fillable = [
        'organization_id',
        'recording_id',
        'granted_to_user_id',
        'granted_to_group_id',
        'granted_by_user_id',
        'expires_at',
        'revoked_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at->isFuture();
    }
}
