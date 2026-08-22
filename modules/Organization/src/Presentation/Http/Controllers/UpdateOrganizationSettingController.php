<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Organization\Application\Actions\UpsertOrganizationSetting;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Presentation\Http\Requests\UpdateOrganizationSettingRequest;
use Modules\Organization\Presentation\Http\Resources\OrganizationSettingResource;

final class UpdateOrganizationSettingController
{
    public function __construct(
        private readonly UpsertOrganizationSetting $upsertSetting,
    ) {}

    public function __invoke(UpdateOrganizationSettingRequest $request, Organization $organization): JsonResponse
    {
        $setting = $this->upsertSetting->execute(
            $organization,
            (string) $request->string('key'),
            $request->input('value'),
        );

        return OrganizationSettingResource::make($setting)->response();
    }
}
