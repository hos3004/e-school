<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\StoreOwnAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Staff\Application\Actions\RemoveTeacherAvailability;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

/**
 * أوقات توفّر المعلم للموبايل — مرآة Portal\TeacherAvailabilityController
 * وPortal\TeacherAvailabilityWriteController بالضبط: نفس StoreOwnAvailabilityRequest
 * ونفس SetTeacherAvailability/RemoveTeacherAvailability. الملف يُشتق من الجلسة
 * فقط، فيستحيل على معلم تعديل إتاحة زميله (نفس تعليق النسخة الويب).
 */
final class TeacherAvailabilityController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SetTeacherAvailability $set,
        private readonly RemoveTeacherAvailability $remove,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        return response()->json([
            'availability' => $staffProfileId === null ? [] : $this->data->teacherAvailability($staffProfileId),
            'has_profile' => $staffProfileId !== null,
            'approval_required' => (bool) config('scheduling.availability.teacher_requires_approval'),
        ]);
    }

    public function store(StoreOwnAvailabilityRequest $request): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $validated = $request->validated();

        $this->set->execute(
            profile: $profile,
            weekday: (int) $validated['weekday'],
            startTime: (string) $validated['start_time'],
            endTime: (string) $validated['end_time'],
            timezone: (string) $validated['timezone'],
            effectiveFrom: (string) $validated['effective_from'],
            effectiveTo: $validated['effective_to'] ?? null,
            actorId: (string) $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'availability' => $this->data->teacherAvailability((string) $profile->getKey()),
        ], 201);
    }

    public function destroy(Request $request, string $availability): JsonResponse
    {
        $profile = $this->ownProfile($request);

        /** @var TeacherAvailability|null $row */
        $row = TeacherAvailability::query()
            ->whereKey($availability)
            ->where('staff_profile_id', (string) $profile->getKey())
            ->first();

        abort_if($row === null, 404);

        $this->remove->execute(
            availability: $row,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: is_string($request->input('reason')) ? trim((string) $request->input('reason')) : null,
        );

        return response()->json([
            'availability' => $this->data->teacherAvailability((string) $profile->getKey()),
        ]);
    }

    private function ownProfile(Request $request): StaffProfile
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');

        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        abort_if($staffProfileId === null, 403);

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()
            ->whereKey($staffProfileId)
            ->where('organization_id', $organizationId)
            ->first();

        abort_if($profile === null, 403);

        return $profile;
    }
}
