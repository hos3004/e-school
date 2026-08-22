<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AccessControl\Domain\Enums\GuardName;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * دور داخل منظمة واحدة (أو دور عام organization_id = null).
 *
 * is_system يعني أن الدور معرّف من المنصة نفسها: يُمنع تعديل اسمه
 * أو حذفه — فقط ربط الصلاحيات به مسموح عبر الإجراءات المعتمدة.
 */
final class Role extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'roles';

    protected $fillable = [
        'organization_id',
        'name',
        'guard_name',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'guard_name' => GuardName::class,
            'is_system' => 'bool',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeIncludingGlobal(Builder $query, string $organizationId): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->where('organization_id', $organizationId)
            ->orWhereNull('organization_id'));
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }
}
