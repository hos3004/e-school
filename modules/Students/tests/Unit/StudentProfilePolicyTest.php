<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    $this->policy = new StudentProfilePolicy;

    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->student = StudentProfile::factory()->create([
        'organization_id' => (string) $this->owner->organization_id,
        'user_id' => $this->owner->getKey(),
        'student_code' => 'STU-PO-'.str()->random(4),
    ]);
});

it('lets the student view their own profile without any ability', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.view.any', fn ($user): bool => false);

    expect($this->policy->viewAny($this->owner))->toBeFalse()
        ->and($this->policy->view($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->view($this->stranger, $this->student))->toBeFalse();
});

it('lets anyone with student view any permission view profiles in their organization', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.view.any', fn ($user): bool => true);

    expect($this->policy->view($this->stranger, $this->student))->toBeTrue()
        ->and($this->policy->viewAny($this->stranger))->toBeTrue();
});

it('allows update via student update permission or direct ownership', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.update', fn ($user): bool => false);

    expect($this->policy->update($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->update($this->stranger, $this->student))->toBeFalse();

    Gate::define('student.update', fn ($user): bool => true);
    expect($this->policy->update($this->stranger, $this->student))->toBeTrue();
});

it('never grants archive or restore by ownership — abilities only', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.update', fn ($user): bool => false);

    expect($this->policy->delete($this->owner, $this->student))->toBeFalse()
        ->and($this->policy->restore($this->owner, $this->student))->toBeFalse();

    Gate::define('student.update', fn ($user): bool => true);

    expect($this->policy->delete($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->restore($this->owner, $this->student))->toBeTrue();
});

it('gates create behind student create only', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.create', fn ($user): bool => false);

    expect($this->policy->create($this->owner))->toBeFalse();

    Gate::define('student.create', fn ($user): bool => true);

    expect($this->policy->create($this->owner))->toBeTrue();
});

it('denies every record action across organization boundaries despite abilities', function (): void {
    /** @var \Modules\Students\Tests\Support\StudentsPestContext $this */
    Gate::define('student.view.any', fn ($user): bool => true);
    Gate::define('student.update', fn ($user): bool => true);

    $otherOrganizationUser = User::factory()->make([
        'organization_id' => (string) str()->ulid(),
    ]);

    expect($this->policy->view($otherOrganizationUser, $this->student))->toBeFalse()
        ->and($this->policy->update($otherOrganizationUser, $this->student))->toBeFalse()
        ->and($this->policy->delete($otherOrganizationUser, $this->student))->toBeFalse()
        ->and($this->policy->restore($otherOrganizationUser, $this->student))->toBeFalse();
});
