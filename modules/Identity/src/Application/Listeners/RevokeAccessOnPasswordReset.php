<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Listeners;

use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;

/**
 * عند اكتمال إعادة تعيين كلمة المرور، تُبطَل كل رموز الموبايل (Sanctum)
 * والأجهزة النشطة للمستخدم، فلا يبقى وصول قديم أو مسروق بعد الاستعادة.
 *
 * كان الحدث يُبثّ دون مستمع فعلي رغم ما توثّقه التعليقات؛ هذا المستمع
 * يُغلق تلك الفجوة. idempotent: إعادة تشغيله لا تُحدث ضررًا.
 */
final class RevokeAccessOnPasswordReset
{
    public function handle(PasswordResetCompleted $event): void
    {
        $user = User::query()->find($event->userId);

        if (!$user instanceof User) {
            return;
        }

        // رموز Sanctum للموبايل — المورف يُحلّ تلقائيًا عبر العلاقة.
        $user->tokens()->delete();

        // الأجهزة النشطة: توقيف الوصول وتصفية رمز الدفع (موازاة لمسار الهاتف).
        UserDevice::query()
            ->forUser($user->id)
            ->active()
            ->update([
                'revoked_at' => now()->utc(),
                'push_token' => null,
            ]);
    }
}
