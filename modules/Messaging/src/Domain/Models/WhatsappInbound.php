<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'received_at' => 'immutable_datetime',
            'handled_at' => 'immutable_datetime',
        ];
    }

    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
