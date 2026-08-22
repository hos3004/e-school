<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;

/**
 * @extends Factory<UserDevice>
 */
final class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_name' => $this->faker->randomElement(['iPhone 15', 'Galaxy S24', 'Pixel 8', 'iPad Air']),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
            'push_token' => Str::random(64),
            'last_used_at' => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }

    /** جهاز داخل حساب موجود بدل إنشاء مستخدم جديد. */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now()->utc(),
            'push_token' => null,
        ]);
    }

    public function withoutPush(): static
    {
        return $this->state(fn (): array => [
            'push_token' => null,
        ]);
    }
}
