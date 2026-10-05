<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Modules\Identity\Domain\Contracts\DTOs\UserPushDevice;

interface UserPushGateway
{
    /** @return list<UserPushDevice> */
    public function activeDevices(string $organizationId, string $userId): array;

    public function can(string $organizationId, string $userId, string $permission): bool;

    public function revokeUnregisteredDevice(string $organizationId, string $userId, UserPushDevice $device): void;
}
