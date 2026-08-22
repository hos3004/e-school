<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Models;

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

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeOfGuard(Builder $query, GuardName $guard): Builder
    {
        return $query->where('guard_name', $guard);
    }
}
