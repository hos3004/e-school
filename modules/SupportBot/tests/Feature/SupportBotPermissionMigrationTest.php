<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;

/*
| هجرة الصلاحيات تُضيف ولا تُزامن.
|
| الإنتاج يحمل تخصيصات أجرتها الإدارة على الأدوار من اللوحة؛ لو مسّتها الهجرة
| لمحت عملًا إداريًّا صامتًا. هذا ما يمنع استعمال AccessControlSeeder في النشر.
*/

/** @return Migration */
function supportBotGrantMigration(): object
{
    return require base_path('modules/AccessControl/database/migrations/2026_09_22_180100_grant_support_bot_permissions.php');
}

beforeEach(function (): void {
    $this->seed(AccessControlSeeder::class);
});

it('grants both permissions to the platform admin without touching other grants', function (): void {
    $adminRole = DB::table('roles')->whereNull('organization_id')->where('name', 'platform_admin')->value('id');
    $teacherRole = DB::table('roles')->whereNull('organization_id')->where('name', 'teacher')->value('id');

    // حالة إنتاج: الصلاحيتان غائبتان، والإدارة منحت المعلم صلاحية إضافية من اللوحة.
    $botPermissions = DB::table('permissions')->whereIn('name', ['support_bot.manage', 'support_bot.archive.view'])->pluck('id');
    DB::table('role_has_permissions')->whereIn('permission_id', $botPermissions)->delete();
    DB::table('permissions')->whereIn('id', $botPermissions)->delete();

    $customPermission = (string) Str::ulid();
    DB::table('permissions')->insert([
        'id' => $customPermission, 'name' => 'custom.admin.grant', 'guard_name' => 'web',
        'module' => 'AccessControl', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('role_has_permissions')->insert(['role_id' => $teacherRole, 'permission_id' => $customPermission]);

    $teacherBefore = DB::table('role_has_permissions')->where('role_id', $teacherRole)->count();

    supportBotGrantMigration()->up();

    foreach (['support_bot.manage', 'support_bot.archive.view'] as $name) {
        $id = DB::table('permissions')->where('name', $name)->value('id');

        expect(DB::table('role_has_permissions')->where('role_id', $adminRole)->where('permission_id', $id)->exists())
            ->toBeTrue("{$name} not granted to platform_admin");
    }

    // تخصيص الإدارة باقٍ، ولا صلاحية بوت تسرّبت إلى المعلم.
    expect(DB::table('role_has_permissions')->where('role_id', $teacherRole)->count())->toBe($teacherBefore)
        ->and(DB::table('role_has_permissions')->where('role_id', $teacherRole)->where('permission_id', $customPermission)->exists())->toBeTrue();
});

it('is idempotent and reversible', function (): void {
    $migration = supportBotGrantMigration();

    $migration->up();
    $migration->up();

    expect(DB::table('permissions')->where('name', 'support_bot.manage')->count())->toBe(1);

    $migration->down();

    expect(DB::table('permissions')->whereIn('name', ['support_bot.manage', 'support_bot.archive.view'])->count())->toBe(0);
});
