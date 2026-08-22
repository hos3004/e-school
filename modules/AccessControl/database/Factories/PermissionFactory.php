<?php

declare(strict_types=1);

namespace Modules\AccessControl\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;

/**
 * @extends Factory<Permission>
 */
final class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = $this->faker->randomElement(['students', 'scheduling', 'payroll', 'content']);

        return [
            'name' => $module.'.'.$this->faker->unique()->slug(2, false),
            'guard_name' => GuardName::Web,
            'module' => $module,
            'description' => [
                'ar' => 'وصف تجريبي للصلاحية '.Str::limit($this->faker->sentence(4), 60, ''),
                'en' => Str::limit('Sample permission description: '.$this->faker->sentence(4), 60, ''),
            ],
        ];
    }

    public function api(): static
    {
        return $this->state(fn (): array => [
            'guard_name' => GuardName::Api,
        ]);
    }
}
