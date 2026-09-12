<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Contracts\DirectPermissionGateway;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

/**
 * البوابة تمنح الصلاحيات الاختيارية المعلنة وحدها. بدون هذا الحارس تتحول
 * شاشة المستخدم إلى منح أي صلاحية في النظام مباشرة والتفاف على الأدوار.
 */
it('refuses to grant a permission that is not declared optional', function (): void {
    (new AccessControlSeeder)->run();

    $gateway = app(DirectPermissionGateway::class);
    $modelId = (string) Str::ulid();

    expect(fn (): bool => $gateway->grantIfMissing(
        'settings.manage',
        'user',
        $modelId,
        (string) Str::ulid(),
        (string) Str::ulid(),
        'محاولة منح صلاحية إدارية خارج القائمة',
    ))->toThrow(BusinessRuleViolation::class);

    $permissionId = Permission::query()->where('name', 'settings.manage')->value('id');

    expect(ModelHasPermission::query()
        ->where('permission_id', $permissionId)
        ->where('model_id', $modelId)
        ->exists())->toBeFalse();
});

it('is idempotent so a repeated switch state neither duplicates nor fails', function (): void {
    (new AccessControlSeeder)->run();

    $gateway = app(DirectPermissionGateway::class);
    $modelId = (string) Str::ulid();
    $organizationId = (string) Str::ulid();
    $actorId = (string) Str::ulid();

    expect($gateway->grantIfMissing('payroll.view', 'user', $modelId, $organizationId, $actorId, 'تكليف مراجعة'))
        ->toBeTrue()
        ->and($gateway->grantIfMissing('payroll.view', 'user', $modelId, $organizationId, $actorId, 'تكليف مراجعة'))
        ->toBeFalse();

    $permissionId = Permission::query()->where('name', 'payroll.view')->value('id');

    expect(ModelHasPermission::query()
        ->where('permission_id', $permissionId)
        ->where('model_id', $modelId)
        ->count())->toBe(1);

    expect($gateway->revokeIfPresent('payroll.view', 'user', $modelId, $organizationId, $actorId, 'انتهى التكليف'))
        ->toBeTrue()
        ->and($gateway->revokeIfPresent('payroll.view', 'user', $modelId, $organizationId, $actorId, 'انتهى التكليف'))
        ->toBeFalse();
});
