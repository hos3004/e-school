<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Application\Policies\UserPolicy;
use Modules\Identity\Domain\Models\User;

function identityActor(bool $granted): User
{
    Gate::define('identity.users.view', fn ($user): bool => $granted);
    Gate::define('identity.users.delete', fn ($user): bool => $granted);
    Gate::define('identity.users.change_status', fn ($user): bool => $granted);
    Gate::define('identity.users.view_any', fn ($user): bool => $granted);
    Gate::define('identity.users.create', fn ($user): bool => $granted);
    Gate::define('identity.users.update', fn ($user): bool => $granted);

    /** @var User $actor */
    $actor = User::factory()->make(['id' => (string) Str::ulid()]);

    return $actor;
}

it('lets a user view themselves even without permissions', function (): void {
    $actor = identityActor(granted: false);
    $other = User::factory()->make(['id' => (string) Str::ulid()]);

    expect($actor->can('view', $actor))->toBeTrue()
        ->and($actor->can('view', $other))->toBeFalse();
});

it('never lets a user delete or suspend themselves', function (): void {
    // حتى مع الصلاحية الممنوحة، الحذف الذاتي وتغيير الحالة الذاتي مرفوضان.
    $privileged = identityActor(granted: true);

    expect($privileged->can('delete', $privileged))->toBeFalse()
        ->and($privileged->can('changeStatus', $privileged))->toBeFalse();
});

it('grants privileged staff full management over others', function (): void {
    $staff = identityActor(granted: true);
    $target = User::factory()->suspended()->make(['id' => (string) Str::ulid()]);

    expect($staff->can('viewAny', User::class))->toBeTrue()
        ->and($staff->can('view', $target))->toBeTrue()
        ->and($staff->can('update', $target))->toBeTrue()
        ->and($staff->can('delete', $target))->toBeTrue()
        ->and($staff->can('changeStatus', $target))->toBeTrue();
});

it('denies everything for unprivileged actors on other records', function (): void {
    $plain = identityActor(granted: false);
    $target = User::factory()->make(['id' => (string) Str::ulid()]);

    expect($plain->can('viewAny', User::class))->toBeFalse()
        ->and($plain->can('update', $target))->toBeFalse()
        ->and($plain->can('delete', $target))->toBeFalse()
        ->and($plain->can('changeStatus', $target))->toBeFalse()
        // تحديث حسابه الخاص مسموح بالملكية بلا أي صلاحية إضافية.
        ->and($plain->can('update', $plain))->toBeTrue();
});

it('maps the policy to the model through the service provider', function (): void {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);
});
