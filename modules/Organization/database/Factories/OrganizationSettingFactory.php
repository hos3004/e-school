<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Database\Factories\OrganizationFactory;
use Modules\Organization\Domain\Models\OrganizationSetting;

/**
 * @extends Factory<OrganizationSetting>
 */
final class OrganizationSettingFactory extends Factory
{
    protected $model = OrganizationSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new()->create()->id,
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->boolean(),
            'updated_by' => null,
        ];
    }
}
