<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * سجل الأحداث المُدخلة — دفتر append-only لكل حدث خارجي عالجه الموديول.
 *
 * فرادة event_id تجعل الإدخال idempotent: الحدث نفسه لا يُعالَج مرتين،
 * والسجل يتيح إعادة بناء اللوحات من نقطة زمنية.
 */
final class ReportEventLog extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'report_event_log';

    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'module',
        'actor_id',
        'correlation_id',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOfName(Builder $query, string $eventName): Builder
    {
        return $query->where('name', $eventName);
    }
}
