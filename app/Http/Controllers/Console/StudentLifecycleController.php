<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PersonLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Application\Actions\RestoreStudentAction;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * إيقاف حساب الطالب واسترجاعه من صفحة ملفه.
 *
 * لا حذف نهائي مهما كان السبب: الإيقاف أرشفة تُبقي البيانات والسجل كما هما،
 * والاسترجاع يعيد الوصول دون مسّ التاريخ.
 *
 * التدقيق يُكتب هنا لأن حدثَي الأرشفة والاسترجاع بلا مستمعين في الموديول،
 * فلو اعتمدنا عليهما لضاع السبب والفاعل.
 */
final class StudentLifecycleController extends Controller
{
    public function archive(
        PersonLifecycleRequest $request,
        string $profile,
        ArchiveStudentAction $action,
        AuditRecorder $audit,
    ): RedirectResponse {
        $student = $this->record($request, $profile);
        Gate::authorize('delete', $student);

        $action->execute($student, $request->reason());
        $this->audit($audit, $request, $student, 'students.archived', false, true);

        return back()->with('success', __('console_people.lifecycle.archived'));
    }

    public function restore(
        PersonLifecycleRequest $request,
        string $profile,
        RestoreStudentAction $action,
        AuditRecorder $audit,
    ): RedirectResponse {
        $student = $this->record($request, $profile);
        Gate::authorize('restore', $student);

        $action->execute((string) $student->getKey());
        $this->audit($audit, $request, $student, 'students.restored', true, false);

        return back()->with('success', __('console_people.lifecycle.restored'));
    }

    private function audit(
        AuditRecorder $audit,
        PersonLifecycleRequest $request,
        StudentProfile $student,
        string $action,
        bool $before,
        bool $after,
    ): void {
        $audit->record(
            organizationId: (string) $student->organization_id,
            actorId: $request->actorId(),
            actorType: 'user',
            action: $action,
            auditableType: 'student_profiles',
            auditableId: (string) $student->getKey(),
            oldValues: ['archived' => $before],
            newValues: ['archived' => $after],
            reason: $request->reason(),
        );
    }

    private function record(PersonLifecycleRequest $request, string $profile): StudentProfile
    {
        return StudentProfile::query()
            ->forOrganization($request->organizationId())
            ->withTrashed()
            ->whereKey($profile)
            ->firstOrFail();
    }
}
