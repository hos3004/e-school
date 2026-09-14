<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SessionReviewRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\Contracts\ProgramRulesQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\ExcuseAbsenceAction;
use Modules\Sessions\Application\Actions\MarkNoShowAction;
use Modules\Sessions\Application\Actions\RecordOffPlatformSessionAction;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionFactsQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\LocalizedJsonColumn;

/**
 * شاشة اعتماد الحصص — الطرف البشري من سلسلة المستحقات.
 *
 * الحصة لا تولّد قيدة مستحقات إلا إذا وصلت حالة نهائية، و`sessions:finalize-due`
 * لا يعتمد آليًا إلا مكتمل الدليل. ما نقص دليله كان يبقى معلّقًا بلا أي مسار في
 * الواجهة، فيُدرّس المعلم ولا يظهر له مستحق ولا عدّاد. هذه الشاشة هي المسار.
 *
 * تعرض صنفين لا واحدًا:
 *   awaiting_review        — انتهت وسُجّلت، تنتظر قرارًا.
 *   scheduled / confirmed  — مضى موعدها ولم تتحرك أصلًا (المعلم لم يدخل الغرفة
 *                            من المنصة، أو دُرِّست خارجها). هذه أخطر لأنها
 *                            تُحتسب صفرًا في كل العدادات وهي في الواقع عمل تم.
 *
 * القرار يُنفَّذ عبر Actions موديول Sessions نفسها — لا انتقال حالة يُكتب هنا —
 * ويُشترط سبب مكتوب، ويُحمى بـPolicy مختلفة لكل قرار.
 */
final class SessionReviewController extends Controller
{
    /** @var array<string, bool> */
    private array $rateCache = [];

    public function __construct(
        private readonly SessionAdministrationQueries $sessions,
        private readonly SessionParticipantAdministrationQueries $participants,
        private readonly AttendanceAdministrationQueries $attendance,
        private readonly SessionReportStatusQueries $reports,
        private readonly StaffQueries $staff,
        private readonly StudentDirectoryQueries $students,
        private readonly AcademicCatalogQueries $catalog,
        private readonly SchoolClockQueries $clock,
        private readonly SessionFactsQueries $facts,
        private readonly ProgramRulesQueries $programs,
        private readonly TeacherRateResolver $rates,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);

        $school = $this->clock->forOrganization($organizationId);
        $timezone = (string) (data_get($user, 'timezone') ?: $school['timezone']);
        $now = CarbonImmutable::now('UTC');
        $from = $now->subDays(max(1, (int) config('console.session_review_window_days')));

        $records = $this->sessions->forReport(
            $organizationId,
            $from,
            $now,
            statuses: [
                SessionStatus::AwaitingReview->value,
                SessionStatus::Scheduled->value,
                SessionStatus::Confirmed->value,
            ],
            limit: (int) config('console.directory_search_limit'),
        );

        /*
         * `forReport` يرشّح ببداية الحصة؛ الحصة التي بدأت قبل دقائق ولم تنتهِ
         * بعد ليست محلًّا لقرار، فتُستبعد بنهايتها هنا.
         */
        $records = array_values(array_filter(
            $records,
            static fn ($session): bool => CarbonImmutable::parse($session->scheduledEnd)->lessThan($now),
        ));

        $sessionIds = array_map(static fn ($session): string => $session->id, $records);
        $reportStates = $this->reports->forSessions($sessionIds);
        $participantRows = $this->participants->forSessions($organizationId, $sessionIds);

        $participantIds = [];
        $studentIds = [];
        foreach ($participantRows as $rows) {
            foreach ($rows as $row) {
                $participantIds[] = $row->id;
                $studentIds[] = $row->studentProfileId;
            }
        }
        $attendanceRows = $this->attendance->byParticipantIds(
            $organizationId,
            array_values(array_unique($participantIds)),
        );

        $teacherNames = $this->staff->namesForProfiles($organizationId, array_values(array_unique(array_filter(
            array_map(static fn ($session): string => (string) $session->staffProfileId, $records),
        ))));
        $studentNames = $this->students->namesForProfiles($organizationId, array_values(array_unique($studentIds)));
        $courseRecords = $this->catalog->coursesByIds($organizationId, array_values(array_unique(
            array_map(static fn ($session): string => (string) $session->courseId, $records),
        )));

        $absentStatuses = (array) config('scheduling.auto_finalize.absent_attendance_statuses');
        $checkRates = count($records) <= (int) config('console.session_review_rate_check_limit');
        $rows = [];
        foreach ($records as $session) {
            $start = CarbonImmutable::parse($session->scheduledStart)->setTimezone($timezone);
            $end = CarbonImmutable::parse($session->scheduledEnd)->setTimezone($timezone);
            $sessionParticipants = $participantRows[$session->id] ?? [];
            $recorded = 0;
            $absent = 0;
            $students = [];
            foreach ($sessionParticipants as $participant) {
                $record = $attendanceRows[$participant->id] ?? null;
                if ($record !== null) {
                    $recorded++;
                    if (in_array($record->status, $absentStatuses, true)) {
                        $absent++;
                    }
                }
                $students[] = [
                    'id' => $participant->studentProfileId,
                    'name' => $studentNames[$participant->studentProfileId] ?? __('console.not_set'),
                    'attendance' => $record?->status,
                    'joined' => $participant->firstJoinedAt !== null,
                ];
            }

            $status = SessionStatus::tryFrom($session->status);
            $rows[] = [
                'id' => $session->id,
                'status' => $session->status,
                'status_label' => $status?->label() ?? $session->status,
                'never_started' => $status !== SessionStatus::AwaitingReview,
                'date' => $start->toDateString(),
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'minutes' => (int) round($start->diffInMinutes($end)),
                'teacher' => $teacherNames[$session->staffProfileId] ?? __('console.unassigned'),
                'course' => isset($courseRecords[$session->courseId])
                    ? LocalizedJsonColumn::display($courseRecords[$session->courseId]->name)
                    : __('console.not_set'),
                'students' => $students,
                'participants' => count($sessionParticipants),
                'attendance_recorded' => $recorded,
                'all_absent' => $sessionParticipants !== [] && $absent === count($sessionParticipants),
                'report' => ($reportStates[$session->id] ?? null)?->state() ?? 'missing',
                'rate_ok' => $checkRates ? $this->hasApplicableRate($session->id) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);

        return Inertia::render('Console/SessionReview', [
            'rows' => $rows,
            'timezone' => $timezone,
            'can' => [
                'complete' => $user?->can('session.finalize') ?? false,
                'no_show' => $user?->can('attendance.record') ?? false,
                'excused' => $user?->can('session.cancel') ?? false,
                'cancelled_by_school' => $user?->can('session.cancel') ?? false,
            ],
        ]);
    }

    /**
     * هل لهذه الحصة سعر مطبَّق أصلًا؟
     *
     * اعتماد حصة بلا سعر يُقفلها بلا أي قيدة مستحقات ويسجّل
     * `payroll.entry.rate_unresolved` فقط — أي يظهر للمعلم صفر بلا سبب
     * مفهوم. إظهار النقص **قبل** القرار أرخص من تصحيحه بقيدة تسوية بعده.
     *
     * السعر يُحسب من الحصة الأصلية إن كانت هذه تعويضية، تمامًا كما يفعل
     * `RecordSessionPayrollEntry`، وإلا لأظهرت كل تعويضية نقصًا كاذبًا.
     */
    private function hasApplicableRate(string $sessionId): ?bool
    {
        $facts = $this->facts->payrollFactsFor($sessionId);

        if ($facts === null) {
            return null;
        }

        if ($facts->isMakeup() && $facts->makeupForSessionId !== null) {
            $facts = $this->facts->payrollFactsFor($facts->makeupForSessionId) ?? $facts;
        }

        $durationMinutes = (int) round($facts->scheduledStart->diffInMinutes($facts->scheduledEnd));

        /*
         * الصفوف تتكرر بشدة على نفس (معلم، مقرر، نوع، مدة، يوم) — جدول أسبوعي
         * واحد يولّد عشرات الحصص المتطابقة في هذه المفاتيح. بلا هذه الذاكرة
         * كانت الصفحة تعيد نفس بحث السعر لكل صف.
         */
        $key = implode('|', [
            $facts->staffProfileId,
            $facts->courseId,
            $facts->sessionType,
            $durationMinutes,
            $facts->scheduledStart->toDateString(),
        ]);

        if (array_key_exists($key, $this->rateCache)) {
            return $this->rateCache[$key];
        }

        $programIds = $this->programs->programIdsOfCourse($facts->courseId);

        return $this->rateCache[$key] = $this->rates->resolve(
            staffProfileId: $facts->staffProfileId,
            sessionDate: $facts->scheduledStart,
            programId: $programIds === [] ? null : (string) reset($programIds),
            courseId: $facts->courseId,
            sessionType: $facts->sessionType,
            durationMinutes: $durationMinutes,
        ) !== null;
    }

    public function finalize(SessionReviewRequest $request, string $session): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();

        /** @var Session $record */
        $record = Session::query()->forOrganization($organizationId)->findOrFail($session);

        /*
         * الحالة المتوقعة تأتي من الشاشة التي رآها المستخدم. لو تغيّرت الحصة
         * بين العرض والقرار — اعتمدها زميل، أو أقفلها الأمر المجدول — يُرفض
         * القرار بدل أن يُبنى على شاشة قديمة.
         */
        if ($record->status->value !== $data['expected_status']) {
            throw ValidationException::withMessages([
                'decision' => __('console_session_review.stale'),
            ]);
        }

        try {
            $reason = (string) $data['reason'];

            match ((string) $data['decision']) {
                'complete' => $this->complete($record, $actorId, $reason),
                'no_show' => $this->noShow($record, $actorId, $reason),
                'excused' => $this->excuse($record, $actorId, $reason),
                'cancelled_by_school' => $this->cancel($record, $actorId, $reason),
                // غير قابل للوصول: القائمة مقفلة في SessionReviewRequest::DECISIONS.
                default => throw ValidationException::withMessages([
                    'decision' => __('console_session_review.stale'),
                ]),
            };
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages(['decision' => $violation->getMessage()]);
        }

        return back()->with('success', __('console_session_review.recorded'));
    }

    private function complete(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('complete', $session);

        /*
         * `scheduled → completed` ليس انتقالًا مشروعًا، والحصص التي لم تُفتح
         * من المنصة هي أغلب ما تعرضه هذه الشاشة. تُنقل أولًا عبر مسارها
         * المشروع إلى `awaiting_review` بموعدها المجدول كوقت تنفيذ، ثم
         * تُعتمد بنفس إجراء الاعتماد الوحيد.
         */
        $session = app(RecordOffPlatformSessionAction::class)->execute($session, $actorId, $reason);

        app(CompleteSessionAction::class)->execute($session, $actorId, $reason);
    }

    private function noShow(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('markNoShow', $session);
        app(MarkNoShowAction::class)->execute($session, $reason, $actorId);
    }

    private function excuse(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('excuse', $session);
        app(ExcuseAbsenceAction::class)->execute($session, $reason, $actorId);
    }

    private function cancel(Session $session, string $actorId, string $reason): void
    {
        Gate::authorize('cancel', $session);
        app(CancelSessionAction::class)->execute($session, SessionStatus::CancelledBySchool, $reason, $actorId);
    }
}
