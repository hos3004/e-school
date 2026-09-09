<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Application\Queries\ProfileAdministrationQueryService;
use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\PersonProfileData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Services\TeacherSessionCounts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;

final class LearningController extends Controller
{
    public function __construct(private readonly PortalData $data, private readonly TeachingStudents $students) {}

    /** @return array{string, string, string} */
    private function context(Request $request, string $kind): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $userId = (string) $request->user()?->getAuthIdentifier();
        $id = $kind === 'student' ? $this->data->studentProfileId($userId, $organizationId)
            : $this->data->staffProfileId($userId, $organizationId);
        abort_if($organizationId === '' || $id === null, 403);

        return [$organizationId, $userId, $id];
    }

    private function timezone(Request $request): string
    {
        $context = app(ConsoleContext::class)->forRequest($request);
        $timezone = (string) $context['timezone'];

        return in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }

    /** @return array<string, mixed> */
    private function common(Request $request, string $kind): array
    {
        return ['kind' => $kind, 'timezone' => $this->timezone($request),
            'capabilities' => collect(['schedule.view', 'student.view', 'session.view', 'session.join',
                'assignment.submit', 'session_report.view', 'attendance.record', 'session_report.create',
                'enrollment.view', 'group.view', 'payroll.view', 'assignment.manage', 'assignment.grade', 'content.view', 'session.postpone.approve', 'staff.view'])->mapWithKeys(
                    fn (string $permission): array => [$permission => (bool) $request->user()?->can($permission)],
                )->all(), 'payrollEnabled' => (bool) config('features.payroll')];
    }

    public function student(Request $request): Response
    {
        [$organizationId, $userId, $id] = $this->context($request, 'student');
        $locale = app()->getLocale();
        $sessions = $this->data->upcomingStudentSessions($id, $locale, $organizationId);
        $assignments = $request->user()?->can('assignment.submit') ? $this->data->studentAssignments($id, $locale, $organizationId) : [];
        $assignments = array_map(static fn (array $assignment): array => [...$assignment, 'canSubmit' => $assignment['canSubmit']
            && in_array($assignment['submissionStatus'], ['open', 'overdue', 'pending'], true)], $assignments);

        return Inertia::render('Learning/Dashboard', [...$this->common($request, 'student'),
            'nextSession' => collect($sessions)->first(fn (array $session): bool => in_array($session['status'], ['scheduled', 'confirmed', 'in_progress'], true)),
            'sessions' => $this->data->studentWeekSessions($id, $locale, $this->timezone($request), $organizationId),
            'attendanceRate' => $this->data->attendanceRate($id, $organizationId),
            'reports' => $request->user()?->can('session_report.view') ? $this->data->monthlyReports($id, $locale, $organizationId) : [],
            'availability' => [], 'earnings' => null,
            'allAssignments' => $assignments,
            'libraryPreview' => app(LearningLibraryData::class)->preview($request, 'student'), 'teachingAssignments' => [],
            'assignments' => $this->data->openAssignments($assignments),
            'programs' => $request->user()?->can('enrollment.view') ? $this->data->studentPrograms($id, $organizationId, $locale) : [],
            'students' => [], 'pendingAttendance' => [], 'lateReports' => [],
        ]);
    }

    public function teacher(Request $request): Response
    {
        [$organizationId, $userId, $id] = $this->context($request, 'teacher');
        $locale = app()->getLocale();
        $sessions = $this->data->teacherWeekSessions($id, $locale, $this->timezone($request), $organizationId);
        $upcoming = $this->data->teacherScheduleSessions($id, $locale, $organizationId);
        $earnings = (bool) config('features.payroll') && $request->user()?->can('payroll.view')
            ? (app(TeacherEarningsQueries::class)->periodsFor($organizationId, $id, 1)[0] ?? null) : null;

        return Inertia::render('Learning/Dashboard', [...$this->common($request, 'teacher'),
            'sessionCounts' => app(TeacherSessionCounts::class)->forTeacher($organizationId, $id, $this->timezone($request)),
            'nextSession' => collect($upcoming)->first(fn (array $session): bool => in_array($session['status'], ['scheduled', 'confirmed', 'in_progress'], true)),
            'reports' => [], 'allAssignments' => [],
            'libraryPreview' => app(LearningLibraryData::class)->preview($request, 'teacher'),
            'teachingAssignments' => app(TeachingAssignmentsController::class)->preview($request),
            'availability' => $this->data->teacherAvailability($id),
            'earnings' => $earnings === null ? null : ['year' => $earnings->year, 'month' => $earnings->month, 'currency' => $earnings->currency,
                'netMinorUnits' => $earnings->netMinorUnits, 'sessionsCount' => $earnings->sessionsCount],
            'sessions' => $sessions, 'attendanceRate' => null, 'assignments' => [], 'programs' => [],
            'students' => $request->user()?->can('student.view') ? array_values($this->students->roster($organizationId, $id, $locale)) : [],
            'pendingAttendance' => $request->user()?->can('attendance.record') ? $this->data->teacherPendingAttendanceSessions($id, $locale, $organizationId) : [],
            'lateReports' => $request->user()?->can('session_report.create') ? $this->data->teacherLateReportSessions($id, $locale, $organizationId) : [],
        ]);
    }

    public function studentProfile(Request $request): Response
    {
        return $this->ownProfile($request, 'student');
    }

    public function teacherProfile(Request $request): Response
    {
        return $this->ownProfile($request, 'teacher');
    }

    private function ownProfile(Request $request, string $kind): Response
    {
        [$organizationId, $userId, $id] = $this->context($request, $kind);
        $locale = app()->getLocale();
        $profile = $kind === 'student' ? $this->data->studentProfile($userId, $organizationId, $locale)
            : $this->data->teacherProfile($userId, $organizationId);

        $hubQueries = app(ProfileAdministrationQueryService::class);
        $hub = $kind === 'student' ? $hubQueries->studentHub($organizationId, $id, $userId)
            : $hubQueries->teacherHub($organizationId, $id, $userId);
        unset($hub['account'], $hub['sessions'], $hub['guardians'], $hub['contracts'], $hub['rates']);
        $profileData = app(PersonProfileData::class);
        $profile = [...($profile ?? []), ...$profileData->identity($organizationId, $userId)];

        return Inertia::render('Learning/Profile', [...$this->common($request, $kind),
            'hub' => $hub, 'profileWorkspace' => $profileData->workspace($request, $organizationId, $kind, $id, 'self'),
            'profile' => $profile, 'own' => true, 'account' => $this->data->accountSettings($userId, $organizationId),
            'timezones' => $this->data->timezoneOptions(),
            'programs' => $kind === 'student' ? $this->data->studentPrograms($id, $organizationId, $locale) : [],
            'qualifications' => $kind === 'teacher' ? $this->data->teacherQualifications($id, $locale) : [],
            'attendanceRate' => $kind === 'student' ? $this->data->attendanceRate($id, $organizationId) : null,
            'updateUrl' => route('learning.'.$kind.'.profile.update'),
            'passwordUrl' => route('learning.'.$kind.'.profile.password'),
            'availabilityUrl' => $kind === 'teacher' && $request->user()?->can('staff.view') ? route('learning.teacher.availability') : null,
        ]);
    }

    public function teacherStudent(Request $request, string $student): Response
    {
        [$organizationId, $userId, $id] = $this->context($request, 'teacher');
        $profile = $this->students->profile($organizationId, $id, $student, app()->getLocale());
        abort_if($profile === null, 404);
        $returnUrl = route('learning.teacher.dashboard').'#studies';
        $origin = $request->query('from');
        if (is_string($origin) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $origin)
            && $this->data->teacherSession($id, $origin, app()->getLocale(), $organizationId) !== null) {
            $returnUrl = route('learning.teacher.sessions.show', ['session' => $origin]);
        }

        $workspace = app(PersonProfileData::class)
            ->workspace($request, $organizationId, 'student', $student, 'teacher', $id);
        $hub = ['groups' => []];
        foreach ($profile['tracks'] as $track) {
            if ($track['kind'] === 'individual') {
                $workspace['individual'][] = ['id' => $track['id'], 'course' => $track['name']];
            } else {
                $hub['groups'][] = ['id' => $track['id'], 'group' => $track['name']];
            }
        }

        return Inertia::render('Learning/Profile', [...$this->common($request, 'teacher'),
            'hub' => $hub, 'profileWorkspace' => $workspace,
            'profile' => $profile, 'own' => false, 'returnUrl' => $returnUrl, 'account' => null, 'timezones' => [],
            'programs' => [], 'qualifications' => [], 'attendanceRate' => null,
            'updateUrl' => null, 'passwordUrl' => null,
        ]);
    }
}
