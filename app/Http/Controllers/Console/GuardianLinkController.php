<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\GuardianLinkStoreRequest;
use App\Http\Requests\Console\PersonLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Guardians\Application\Actions\LinkStudentToGuardian;
use Modules\Guardians\Application\Actions\UnlinkStudentFromGuardian;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * ربط طالب بحساب ولي أمر قائم وفكّه، من صفحة ملف الوصي.
 *
 * التسكين الأولي عند إنشاء الحساب يمر بـCreateGuardianOnboardingAction؛ هذا
 * المتحكم يخص إضافة/إزالة أبناء آخرين لحساب موجود بالفعل.
 */
final class GuardianLinkController extends Controller
{
    public function store(
        GuardianLinkStoreRequest $request,
        string $profile,
        LinkStudentToGuardian $action,
    ): RedirectResponse {
        $guardian = GuardianProfile::query()
            ->where('organization_id', $request->organizationId())
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('linkStudents', $guardian);

        $data = $request->validated();

        try {
            $action->execute(
                guardianProfileId: (string) $guardian->id,
                studentProfileId: (string) $data['student_profile_id'],
                data: [
                    'relationship' => GuardianRelationship::from((string) $data['relationship']),
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                    'can_act_for' => (bool) ($data['can_act_for'] ?? false),
                    'visible_sections' => $data['visible_sections'] ?? [],
                ],
                actorId: $request->actorId(),
                reason: $request->reason(),
            );
        } catch (BusinessRuleViolation $error) {
            return back()->withErrors(['student_profile_id' => $error->getMessage()])->withInput();
        }

        return back()->with('success', __('console_people.guardians.linked'));
    }

    public function destroy(
        PersonLifecycleRequest $request,
        string $profile,
        string $link,
        UnlinkStudentFromGuardian $action,
    ): RedirectResponse {
        $guardian = GuardianProfile::query()
            ->where('organization_id', $request->organizationId())
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('linkStudents', $guardian);

        $record = GuardianLink::query()
            ->where('guardian_profile_id', $guardian->id)
            ->whereKey($link)
            ->firstOrFail();

        $action->execute((string) $record->id, $request->reason(), $request->actorId());

        return back()->with('success', __('console_people.guardians.unlinked'));
    }
}
