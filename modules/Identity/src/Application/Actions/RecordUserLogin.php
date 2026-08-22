<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Events\UserLoggedIn;
use Modules\Identity\Domain\Models\User;

/**
 * توثيق دخول ناجح — يحدّث آخر دخول وIP ويبث الحدث.
 *
 * يُستدعى بعد نجاح المصادقة فقط؛ لا حراس عمل هنا سوى حالة الحساب.
 */
final readonly class RecordUserLogin
{
    public function execute(User $user, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if (! $user->canLogIn()) {
            return;
        }

        DB::transaction(function () use ($user, $ipAddress): void {
            $user->last_login_at = now()->utc();
            $user->last_login_ip = $ipAddress;
            $user->save();
        });

        Event::dispatch(new UserLoggedIn(
            userId: $user->id,
            organizationId: $user->organization_id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        ));
    }
}
