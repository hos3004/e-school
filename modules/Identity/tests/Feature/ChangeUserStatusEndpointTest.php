<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Events\UserStatusChanged;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    $this->createTestOrganization();

    Gate::define('identity.users.change_status', fn ($user): bool => true);
});

it('changes a user status over HTTP with reason and event', function (): void {
    Event::fake([UserStatusChanged::class]);

    /** @var User $admin */
    $admin = User::factory()->inOrganization($this->organizationId)->create();

    /** @var User $target */
    $target = User::factory()->inOrganization($this->organizationId)->create([
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/identity/users/{$target->id}/status", [
            'status' => 'suspended',
            'reason' => 'محاولة اختراق متكررة',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);

    Event::assertDispatched(UserStatusChanged::class);
});

it('rejects status change without a written reason', function (): void {
    /** @var User $admin */
    $admin = User::factory()->inOrganization($this->organizationId)->create();
    /** @var User $target */
    $target = User::factory()->inOrganization($this->organizationId)->create();

    $this->actingAs($admin)
        ->patchJson("/api/identity/users/{$target->id}/status", [
            'status' => 'frozen',
            'reason' => '',
        ])
        ->assertStatus(422);

    Event::assertNotDispatched(UserStatusChanged::class);
});

it('forbids an admin changing their own status over HTTP', function (): void {
    /** @var User $admin */
    $admin = User::factory()->inOrganization($this->organizationId)->create();

    $this->actingAs($admin)
        ->patchJson("/api/identity/users/{$admin->id}/status", [
            'status' => 'suspended',
            'reason' => 'سبب مكتوب كامل',
        ])
        ->assertForbidden();
});
