<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class ConversationParticipant extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'conversation_participants';

    protected $fillable = [
        'organization_id',
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'last_read_at',
        'muted_until',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'last_read_at' => 'immutable_datetime',
            'muted_until' => 'immutable_datetime',
        ];
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
