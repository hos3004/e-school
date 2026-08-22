<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $conversation_id
 * @property string $user_id
 * @property string $body
 * @property array<int, array<string, mixed>>|null $attachments
 * @property bool $is_flagged
 * @property string|null $flagged_reason
 * @property string|null $moderated_by
 * @property CarbonImmutable|null $moderated_at
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable $created_at
 * @property Carbon|null $deleted_at
 * @property-read Conversation $conversation
 */
final class Message extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'messages';

    protected $fillable = [
        'organization_id',
        'conversation_id',
        'user_id',
        'body',
        'attachments',
        'is_flagged',
        'flagged_reason',
        'moderated_by',
        'moderated_at',
        'edited_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_flagged' => 'bool',
            'moderated_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
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
