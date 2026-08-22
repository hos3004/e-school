<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Domain\Models\UserDevice;

/**
 * @property UserDevice $resource
 */
final class UserDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserDevice $device */
        $device = $this->resource;

        return [
            'id' => $device->id,
            'user_id' => $device->user_id,
            'device_name' => $device->device_name,
            'platform' => $device->platform,
            'revoked' => $device->isRevoked(),
            'can_receive_push' => $device->canReceivePush(),
            'last_used_at' => $device->last_used_at?->toIso8601String(),
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }
}
