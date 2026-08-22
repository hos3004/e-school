<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Domain\Enums\Weekday;
use Modules\Organization\Domain\Models\Organization;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameAr = 'مدرسة '.$this->faker->unique()->company();

        return [
            'name' => ['ar' => $nameAr, 'en' => $this->faker->unique()->company()],
            'slug' => str($this->faker->unique()->slug(2))->toString(),
            'logo_path' => null,
            'default_timezone' => 'Africa/Cairo',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'supported_locales' => ['ar', 'en'],
            'week_starts_on' => Weekday::Saturday->value,
            'settings' => null,
            'feature_overrides' => null,
        ];
    }
}
