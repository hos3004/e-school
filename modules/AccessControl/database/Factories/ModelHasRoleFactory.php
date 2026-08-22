<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;

/**
 * @extends Factory<ModelHasRole>
 */
final class ModelHasRoleFactory extends Factory
{
    protected $model = ModelHasRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'model_type' => $this->faker->randomElement(['users', 'staff_profiles', 'student_profiles']),
            'model_id' => (string) Str::ulid(),
        ];
    }
}
