<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Identity\Domain\Contracts\DTOs\UserPushDevice;
use Modules\Identity\Domain\Contracts\UserPushGateway;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;

final readonly class UserPushService implements UserPushGateway
{
    public function activeDevices(string $organizationId, string $userId): array
    {
        if (!$this->userExists($organizationId, $userId)) {
            return [];
        }

        return UserDevice::query()->forUser($userId)->active()->whereNotNull('push_token')
            ->get()->map(static fn (UserDevice $device): UserPushDevice => new UserPushDevice(
                (string) $device->getKey(),
                (string) $device->push_token,
            ))->values()->all();
    }

    public function can(string $organizationId, string $userId, string $permission): bool
    {
        return User::query()->where('organization_id', $organizationId)->find($userId)?->can($permission) === true;
    }

    public function revokeUnregisteredDevice(string $organizationId, string $userId, UserPushDevice $device): void
    {
        if (!$this->userExists($organizationId, $userId)) {
            return;
        }

        // A delayed FCM response must not revoke a token refreshed in the meantime.
        UserDevice::query()->forUser($userId)->active()->whereKey($device->id)
            ->where('push_token', $device->token)
            ->update(['revoked_at' => now(), 'push_token' => null]);
    }

    private function userExists(string $organizationId, string $userId): bool
    {
        return User::query()->where('organization_id', $organizationId)->whereKey($userId)->exists();
    }
}
