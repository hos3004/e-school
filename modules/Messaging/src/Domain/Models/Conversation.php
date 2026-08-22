<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $subject
 * @property string $type
 * @property bool $is_moderated
 * @property string|null $related_type
 * @property string|null $related_id
 * @property string $created_by
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable $created_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ConversationParticipant> $participants
 * @property-read Collection<int, Message> $messages
 */
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
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_moderated' => 'bool',
            'last_message_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
