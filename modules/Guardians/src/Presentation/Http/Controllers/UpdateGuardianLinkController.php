<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Modules\Guardians\Application\Actions\UpdateGuardianLink;
use Modules\Guardians\Presentation\Http\Requests\UpdateGuardianLinkRequest;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class UpdateGuardianLinkController
{
    public function __construct(
        private readonly UpdateGuardianLink $action,
    ) {}

    public function __invoke(UpdateGuardianLinkRequest $request, string $guardianLink): GuardianLinkResource
    {
        $link = $this->action->execute($guardianLink, $request->validated());

        return GuardianLinkResource::make($link);
    }
}
