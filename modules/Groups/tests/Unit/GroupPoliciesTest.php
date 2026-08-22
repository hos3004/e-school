<?php

declare(strict_types=1);

use Modules\Groups\Application\Policies\GroupMembershipPolicy;
use Modules\Groups\Application\Policies\GroupPolicy;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;

it('never inspects role names and decides via declared abilities', function (): void {
    $group = Group::factory()->make();
    $membership = GroupMembership::factory()->make(['group_id' => $group]);

    $allowedUser = new class
    {
        public function can(string $ability): bool
        {
            return str_starts_with($ability, 'groups.');
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }
    };

    $deniedUser = new class
    {
        public function can(string $ability): bool
        {
            return false;
        }

        public function getAuthIdentifier(): int
        {
            return 2;
        }
    };

    $policy = new GroupPolicy;
    $membershipPolicy = new GroupMembershipPolicy;

    expect($policy->viewAny($allowedUser))->toBeTrue()
        ->and($policy->view($allowedUser, $group))->toBeTrue()
        ->and($policy->create($allowedUser))->toBeTrue()
        ->and($policy->update($allowedUser, $group))->toBeTrue()
        ->and($policy->delete($allowedUser, $group))->toBeTrue()
        ->and($policy->enrollStudent($allowedUser, $group))->toBeTrue()

        ->and($policy->viewAny($deniedUser))->toBeFalse()
        ->and($policy->view($deniedUser, $group))->toBeFalse()
        ->and($policy->create($deniedUser))->toBeFalse()
        ->and($policy->update($deniedUser, $group))->toBeFalse()
        ->and($policy->delete($deniedUser, $group))->toBeFalse()

        ->and($membershipPolicy->viewAny($allowedUser))->toBeTrue()
        ->and($membershipPolicy->create($allowedUser))->toBeTrue()
        ->and($membershipPolicy->create($deniedUser))->toBeFalse();
});
