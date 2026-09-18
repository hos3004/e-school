<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PersonLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Guardians\Application\Actions\ArchiveGuardianProfile;
use Modules\Guardians\Application\Actions\RestoreGuardianProfile;
use Modules\Guardians\Domain\Models\GuardianProfile;

/**
 * إيقاف حساب ولي الأمر واسترجاعه من صفحة ملفه.
 *
 * لا حذف نهائي: الإيقاف أرشفة تُسقط صلاحيات وساطته على روابطه (عبر مستمع
 * DeactivateLinksWhenGuardianArchived) وتُبقي البيانات والسجل كما هما،
 * والاسترجاع يعيد الوصول للحساب دون إعادة تفعيل تلك الصلاحيات تلقائيًا.
 */
final class GuardianLifecycleController extends Controller
{
    public function archive(
        PersonLifecycleRequest $request,
        string $profile,
        ArchiveGuardianProfile $action,
        AuditRecorder $audit,
    ): RedirectResponse {
        $guardian = $this->record($request, $profile);
        Gate::authorize('delete', $guardian);

        $action->execute((string) $guardian->id, $request->reason());
        $this->audit($audit, $request, $guardian, 'guardians.archived', false, true);

        return back()->with('success', __('console_people.lifecycle.archived_guardian'));
    }

    public function restore(
        PersonLifecycleRequest $request,
        string $profile,
        RestoreGuardianProfile $action,
        AuditRecorder $audit,
    ): RedirectResponse {
        $guardian = $this->record($request, $profile);
        Gate::authorize('restore', $guardian);

        $action->execute((string) $guardian->getKey());
        $this->audit($audit, $request, $guardian, 'guardians.restored', true, false);

        return back()->with('success', __('console_people.lifecycle.restored_guardian'));
    }

    private function audit(
        AuditRecorder $audit,
        PersonLifecycleRequest $request,
        GuardianProfile $guardian,
        string $action,
        bool $before,
        bool $after,
    ): void {
        $audit->record(
            organizationId: (string) $guardian->organization_id,
            actorId: $request->actorId(),
            actorType: 'user',
            action: $action,
            auditableType: 'guardian_profiles',
            auditableId: (string) $guardian->getKey(),
            oldValues: ['archived' => $before],
            newValues: ['archived' => $after],
            reason: $request->reason(),
        );
    }

    private function record(PersonLifecycleRequest $request, string $profile): GuardianProfile
    {
        return GuardianProfile::query()
            ->forOrganization($request->organizationId())
            ->withTrashed()
            ->whereKey($profile)
            ->firstOrFail();
    }
}
