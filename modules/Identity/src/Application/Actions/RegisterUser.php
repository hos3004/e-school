<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

/**
 * تسجيل حساب مستخدم جديد داخل مؤسسة قائمة.
 *
 * الترتيب إلزامي: حراس ← DB::transaction ← نشر الأحداث بعد النجاح.
 */
final readonly class RegisterUser
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(array $attributes): User
    {
        $email = (string) $attributes['email'];

        $taken = User::query()->where('email', $email)->exists();
        if ($taken) {
            throw BusinessRuleViolation::make(
                'identity.email_taken',
                'identity::errors.email_taken',
                ['email' => $email],
            );
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($attributes): User {
            return User::query()->create([
                'organization_id' => (string) $attributes['organization_id'],
                'name' => (string) $attributes['name'],
                'email' => $email,
                'username' => $attributes['username'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'phone_country' => $attributes['phone_country'] ?? null,
                'password' => Hash::make((string) $attributes['password']),
                'locale' => (string) ($attributes['locale'] ?? config('app.fallback_locale')),
                'timezone' => (string) ($attributes['timezone'] ?? config('app.timezone')),
            ]);
        });

        Event::dispatch(new UserRegistered(
            userId: $user->id,
            organizationId: $user->organization_id,
            email: $user->email,
            locale: $user->locale,
        ));

        return $user;
    }
}
