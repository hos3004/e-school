<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PersonLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Application\Actions\TerminateStaffProfile;
use Modules\Staff\Domain\Models\StaffProfile;

/**
 * إيقاف حساب المعلم من صفحة ملفه.
 *
 * الإيقاف يضبط تاريخ إنهاء الخدمة ويغلق العقود السارية بنهاية صريحة — لا حذف
 * للحساب ولا للعقود ولا للقيود المالية القائمة.
 *
 * التدقيق يُكتب هنا لأن حدث إنهاء الخدمة بلا مستمع في الموديول.
 */
final class TeacherLifecycleController extends Controller
{
    public function terminate(
        PersonLifecycleRequest $request,
        string $profile,
        TerminateStaffProfile $action,
        AuditRecorder $audit,
    ): RedirectResponse {
        $record = StaffProfile::query()
            ->forOrganization($request->organizationId())
            ->withTrashed()
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('terminate', $record);

        $terminated = $action->execute($record, $request->reason());

        $audit->record(
            organizationId: (string) $record->organization_id,
            actorId: $request->actorId(),
            actorType: 'user',
            action: 'staff.profile_terminated',
            auditableType: 'staff_profiles',
            auditableId: (string) $record->getKey(),
            oldValues: ['terminated_at' => null],
            newValues: ['terminated_at' => $terminated->terminated_at?->toIso8601String()],
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.lifecycle.terminated'));
    }
}
