<?php

declare(strict_types=1);

use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Application\Policies\UserPolicy;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Concerns\UsesRealAccessControl;

uses(CreatesTestOrganization::class, UsesRealAccessControl::class);

beforeEach(function (): void {
    $this->createTestOrganization();
    $this->seedRealAccessControl();
});

it('allows self-service but denies every cross-tenant object even with real admin permissions', function (): void {
    $actor = User::factory()->inOrganization($this->organizationId)->create();
    $sameTenant = User::factory()->inOrganization($this->organizationId)->create();
    $firstOrganization = $this->organizationId;
    $otherOrganization = $this->createTestOrganization();
    $foreign = User::factory()->inOrganization($otherOrganization)->create();
    $this->assignRealRole($actor, 'platform_admin');

    expect($actor->can('viewAny', User::class))->toBeTrue()
        ->and($actor->can('view', $actor))->toBeTrue()
        ->and($actor->can('update', $actor))->toBeTrue()
        ->and($actor->can('view', $sameTenant))->toBeTrue()
        ->and($actor->can('changeStatus', $sameTenant))->toBeTrue()
        ->and($actor->can('view', $foreign))->toBeFalse()
        ->and($actor->can('update', $foreign))->toBeFalse()
        ->and($actor->can('changeStatus', $foreign))->toBeFalse()
        ->and($actor->organization_id)->toBe($firstOrganization);
});

it('forbids user deletion and self status changes', function (): void {
    $actor = User::factory()->inOrganization($this->organizationId)->create();
    $target = User::factory()->inOrganization($this->organizationId)->create();
    $this->assignRealRole($actor, 'platform_admin');

    expect($actor->can('delete', $target))->toBeFalse()
        ->and($actor->can('changeStatus', $actor))->toBeFalse();
});

it('uses capabilities for admin panel access and denies teacher and student accounts', function (): void {
    $admin = User::factory()->inOrganization($this->organizationId)->create();
    $supervisor = User::factory()->inOrganization($this->organizationId)->create();
    $teacher = User::factory()->inOrganization($this->organizationId)->create();
    $student = User::factory()->inOrganization($this->organizationId)->create();
    $this->assignRealRole($admin, 'platform_admin');
    $this->assignRealRole($supervisor, 'academic_supervisor');
    $this->assignRealRole($teacher, 'teacher');
    $this->assignRealRole($student, 'student');
    $panel = Panel::make()->id('admin');

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($supervisor->canAccessPanel($panel))->toBeTrue()
        ->and($teacher->canAccessPanel($panel))->toBeFalse()
        ->and($student->canAccessPanel($panel))->toBeFalse();
});

it('maps the policy to the model through the service provider', function (): void {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);
});
