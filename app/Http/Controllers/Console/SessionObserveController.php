<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Actions\EnterClassroom;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * بوابة دخول الأدمن (بصفة رقابية) لأي حصة في مؤسسته.
 *
 * منفصلة تمامًا عن ClassroomJoinController (بوابة المعلم/الطالب): هنا لا
 * تحقّق من ملكية الحصة، بل من صلاحية classroom.observe فقط، والدور دائمًا
 * Viewer. كل دخول يُسجَّل في سجل التدقيق — هذا المسار لا يُستثنى من المراجعة
 * لمجرد كونه إداريًا.
 */
final class SessionObserveController extends Controller
{
    public function __construct(
        private readonly EnterClassroom $enterClassroom,
        private readonly AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $row = DB::table('sessions')
            ->where('sessions.id', $session)
            ->where('sessions.organization_id', $organizationId)
            ->whereNull('sessions.deleted_at')
            ->first([
                'sessions.id',
                'sessions.title',
                'sessions.status',
                'sessions.scheduled_start',
                'sessions.scheduled_end',
            ]);

        abort_if($row === null, 404);

        $mode = $request->query('mode') === 'pseudonymous' ? 'pseudonymous' : 'announced';

        $displayName = $mode === 'pseudonymous'
            ? __('virtualclassroom::messages.admin_observer_name')
            : trim((string) $user?->getAttribute('name')).' ('.__('virtualclassroom::messages.admin_observer_suffix').')';

        $url = $this->enterClassroom->url(
            row: $row,
            organizationId: $organizationId,
            userId: $userId,
            displayName: $displayName,
            role: JoinRole::Viewer,
            isFrozen: false,
            isTeacher: false,
            returnUrl: route('console.sessions'),
        );

        $this->audit->record(
            organizationId: $organizationId,
            actorId: $userId,
            actorType: 'user',
            action: 'virtualclassroom.observed',
            auditableType: 'sessions',
            auditableId: (string) $row->id,
            oldValues: null,
            newValues: ['mode' => $mode],
            reason: null,
        );

        return redirect()->away($url);
    }
}
