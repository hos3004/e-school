<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Conversation extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'conversations';

    protected $fillable = [
        'organization_id',
        'subject',
        'type',
        'is_moderated',
        'related_type',
        'related_id',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_moderated' => 'bool',
            'last_message_at' => 'immutable_datetime',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
