<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Application\Actions\AuthenticateWithCredentials;
use Modules\Identity\Application\Actions\RecordUserLogin;
use Modules\Identity\Application\Actions\RegisterDevice;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Http\Requests\MobileLoginRequest;
use Modules\Identity\Presentation\Http\Resources\UserResource;

/**
 * تسجيل دخول تطبيق الموبايل — يرجّع Sanctum personal access token.
 *
 * مسار منفصل تمامًا عن تسجيل دخول الويب (Fortify بجارد web بالجلسة)؛
 * يشترك معه فقط في منطق التحقق من بيانات الدخول عبر AuthenticateWithCredentials.
 */
final readonly class MobileLoginController
{
    public function __construct(
        private AuthenticateWithCredentials $authenticate,
        private RecordUserLogin $recordLogin,
        private RegisterDevice $registerDevice,
    ) {}

    public function __invoke(MobileLoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->authenticate->execute(
            (string) $request->validated('identifier'),
            (string) $request->validated('password'),
        );

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => [trans('auth.failed')],
            ]);
        }

        $this->recordLogin->execute($user, $request->ip(), $request->userAgent());

        $deviceName = (string) ($request->validated('device_name') ?? '');

        if ($request->filled('push_token') || $deviceName !== '') {
            $this->registerDevice->execute($user->id, [
                'device_name' => $request->validated('device_name'),
                'platform' => $request->validated('platform'),
                'push_token' => $request->validated('push_token'),
            ]);
        }

        $token = $user->createToken($deviceName !== '' ? $deviceName : 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ], 201);
    }
}
