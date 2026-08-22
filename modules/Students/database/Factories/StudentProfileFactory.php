<?php

declare(strict_types=1);

namespace Modules\Students\Database\Factories;

use Shared\Testing\Fixtures;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * @extends Factory<StudentProfile>
 */
final class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => (string) $this(string) Str::ulid(),
            'user_id' => Fixtures::userId(),
            'student_code' => strtoupper($this->faker->bothify('STU-####-####')),
            'date_of_birth' => $this->faker->dateTimeBetween('-25 years', '-10 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement([StudentGender::Male, StudentGender::Female]),
            'nationality' => $this->faker->countryCode(),
            'country' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'preferred_language' => $this->faker->randomElement(['ar', 'en', 'fr']),
            'joined_at' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}
