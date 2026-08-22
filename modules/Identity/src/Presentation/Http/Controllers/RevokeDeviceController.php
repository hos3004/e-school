<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Application\Actions\RevokeDevice;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Presentation\Http\Requests\RevokeDeviceRequest;
use Modules\Identity\Presentation\Http\Resources\UserDeviceResource;

final readonly class RevokeDeviceController
{
    public function __construct(private RevokeDevice $action) {}

    public function __invoke(RevokeDeviceRequest $request, UserDevice $device): JsonResponse
    {
        $revoked = $this->action->execute($device);

        return UserDeviceResource::make($revoked)->response();
    }
}
