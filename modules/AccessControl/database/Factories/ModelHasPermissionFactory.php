<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;

/**
 * @extends Factory<ModelHasPermission>
 */
final class ModelHasPermissionFactory extends Factory
{
    protected $model = ModelHasPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permission_id' => Permission::factory(),
            'model_type' => $this->faker->randomElement(['users', 'staff_profiles', 'student_profiles']),
            'model_id' => (string) Str::ulid(),
        ];
    }
}
