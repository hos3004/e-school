<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Modules\Guardians\Application\Actions\VerifyGuardianLink;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class VerifyGuardianLinkController
{
    public function __construct(
        private readonly VerifyGuardianLink $action,
    ) {}

    public function __invoke(string $guardianLink): GuardianLinkResource
    {
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->with('guardian')->findOrFail($guardianLink);

        if (!auth()->user()?->can('verify', $link)) {
            abort(403);
        }

        return GuardianLinkResource::make($this->action->execute($guardianLink));
    }
}
