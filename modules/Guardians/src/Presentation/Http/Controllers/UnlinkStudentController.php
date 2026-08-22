<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Guardians\Application\Actions\UnlinkStudentFromGuardian;
use Modules\Guardians\Domain\Models\GuardianLink;

final class UnlinkStudentController
{
    public function __construct(
        private readonly UnlinkStudentFromGuardian $action,
    ) {}

    public function __invoke(Request $request, string $guardianLink): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => __('guardians::validation.reason_required'),
            'reason.min' => __('guardians::validation.reason_min'),
        ]);

        /** @var GuardianLink|null $link */
        $link = GuardianLink::query()->find($guardianLink);

        if ($link !== null) {
            if (! $request->user()->can('delete', $link)) {
                abort(403);
            }

            $this->action->execute($guardianLink, (string) $request->string('reason'));
        }

        return response()->json(status: 204);
    }
}
