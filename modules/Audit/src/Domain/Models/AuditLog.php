<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Database\Factories\AuditLogFactory;
use Modules\Audit\Domain\Enums\AuditActorType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * قيدة تدقيق — سجل append-only لا يُعدَّل ولا يُحذف.
 *
 * أي تصحيح يتم بقيدة جديدة تشرح السبب، وليس بتعديل القيدة القديمة.
 * الجدول بلا updated_at ولا deleted_at عمدًا: القيدة تُولد مرة واحدة
 * وتبقى كما هي. لذلك لا يستخدم هذا النموذج SoftDeletes.
 */
final class AuditLog extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'audit_log';

    /** دفتر أستاذ — لا تحديثات ولا حذف عبر Eloquent. */
    public $timestamps = false;

    protected static string $factory = AuditLogFactory::class;

    protected $fillable = [
        'organization_id',
        'actor_id',
        'actor_type',
        'acting_for_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'correlation_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime:immutable',
        ];
    }

    /**
     * قيود مؤسسة معينة.
     */
    public function scopeForOrganization(Builder $query, ?string $organizationId): Builder
    {
        return $query->when(
            $organizationId !== null && $organizationId !== '',
            fn (Builder $q): Builder => $q->where('organization_id', $organizationId),
        );
    }

    /**
     * قيود فعل معين (نصّ حر أو حالة قياسية).
     */
    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', strtolower($action));
    }
}
