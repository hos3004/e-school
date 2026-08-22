<?php

declare(strict_types=1);

namespace Modules\Guardians\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<GuardianProfile>
 */
final class GuardianProfileFactory extends Factory
{
    protected $model = GuardianProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
            'national_id_last4' => $this->faker->numerify('####'),
            'occupation' => $this->faker->randomElement(['engineer', 'teacher', 'merchant', 'physician']),
            'preferred_contact_channel' => $this->faker->randomElement(ContactChannel::cases()),
        ];
    }
}
