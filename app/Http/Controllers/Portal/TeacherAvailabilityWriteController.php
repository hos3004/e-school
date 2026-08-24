<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\StoreOwnAvailabilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Staff\Application\Actions\RemoveTeacherAvailability;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

/**
 * إدارة المعلم لإتاحته من بوابته.
 *
 * الفرق الجوهري عن `POST /api/staff/availability`: ذلك المسار يقبل
 * `staff_profile_id` من المدخلات، فمن يملك صلاحية الإنشاء يستطيع الكتابة على
 * ملف معلم آخر. هنا **يُشتق الملف من الجلسة ولا يُقرأ من الطلب إطلاقًا**،
 * فيستحيل على معلم أن يعدّل إتاحة زميله.
 */
final class TeacherAvailabilityWriteController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SetTeacherAvailability $set,
        private readonly RemoveTeacherAvailability $remove,
    ) {}

    public function store(StoreOwnAvailabilityRequest $request): RedirectResponse
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
        );

        return back()->with('success', __('portal.availability.created'));
    }

    public function destroy(Request $request, string $availability): RedirectResponse
    {
        $profile = $this->ownProfile($request);

        /** @var TeacherAvailability|null $row */
        $row = TeacherAvailability::query()
            ->whereKey($availability)
            ->where('staff_profile_id', (string) $profile->getKey())
            ->first();

        abort_if($row === null, 404);

        $this->remove->execute($row);

        return back()->with('success', __('portal.availability.removed'));
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
