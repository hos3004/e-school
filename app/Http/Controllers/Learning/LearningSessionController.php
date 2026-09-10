<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\StudentSessionController;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Controllers\Portal\TeacherSessionController;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;

/** Reuse the existing session presentation contract, including recording grants. */
final class LearningSessionController extends Controller
{
    public function __construct(
        private readonly SessionParticipantAdministrationQueries $participants,
        private readonly AttendanceAdministrationQueries $attendance,
    ) {}

    public function student(Request $request, string $session, StudentSessionController $controller, PortalData $data): Response
    {
        abort_if($data->studentProfileId((string) $request->user()?->getAuthIdentifier(), (string) $request->user()?->organization_id) === null, 403);

        return $this->present($request, $controller($request, $session), 'student', $session);
    }

    public function teacher(Request $request, string $session, TeacherSessionController $controller, PortalData $data): Response
    {
        abort_if($data->staffProfileId((string) $request->user()?->getAuthIdentifier(), (string) $request->user()?->organization_id) === null, 403);

        return $this->present($request, $controller($request, $session), 'teacher', $session);
    }

    private function present(Request $request, Response $source, string $kind, string $session): Response
    {
        // Inertia resolves controller props via its public response contract. This
        // avoids a second implementation of join windows and signed recording access.
        $projectionRequest = $request->duplicate();
        $projectionRequest->headers->set('X-Inertia', 'true');
        $projectionRequest->headers->remove('X-Inertia-Partial-Component');
        $projectionRequest->headers->remove('X-Inertia-Partial-Data');
        $projectionRequest->headers->remove('X-Inertia-Partial-Except');
        $projection = $source->toResponse($projectionRequest);
        abort_unless($projection instanceof JsonResponse, 500);
        $sourceProps = $projection->getData(true)['props'];
        $allowed = ['session', 'attendance', 'attendanceStatuses', 'initialReport', 'canSubmitReport',
            'postponementRequest', 'canRequestPostponement', 'studentApology', 'canSubmitApology',
            'postponementRequestUrl', 'studentApologyUrl'];
        $props = array_intersect_key($sourceProps, array_flip($allowed));
        if ($kind === 'teacher') {
            $organizationId = (string) $request->user()?->organization_id;
            $participants = $this->participants->forSession($organizationId, $session);
            $records = $this->attendance->byParticipantIds($organizationId, array_map(static fn ($participant): string => $participant->id, $participants));
            $confirmed = [];
            foreach ($participants as $participant) {
                $confirmed[$participant->studentProfileId] = ($records[$participant->id] ?? null)?->confirmedAt;
            }
            $props['attendance'] = array_map(static fn (array $row): array => [...$row,
                'confirmedAt' => $confirmed[$row['studentId']] ?? null], $props['attendance'] ?? []);
            $props['studentJoinLinks'] = $this->studentJoinLinks($request, $participants);
        }
        if (isset($props['session'])) {
            $props['session']['joinUrl'] = $request->user()?->can('session.join')
                ? route('learning.'.$kind.'.sessions.join', ['session' => $session]) : null;
        }
        $props['postponementRequestUrl'] = route('learning.sessions.postpone', ['kind' => $kind, 'session' => $session]);
        $props['studentApologyUrl'] = $kind === 'student' ? route('learning.student.sessions.apology', ['session' => $session]) : null;
        if (!empty($props['postponementRequest']['acceptAlternativeUrl'])) {
            $props['postponementRequest']['acceptAlternativeUrl'] = route('learning.student.postponements.accept-alternative', ['postponement' => $props['postponementRequest']['id']]);
        }
        $props['kind'] = $kind;
        $context = app(ConsoleContext::class)->forRequest($request);
        $timezone = (string) $context['timezone'];
        $props['timezone'] = in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $props['attendanceUpdateUrl'] = $kind === 'teacher' ? route('learning.teacher.sessions.attendance.store', ['session' => $session]) : null;
        $props['reportSubmitUrl'] = $kind === 'teacher' ? route('learning.teacher.sessions.report.store', ['session' => $session]) : null;
        $props['canViewStudents'] = (bool) $request->user()?->can('student.view');
        $props['reportScoreMin'] = SessionReportStudent::MIN_SCORE;
        $props['reportScoreMax'] = SessionReportStudent::MAX_SCORE;

        return Inertia::render('Learning/Session', $props);
    }

    /**
     * روابط الدخول اليدوية التي ينسخها المعلم للطالب المتعذّر دخوله لحسابه.
     *
     * الرابط موقّع لكل مشارك على حدة حتى يبقى الحضور منسوبًا لصاحبه، وينتهي
     * مع نهاية نافذة الدخول فلا يصلح لحصة أخرى ولا لما بعد انتهاء هذه الحصة.
     *
     * @param list<SessionParticipantAdministrationData> $participants
     * @return array<string, string> معرّف ملف الطالب => الرابط
     */
    private function studentJoinLinks(Request $request, array $participants): array
    {
        if ((bool) config('virtual-classroom.student_link.enabled') !== true
            || $request->user()?->can('session.join') !== true) {
            return [];
        }

        $afterMinutes = max(0, (int) config('virtual-classroom.join_window.after_minutes'));
        $links = [];

        foreach ($participants as $participant) {
            if (!$participant->invitationActive) {
                continue;
            }

            $links[$participant->studentProfileId] = URL::temporarySignedRoute(
                'classroom.student-link',
                CarbonImmutable::parse($participant->scheduledEnd, 'UTC')->addMinutes($afterMinutes),
                ['session' => $participant->sessionId, 'participant' => $participant->id],
            );
        }

        return $links;
    }
}
