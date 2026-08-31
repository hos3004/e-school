<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Support\AccessControlLabels;

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

    $sourcePermissionNames = AccessControlSeeder::permissionNames();
    $permissionCount = count($sourcePermissionNames);
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

    $seededPermissionNames = Permission::query()->orderBy('name')->pluck('name')->all();
    $expectedPermissionNames = $sourcePermissionNames;
    sort($expectedPermissionNames);

    expect($sourcePermissionNames)->toHaveCount(count(array_unique($sourcePermissionNames)))
        ->and($seededPermissionNames)->toBe($expectedPermissionNames)
        ->and(Role::query()
            ->whereNull('organization_id')
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
            ->whereNull('organization_id')
            ->where('name', $roleName)
            ->firstOrFail();
        $permissionNames = $role->permissions()->pluck('name')->all();

        expect(in_array('student.view.any', $permissionNames, true))
            ->toBe(in_array($roleName, $studentViewAnyRoles, true));
        expect(in_array('staff.view.any', $permissionNames, true))
            ->toBe(in_array($roleName, $staffViewAnyRoles, true));
    }

    $academicSupervisor = Role::query()
        ->whereNull('organization_id')
        ->where('name', 'academic_supervisor')
        ->firstOrFail();

    expect($academicSupervisor->permissions()->where('name', 'staff.availability.approve')->exists())
        ->toBeTrue();

    $teacher = Role::query()
        ->whereNull('organization_id')
        ->where('name', 'teacher')
        ->firstOrFail();

    expect($teacher->permissions()->where('name', 'staff.availability.create')->exists())
        ->toBeTrue();

    $popupPermissions = [
        'popup_campaign.view_any',
        'popup_campaign.view',
        'popup_campaign.view_analytics',
        'popup_campaign.create',
        'popup_campaign.update',
        'popup_campaign.publish',
        'popup_campaign.pause',
        'popup_campaign.archive',
    ];

    foreach ($popupPermissions as $popupPermission) {
        expect($sourcePermissionNames)->toContain($popupPermission);
    }

    $communicationsOfficer = Role::query()
        ->whereNull('organization_id')
        ->where('name', 'communications_officer')
        ->firstOrFail();

    expect($communicationsOfficer->permissions()
        ->whereIn('name', $popupPermissions)
        ->count())->toBe(count($popupPermissions));

    $auditor = Role::query()
        ->whereNull('organization_id')
        ->where('name', 'auditor')
        ->firstOrFail();
    $auditorPermissionNames = $auditor->permissions()->pluck('name')->all();

    foreach (['program.manage', 'course.manage', 'assignment.manage', 'assessment.manage'] as $writePermission) {
        expect($auditorPermissionNames)->not->toContain($writePermission);
    }

    foreach (['grade.view', 'session_report.view', 'audit.view'] as $readPermission) {
        expect($auditorPermissionNames)->toContain($readPermission);
    }

    $originalLocale = app()->getLocale();

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ($popupPermissions as $permission) {
            expect(AccessControlLabels::permission($permission))->not->toBe($permission);
        }
    }

    app()->setLocale($originalLocale);
});
