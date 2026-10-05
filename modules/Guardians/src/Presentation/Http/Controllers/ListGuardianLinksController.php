<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Http\Resources\GuardianLinkResource;

final class ListGuardianLinksController
{
    public function __invoke(Request $request): JsonResponse
    {
        $organizationId = data_get($request->user(), 'organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        $query = GuardianLink::query()
            ->with('guardian')
            ->whereHas('guardian', fn (Builder $guardianQuery): Builder => $guardianQuery->where('organization_id', $organizationId));

        if ($request->filled('guardian_profile_id')) {
            $query->forGuardian((string) $request->string('guardian_profile_id'));
        }

        if ($request->filled('student_profile_id')) {
            $query->forStudent((string) $request->string('student_profile_id'));
        }

        if (!$request->user()?->can('guardian.view')) {
            /** @var GuardianProfile|null $own */
            $own = GuardianProfile::query()
                ->forOrganization($organizationId)
                ->where('user_id', (string) $request->user()?->id)
                ->first();

            $query->forGuardian($own->id ?? 'none');
        }

        return GuardianLinkResource::collection(
            $query->orderBy('created_at')->paginate(min(max((int) $request->integer('per_page', 25), 1), 100)),
        )->response();
    }
}
