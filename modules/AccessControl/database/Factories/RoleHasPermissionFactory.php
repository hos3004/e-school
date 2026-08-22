<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Domain\Models\RoleHasPermission;

/**
 * @extends Factory<RoleHasPermission>
 */
final class RoleHasPermissionFactory extends Factory
{
    protected $model = RoleHasPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'permission_id' => Permission::factory(),
        ];
    }
}
