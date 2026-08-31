<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Concerns;

use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;

trait UsesRealAccessControl
{
    public function seedRealAccessControl(): void
    {
        (new AccessControlSeeder)->run();
        app(PermissionGateRegistrar::class)->register();
    }

    public function assignRealRole(User $user, string $roleName): void
    {
        $role = Role::query()
            ->includingGlobal($user->organization_id)
            ->where('name', $roleName)
            ->firstOrFail();

        ModelHasRole::query()->create([
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);
    }
}
