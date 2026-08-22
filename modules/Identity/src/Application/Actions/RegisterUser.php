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
        $email = isset($attributes['email']) && trim((string) $attributes['email']) !== ''
            ? strtolower(trim((string) $attributes['email']))
            : null;
        $phone = isset($attributes['phone']) && trim((string) $attributes['phone']) !== ''
            ? trim((string) $attributes['phone'])
            : null;
        $username = strtolower(trim((string) ($attributes['username'] ?? '')));

        if ($username === '') {
            throw BusinessRuleViolation::make(
                'identity.username_required',
                'identity::validation.username_required',
            );
        }

        if ($email === null && $phone === null) {
            throw BusinessRuleViolation::make(
                'identity.contact_required',
                'identity::validation.contact_required',
            );
        }

        if (in_array($username, (array) config('admission.username.reserved', []), true)) {
            throw BusinessRuleViolation::make(
                'identity.username_reserved',
                'identity::validation.username_reserved',
                ['username' => $username],
            );
        }

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            throw BusinessRuleViolation::make(
                'identity.email_taken',
                'identity::errors.email_taken',
                ['email' => $email],
            );
        }

        if (User::query()->where('username', $username)->exists()) {
            throw BusinessRuleViolation::make(
                'identity.username_taken',
                'identity::errors.username_taken',
                ['username' => $username],
            );
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($attributes, $email, $phone, $username): User {
            return User::query()->create([
                'organization_id' => (string) $attributes['organization_id'],
                'name' => (string) $attributes['name'],
                'email' => $email,
                'username' => $username,
                'phone' => $phone,
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
            username: $user->username,
            phone: $user->phone,
            locale: $user->locale,
        ));

        return $user;
    }
}
