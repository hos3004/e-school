<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'username' => $this->faker->unique()->userName(),
            'phone' => $this->faker->optional()->e164PhoneNumber(),
            'phone_country' => $this->faker->optional()->randomElement(['EG', 'SA', 'FR']),
            'password' => self::$password ??= Hash::make('password'),
            'locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'status' => UserStatus::Active,
        ];
    }

    /** مستخدم داخل مؤسسة محددة (المعرّف يمرَّ من الاختبار/الـ Seeder). */
    public function inOrganization(string $organizationId): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $organizationId,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Frozen,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}
