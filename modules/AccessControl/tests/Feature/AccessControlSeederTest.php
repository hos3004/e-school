<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;

uses(RefreshDatabase::class);

it('seeds the base permission matrix and system roles idempotently', function (): void {
    $organizationId = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $organizationId,
        'name' => json_encode(['ar' => 'مدرسة الاختبار', 'en' => 'Test School'], JSON_UNESCAPED_UNICODE),
        'slug' => 'access-control-test-school',
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);

    $seeder = new AccessControlSeeder;
    $seeder->run();
    $seeder->run();

    $permissionCount = 74;
    $systemRoleNames = [
        'platform_admin',
        'academic_supervisor',
        'finance_supervisor',
        'registrar',
        'communications_officer',
        'teacher',
        'student',
        'guardian',
        'auditor',
    ];

    expect(Permission::query()->count())->toBe($permissionCount)
        ->and(Role::query()
            ->where('organization_id', $organizationId)
            ->where('is_system', true)
            ->whereIn('name', $systemRoleNames)
            ->count())->toBe(count($systemRoleNames));

    $platformAdminId = (string) Role::query()->where('name', 'platform_admin')->value('id');

    expect(DB::table('role_has_permissions')->where('role_id', $platformAdminId)->count())
        ->toBe($permissionCount);

    $studentViewAnyRoles = [
        'platform_admin',
        'academic_supervisor',
        'finance_supervisor',
        'registrar',
        'communications_officer',
        'auditor',
    ];
    $staffViewAnyRoles = [
        'platform_admin',
        'academic_supervisor',
        'finance_supervisor',
        'registrar',
        'auditor',
    ];

    foreach ($systemRoleNames as $roleName) {
        $role = Role::query()
            ->where('organization_id', $organizationId)
            ->where('name', $roleName)
            ->firstOrFail();
        $permissionNames = $role->permissions()->pluck('name')->all();

        expect(in_array('student.view.any', $permissionNames, true))
            ->toBe(in_array($roleName, $studentViewAnyRoles, true));
        expect(in_array('staff.view.any', $permissionNames, true))
            ->toBe(in_array($roleName, $staffViewAnyRoles, true));
    }
});
