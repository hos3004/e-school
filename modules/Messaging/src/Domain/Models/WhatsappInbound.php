<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $from_phone
 * @property string $message_id
 * @property string $body
 * @property array<int, array<string, mixed>>|null $media
 * @property CarbonImmutable $received_at
 * @property string|null $matched_user_id
 * @property string|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable $created_at
 */
final class WhatsappInbound extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'whatsapp_inbound';

    protected $fillable = [
        'organization_id',
        'from_phone',
        'message_id',
        'body',
        'media',
        'received_at',
        'matched_user_id',
        'handled_by',
        'handled_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'received_at' => 'immutable_datetime',
            'handled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
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
