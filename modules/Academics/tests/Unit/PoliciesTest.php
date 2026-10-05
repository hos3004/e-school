<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Application\Policies\CoursePolicy;
use Modules\Academics\Application\Policies\LevelPolicy;
use Modules\Academics\Application\Policies\ProgramPolicy;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;

function academicPolicyUser(string $organizationId): User
{
    $user = new User;
    $user->forceFill([
        'id' => (string) Str::ulid(),
        'organization_id' => $organizationId,
    ]);

    return $user;
}

it('grants program access only through the declared permission and tenant', function (): void {
    Gate::define('program.manage', static fn (): bool => true);
    $organizationId = (string) Str::ulid();
    $user = academicPolicyUser($organizationId);
    $program = new Program(['organization_id' => $organizationId]);
    $foreign = new Program(['organization_id' => (string) Str::ulid()]);
    $policy = new ProgramPolicy;

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $program))->toBeTrue()
        ->and($policy->update($user, $foreign))->toBeFalse()
        ->and($policy->delete($user, $foreign))->toBeFalse();
});

it('scopes level access through the parent program organization', function (): void {
    Gate::define('program.manage', static fn (): bool => true);
    $organizationId = (string) Str::ulid();
    $user = academicPolicyUser($organizationId);
    $level = new Level;
    $level->setRelation('program', new Program(['organization_id' => $organizationId]));
    $foreign = new Level;
    $foreign->setRelation('program', new Program(['organization_id' => (string) Str::ulid()]));
    $policy = new LevelPolicy;

    expect($policy->reorder($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->update($user, $level))->toBeTrue()
        ->and($policy->view($user, $foreign))->toBeFalse();
});

it('scopes course management to the actor organization', function (): void {
    Gate::define('course.manage', static fn (): bool => true);
    $organizationId = (string) Str::ulid();
    $user = academicPolicyUser($organizationId);
    $course = new Course(['organization_id' => $organizationId]);
    $foreign = new Course(['organization_id' => (string) Str::ulid()]);
    $policy = new CoursePolicy;

    expect($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $course))->toBeTrue()
        ->and($policy->update($user, $foreign))->toBeFalse()
        ->and($policy->delete($user, $foreign))->toBeFalse();
});
