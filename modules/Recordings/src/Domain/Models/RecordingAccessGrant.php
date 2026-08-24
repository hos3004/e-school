<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
