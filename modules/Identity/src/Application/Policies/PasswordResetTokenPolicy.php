<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Policies;

use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;

/**
 * سياسة رموز إعادة التعيين — ممنوع عرضها أو إدارتها عبر أي واجهة.
 *
 * الرموز سرية بطبيعتها؛ تُنشأ وتُستهلك وتُحذف داخل الإجراءات فقط.
 * كل القدرات مرفوضة صراحةً لمنع أي تسريب عبر Filament أو API.
 */
final class PasswordResetTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, PasswordResetToken $token): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PasswordResetToken $token): bool
    {
        return false;
    }

    public function delete(User $user, PasswordResetToken $token): bool
    {
        return false;
    }
}
