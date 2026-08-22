<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_flagged' => 'bool',
            'moderated_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
