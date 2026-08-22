<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Policies;

use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;

/**
 * سياسة الأجهزة — المالك يدير أجهزته، ومن يملك صلاحية العرض يشاهدها.
 */
final class UserDevicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('identity.devices.view_any');
    }

    public function view(User $actor, UserDevice $device): bool
    {
        return $actor->id === $device->user_id || $actor->can('identity.devices.view');
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, UserDevice $device): bool
    {
        return $this->revoke($actor, $device);
    }

    public function delete(User $actor, UserDevice $device): bool
    {
        return $this->revoke($actor, $device);
    }

    /** فعل خاص: سحب الجهاز — للمالك أو لمن يملك الصلاحية. */
    public function revoke(User $actor, UserDevice $device): bool
    {
        return $actor->id === $device->user_id || $actor->can('identity.devices.revoke');
    }
}
