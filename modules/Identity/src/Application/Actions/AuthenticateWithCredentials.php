<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\User;

/**
 * يحل المستخدم من معرّف موحّد (اسم مستخدم أو بريد أو هاتف) ويتحقق من كلمة المرور.
 *
 * منطق واحد يشتركه تسجيل دخول الويب (Fortify) وتسجيل دخول الموبايل (Sanctum)
 * لمنع تباعد قواعد المصادقة بين المسارين.
 */
final readonly class AuthenticateWithCredentials
{
    public function execute(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return null;
        }

        $user = $this->findByIdentifier($identifier);

        if ($user === null || !$user->canLogIn()) {
            return null;
        }

        /** @var string $hash */
        $hash = (string) $user->getAuthPassword();

        if (!Hash::check($password, $hash)) {
            return null;
        }

        return $user;
    }

    private function findByIdentifier(string $identifier): ?User
    {
        $isPhoneNumber = preg_match('/^\+?[0-9]{7,15}$/', $identifier) === 1;

        /** @var User|null */
        return User::query()
            ->where(static function ($query) use ($identifier, $isPhoneNumber): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);

                if ($isPhoneNumber) {
                    $query->orWhere(function ($phoneQuery) use ($identifier): void {
                        $digits = ltrim($identifier, '+');

                        $phoneQuery->where(function ($inner) use ($identifier, $digits): void {
                            $inner->where('phone', $identifier)
                                ->orWhere('phone', '+'.$digits);
                        });
                    });
                }
            })
            ->first();
    }
}
