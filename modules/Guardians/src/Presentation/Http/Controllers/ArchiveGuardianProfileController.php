<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Guardians\Application\Actions\ArchiveGuardianProfile;
use Modules\Guardians\Domain\Models\GuardianProfile;

final class ArchiveGuardianProfileController
{
    public function __construct(
        private readonly ArchiveGuardianProfile $action,
    ) {}

    public function __invoke(Request $request, string $guardianProfile): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => __('guardians::validation.reason_required'),
            'reason.min' => __('guardians::validation.reason_min'),
        ]);

        /** @var GuardianProfile|null $profile */
        $profile = GuardianProfile::query()->find($guardianProfile);

        if ($profile !== null) {
            if (!$request->user()->can('delete', $profile)) {
                abort(403);
            }

            $this->action->execute($guardianProfile, (string) $request->string('reason'));
        }

        return response()->json(status: 204);
    }
}
