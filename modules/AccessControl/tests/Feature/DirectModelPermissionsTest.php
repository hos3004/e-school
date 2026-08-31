<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AccessControl\Application\Actions\CreatePermissionAction;
use Modules\AccessControl\Application\Actions\GrantModelPermissionAction;
use Modules\AccessControl\Application\Actions\RevokeModelPermissionAction;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Events\ModelPermissionGranted;
use Modules\AccessControl\Domain\Events\ModelPermissionRevoked;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use PHPUnit\Framework\Assert;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

const AC_DIRECT_TYPE = 'staff_profiles';
const AC_DIRECT_ID = '01STAFF0000000000000000000';

function acDirectPermission(string $name): string
{
    return (string) app(CreatePermissionAction::class)->execute($name, GuardName::Web)->getKey();
}

it('grants a direct permission to a model and dispatches the event', function (): void {
    Event::fake([ModelPermissionGranted::class]);

    acDirectPermission('payroll.override');

    app(GrantModelPermissionAction::class)->execute('payroll.override', AC_DIRECT_TYPE, AC_DIRECT_ID);

    expect(app(AccessControlQuerier::class)->modelHasDirectPermission(AC_DIRECT_TYPE, AC_DIRECT_ID, 'payroll.override'))
        ->toBeTrue();

    Event::assertDispatched(ModelPermissionGranted::class);
});

it('rejects a duplicate direct grant', function (): void {
    acDirectPermission('billing.override');

    $action = app(GrantModelPermissionAction::class);
    $action->execute('billing.override', AC_DIRECT_TYPE, AC_DIRECT_ID);
    $action->execute('billing.override', AC_DIRECT_TYPE, AC_DIRECT_ID);
})->throws(BusinessRuleViolation::class);

it('rejects granting an unknown permission', function (): void {
    app(GrantModelPermissionAction::class)->execute('does.not.exist', AC_DIRECT_TYPE, AC_DIRECT_ID);
})->throws(BusinessRuleViolation::class);

it('revokes a direct permission and dispatches the event', function (): void {
    Event::fake([ModelPermissionRevoked::class]);

    acDirectPermission('content.purge');
    app(GrantModelPermissionAction::class)->execute('content.purge', AC_DIRECT_TYPE, AC_DIRECT_ID);
    app(RevokeModelPermissionAction::class)->execute('content.purge', AC_DIRECT_TYPE, AC_DIRECT_ID);

    expect(ModelHasPermission::query()
        ->where('model_type', AC_DIRECT_TYPE)
        ->where('model_id', AC_DIRECT_ID)
        ->exists())->toBeFalse();

    Event::assertDispatched(ModelPermissionRevoked::class);
});

it('refuses revoking a permission that was never granted', function (): void {
    try {
        app(RevokeModelPermissionAction::class)->execute('never.granted', AC_DIRECT_TYPE, AC_DIRECT_ID);
        Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('accesscontrol.permission.not_granted');
    }
});

it('audits direct permission grant and revocation with written reasons', function (): void {
    $organizationId = Fixtures::organizationId();
    $actorId = Fixtures::userId();
    $targetId = Fixtures::userId();
    acDirectPermission('sessions.override');

    app(GrantModelPermissionAction::class)->execute(
        'sessions.override',
        AC_DIRECT_TYPE,
        $targetId,
        $actorId,
        $organizationId,
        'approved emergency classroom support request',
    );

    app(RevokeModelPermissionAction::class)->execute(
        'sessions.override',
        AC_DIRECT_TYPE,
        $targetId,
        $actorId,
        $organizationId,
        'emergency support request has expired',
    );

    expect(DB::table('audit_log')->where([
        'action' => 'accesscontrol.permission_granted_directly',
        'auditable_id' => $targetId,
        'reason' => 'approved emergency classroom support request',
    ])->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where([
            'action' => 'accesscontrol.permission_revoked_directly',
            'auditable_id' => $targetId,
            'reason' => 'emergency support request has expired',
        ])->exists())->toBeTrue();
});
