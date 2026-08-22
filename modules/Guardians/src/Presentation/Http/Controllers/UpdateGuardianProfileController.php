<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Modules\Guardians\Application\Actions\UpdateGuardianProfile;
use Modules\Guardians\Presentation\Http\Requests\UpdateGuardianProfileRequest;
use Modules\Guardians\Presentation\Http\Resources\GuardianProfileResource;

final class UpdateGuardianProfileController
{
    public function __construct(
        private readonly UpdateGuardianProfile $action,
    ) {}

    public function __invoke(UpdateGuardianProfileRequest $request, string $guardianProfile): GuardianProfileResource
    {
        $profile = $this->action->execute($guardianProfile, $request->validated());

        return GuardianProfileResource::make($profile);
    }
}
