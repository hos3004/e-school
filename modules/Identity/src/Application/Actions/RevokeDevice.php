<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Events\DeviceRevoked;
use Modules\Identity\Domain\Models\UserDevice;
use Shared\Support\BusinessRuleViolation;

/**
 * سحب جهاز — إبطال رمز الإشعارات ومنع وصوله.
 */
final readonly class RevokeDevice
{
    public function execute(UserDevice $device): UserDevice
    {
        if ($device->isRevoked()) {
            throw BusinessRuleViolation::make(
                'identity.device_already_revoked',
                'identity::errors.device_already_revoked',
            );
        }

        /** @var UserDevice $device */
        $device = DB::transaction(function () use ($device): UserDevice {
            $device->revoked_at = now()->utc();
            $device->push_token = null;
            $device->save();

            return $device;
        });

        Event::dispatch(new DeviceRevoked(
            deviceId: $device->id,
            userId: $device->user_id,
        ));

        return $device;
    }
}
