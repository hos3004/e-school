<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;

it('seeds the base permission matrix and system roles idempotently', function (): void {
    $seeder = new AccessControlSeeder();
    $seeder->run();
    $seeder->run();

    $moduleCount = 7;
    $actionCount = 5;

    expect(Permission::query()->count())->toBe($moduleCount * $actionCount)
        ->and(Role::query()->where('is_system', true)->whereIn('name', ['super-admin', 'school-admin'])->count())->toBe(2);

    $superAdminId = (string) Role::query()->where('name', 'super-admin')->value('id');

    expect(DB::table('role_has_permissions')->where('role_id', $superAdminId)->count())
        ->toBe($moduleCount * $actionCount);
});
