<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Events\PasswordResetRequested;
use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;

/**
 * طلب إعادة تعيين كلمة المرور — يُنشئ رمزًا ويبث حدث الإرسال.
 *
 * الاستجابة متساوية دائمًا سواء وُجد البريد أم لا (منع حصر الحسابات)،
 * والرمز لا يُرجَع للمتصل أبدًا — يستهلكه مستمع الإشعارات فقط.
 */
final readonly class IssuePasswordResetToken
{
    public function execute(string $email): void
    {
        /** @var array{user_id: string, token: string}|null $issued */
        $issued = DB::transaction(function () use ($email): ?array {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                return null;
            }

            $rawToken = Str::random(64);

            PasswordResetToken::query()->updateOrCreate(
                ['email' => $email],
                [
                    'token' => Hash::make($rawToken),
                    'created_at' => now()->utc(),
                ],
            );

            return ['user_id' => $user->id, 'token' => $rawToken];
        });

        if ($issued !== null) {
            Event::dispatch(new PasswordResetRequested(
                email: $email,
                userId: $issued['user_id'],
                token: $issued['token'],
            ));
        }
    }
}
