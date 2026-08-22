<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Role;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => $this->faker->unique()->slug(2, false),
            'guard_name' => GuardName::Web,
            'is_system' => false,
        ];
    }

    public function forOrganization(string $organizationId): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organizationId,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
            'name' => $this->faker->unique()->randomElement(['super-admin', 'school-admin', 'teacher', 'student']),
        ]);
    }
}
