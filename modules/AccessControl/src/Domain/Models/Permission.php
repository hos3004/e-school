<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\AccessControl\Domain\Enums\GuardName;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * صلاحية ذرّية واحدة: resource.action مثل students.view_any.
 *
 * الصلاحيات هي الحقيقة المركزية التي تُبنى عليها مصفوفة الأدوار؛
 * لا يفحص أي موديول اسم دور أبدًا — يفحص صلاحية عبر $user->can().
 *
 * @property string $id
 * @property string $name
 * @property GuardName $guard_name
 * @property string|null $module
 * @property array<string, string>|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class Permission extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'guard_name' => GuardName::class,
            'description' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param Builder<Permission> $query
     * @return Builder<Permission>
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * @param Builder<Permission> $query
     * @return Builder<Permission>
     */
    public function scopeOfGuard(Builder $query, GuardName $guard): Builder
    {
        return $query->where('guard_name', $guard);
    }
}
