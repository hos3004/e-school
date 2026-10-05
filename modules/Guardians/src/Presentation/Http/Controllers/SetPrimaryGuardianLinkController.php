<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Guardians\Application\Actions\SetPrimaryGuardianLink;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class SetPrimaryGuardianLinkController
{
    public function __construct(
        private readonly SetPrimaryGuardianLink $action,
    ) {}

    public function __invoke(Request $request, string $guardianLink): GuardianLinkResource
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        /** @var GuardianLink $link */
        $link = GuardianLink::query()->with('guardian')->findOrFail($guardianLink);

        if (!auth()->user()?->can('setPrimary', $link)) {
            abort(403);
        }

        return GuardianLinkResource::make($this->action->execute(
            $guardianLink,
            (string) $request->user()->getAuthIdentifier(),
            (string) $validated['reason'],
        ));
    }
}
