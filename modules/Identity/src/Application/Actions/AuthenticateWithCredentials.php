<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        if ($user === null) {
            $this->logRejection($identifier, 'identifier_not_resolved');

            return null;
        }

        if (!$user->canLogIn()) {
            $this->logRejection($identifier, 'account_cannot_log_in', $user->id);

            return null;
        }

        /** @var string $hash */
        $hash = (string) $user->getAuthPassword();

        if (!Hash::check($password, $hash)) {
            $this->logRejection($identifier, 'wrong_password', $user->id);

            return null;
        }

        return $user;
    }

    /**
     * سجل رفض دخول بدون كلمة المرور نفسها — يساعد في تتبع حالات فشل الدخول
     * المتكررة (مثل معرّف يحل لحسابين، أو حساب موقوف) دون كشف بيانات حساسة.
     */
    private function logRejection(string $identifier, string $reason, ?string $userId = null): void
    {
        Log::channel('single')->info('identity.login_rejected', [
            'identifier' => $identifier,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    /**
     * اسم المستخدم والبريد فريدان دائمًا (فهرس فريد على الحسابات غير المحذوفة)،
     * فحلّهما بأول تطابق آمن. رقم الهاتف بلا قيد تفرّد — إخوة أو الطالب وولي
     * أمره كثيرًا ما يشتركون في نفس الرقم — فتسجيل الدخول به لا يُقبل إلا لو
     * طابق حسابًا واحدًا فقط؛ التطابق الغامض يُرفض بدل اختيار عشوائي.
     */
    private function findByIdentifier(string $identifier): ?User
    {
        $user = User::query()
            ->where(static function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if ($user !== null) {
            return $user;
        }

        $isPhoneNumber = preg_match('/^\+?[0-9]{7,15}$/', $identifier) === 1;

        if (!$isPhoneNumber) {
            return null;
        }

        $user = $this->findByUniquePhone($identifier);

        if ($user === null && $this->phoneMatchesMultipleAccounts($identifier)) {
            $this->logRejection($identifier, 'ambiguous_phone_number');
        }

        return $user;
    }

    private function findByUniquePhone(string $identifier): ?User
    {
        $matches = $this->phoneMatches($identifier)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function phoneMatchesMultipleAccounts(string $identifier): bool
    {
        return $this->phoneMatches($identifier)->count() > 1;
    }

    /**
     * @return Builder<User>
     */
    private function phoneMatches(string $identifier): Builder
    {
        $digits = ltrim($identifier, '+');

        return User::query()
            ->where(static function ($query) use ($identifier, $digits): void {
                $query->where('phone', $identifier)
                    ->orWhere('phone', '+'.$digits);
            });
    }
}
