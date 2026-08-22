<?php

declare(strict_types=1);

namespace Modules\Guardians\Database\Factories;

use Shared\Testing\Fixtures;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * @extends Factory<GuardianLink>
 */
final class GuardianLinkFactory extends Factory
{
    protected $model = GuardianLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_profile_id' => GuardianProfile::factory(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'relationship' => $this->faker->randomElement(GuardianRelationship::cases()),
            'is_primary' => false,
            'can_act_for' => false,
            'visible_sections' => (array) config('guardians.links.default_visible_sections', []),
            'verified_at' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }

    public function acting(): static
    {
        return $this->state(fn (): array => ['can_act_for' => true]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => CarbonImmutable::now('UTC')]);
    }
}
