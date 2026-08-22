<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Students\Application\Policies\StudentProfilePolicy;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
    Gate::define('students.view_any', fn ($user) => false);

    expect($this->policy->view($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->view($this->stranger, $this->student))->toBeFalse();
});

it('lets anyone with view_any view all profiles', function (): void {
    Gate::define('students.view_any', fn ($user) => true);

    expect($this->policy->view($this->stranger, $this->student))->toBeTrue()
        ->and($this->policy->viewAny($this->stranger))->toBeTrue();
});

it('allows update via update_any or ownership with update_own', function (): void {
    Gate::define('students.update_any', fn ($user) => false);
    Gate::define('students.update_own', fn ($user) => false);

    expect($this->policy->update($this->owner, $this->student))->toBeFalse();

    Gate::define('students.update_own', fn ($user) => true);
    expect($this->policy->update($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->update($this->stranger, $this->student))->toBeFalse();

    Gate::define('students.update_any', fn ($user) => true);
    expect($this->policy->update($this->stranger, $this->student))->toBeTrue();
});

it('never grants archive or restore by ownership — abilities only', function (): void {
    expect($this->policy->delete($this->owner, $this->student))->toBeFalse()
        ->and($this->policy->restore($this->owner, $this->student))->toBeFalse();

    Gate::define('students.archive_any', fn ($user) => true);
    Gate::define('students.restore_any', fn ($user) => true);

    expect($this->policy->delete($this->owner, $this->student))->toBeTrue()
        ->and($this->policy->restore($this->owner, $this->student))->toBeTrue();
});

it('gates create behind students.create only', function (): void {
    Gate::define('students.create', fn ($user) => false);

    expect($this->policy->create($this->owner))->toBeFalse();

    Gate::define('students.create', fn ($user) => true);

    expect($this->policy->create($this->owner))->toBeTrue();
});
