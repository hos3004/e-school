<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Staff\Application\Actions\CreateStaffProfile;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Presentation\Http\Requests\StoreStaffProfileRequest;
use Modules\Staff\Presentation\Http\Resources\StaffProfileResource;
use Symfony\Component\HttpFoundation\Response;

final class StoreStaffProfileController
{
    public function __invoke(StoreStaffProfileRequest $request, CreateStaffProfile $action): JsonResponse
    {
        $validated = $request->validated();

        $profile = $action->execute(
            organizationId: $validated['organization_id'],
            userId: $validated['user_id'],
            staffCode: $validated['staff_code'],
            employmentType: EmploymentType::from($validated['employment_type']),
            gender: StaffGender::from($validated['gender']),
            countryId: $validated['country_id'],
            regionId: $validated['region_id'],
            dateOfBirth: $validated['date_of_birth'] ?? null,
            phone: $validated['phone'] ?? null,
            hiredAt: $validated['hired_at'] ?? null,
            bio: $validated['bio'] ?? null,
            specializations: $validated['specializations'] ?? null,
        );

        return new StaffProfileResource($profile)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
