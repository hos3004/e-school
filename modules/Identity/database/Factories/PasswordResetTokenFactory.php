<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\PasswordResetToken;

/**
 * @extends Factory<PasswordResetToken>
 */
final class PasswordResetTokenFactory extends Factory
{
    protected $model = PasswordResetToken::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Hash::make(Str::random(64)),
            'created_at' => now()->utc(),
        ];
    }

    /** رمز قارب على الانتهاء — داخل نافذة الصلاحية من config. */
    public function fresh(): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->utc(),
        ]);
    }

    /** رمز منتهٍ قبل نافذة الصلاحية المعرَّفة في config('auth.passwords.users.expire'). */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()
                ->utc()
                ->subMinutes((int) config('auth.passwords.users.expire', 60) + 5),
        ]);
    }
}
