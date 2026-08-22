<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class ListGuardianLinksController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = GuardianLink::query()->with('guardian');

        if ($request->filled('guardian_profile_id')) {
            $query->forGuardian((string) $request->string('guardian_profile_id'));
        }

        if ($request->filled('student_profile_id')) {
            $query->forStudent((string) $request->string('student_profile_id'));
        }

        if (!$request->user()?->can('guardians.view_any')) {
            /** @var GuardianProfile|null $own */
            $own = GuardianProfile::query()->where('user_id', (string) $request->user()?->id)->first();

            $query->forGuardian($own->id ?? 'none');
        }

        return GuardianLinkResource::collection(
            $query->orderBy('created_at')->paginate(min(max((int) $request->integer('per_page', 25), 1), 100)),
        )->response();
    }
}
