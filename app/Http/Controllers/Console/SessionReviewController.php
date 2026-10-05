<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SessionQuickApprovalRequest;
use App\Http\Requests\Console\SessionReviewRequest;
use App\Services\Console\SessionDecisionService;
use App\Services\Console\SessionRateSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicReports\Domain\Contracts\SessionReportBatchQueries;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Contracts\StaffQueries;
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
 * القرار يُنفَّذ عبر `SessionDecisionService` — لا انتقال حالة يُكتب هنا —
 * ويُشترط سبب مكتوب، ويُحمى بـPolicy مختلفة لكل قرار. نفس الخدمة يستدعيها ملف
 * حسابات المعلم، فالقرار واحد من أي شاشة جاء.
 */
final class SessionReviewController extends Controller
{
    public function __construct(
        private readonly SessionAdministrationQueries $sessions,
        private readonly SessionParticipantAdministrationQueries $participants,
        private readonly AttendanceAdministrationQueries $attendance,
        private readonly SessionReportStatusQueries $reports,
        private readonly SessionReportBatchQueries $reportContents,
        private readonly StaffQueries $staff,
        private readonly StudentDirectoryQueries $students,
        private readonly AcademicCatalogQueries $catalog,
        private readonly SchoolClockQueries $clock,
        private readonly SessionRateSuggestion $rates,
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
        $blockingStatuses = (array) config('scheduling.auto_finalize.blocking_attendance_statuses');

        /*
         * «مستحقة» = مضى موعدها ويوجد ما يثبت أن المعلم أدّاها — فتحُه للغرفة
         * من المنصة (`actual_start`) أو تقريره عنها — فلم يبقَ إلا توقيع
         * الإدارة. تُفصل عن بقية المعلّق لأن خلط الاثنين هو ما جعل الحصص
         * المؤدَّاة تضيع بين الحصص التي لا يُعرف عنها شيء.
         */
        $reportContents = [];
        foreach ($this->reportContents->forSessions($sessionIds) as $report) {
            $reportContents[$report->sessionId] = $report;
        }

        $evidenceIds = [];
        foreach ($records as $candidate) {
            $submitted = (($reportStates[$candidate->id] ?? null)?->submittedAt) !== null;

            if ($candidate->actualStart !== null || $submitted) {
                $evidenceIds[$candidate->id] = true;
            }
        }

        /*
         * فحص السعر يمر بجدول الأسعار لكل صف، فيُحدّ بعدد. لكن صفوف الدليل
         * المكتمل هي وحدها التي يظهر لها زر الاعتماد السريع، والزر لا يظهر بلا
         * سعر معروف — فتُفحص أسعارها ولو تجاوز إجمالي المعلّق الحد، وإلا اختفى
         * الزر كلما طالت القائمة، وهي أحوج ما تكون إليه حينها.
         */
        $rateLimit = (int) config('console.session_review_rate_check_limit');
        $checkAllRates = count($records) <= $rateLimit;
        $checkEvidenceRates = count($evidenceIds) <= $rateLimit;
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
            $hasEvidence = isset($evidenceIds[$session->id]);
            $allAbsent = $sessionParticipants !== [] && $absent === count($sessionParticipants);

            /*
             * `not_held` دليل نفي لا دليل ناقص: رصدها أحدهم بأن الحصة لم تُقَم،
             * فلا تُعتمد بضغطة مهما اكتمل باقي الدليل.
             */
            $notHeld = false;
            foreach ($sessionParticipants as $participant) {
                if (in_array(($attendanceRows[$participant->id] ?? null)?->status, $blockingStatuses, true)) {
                    $notHeld = true;
                }
            }

            $rateOk = ($checkAllRates || ($hasEvidence && $checkEvidenceRates))
                ? $this->rates->hasApplicableRate($session->id)
                : null;
            $report = $reportContents[$session->id] ?? null;

            $rows[] = [
                'id' => $session->id,
                'status' => $session->status,
                'status_label' => $status?->label() ?? $session->status,
                'never_started' => $status !== SessionStatus::AwaitingReview,
                'due' => $hasEvidence,
                'teacher_joined' => $session->actualStart !== null,
                'ready' => $hasEvidence && !$allAbsent && !$notHeld && $rateOk === true,
                'approve_url' => route('console.sessions.review.approve', ['session' => $session->id]),
                'report_detail' => $report === null ? null : [
                    'topics' => $report->topicsCovered,
                    'homework' => $report->homeworkAssigned,
                    'notes' => $report->generalNotes,
                    'next_plan' => $report->nextSessionPlan,
                    'is_late' => $report->isLate,
                    'students' => array_map(static fn ($entry): array => [
                        'id' => $entry->studentProfileId,
                        'name' => $studentNames[$entry->studentProfileId] ?? __('console.not_set'),
                        'participation' => $entry->participation,
                        'performance' => $entry->performance,
                        'commitment' => $entry->commitment,
                        'note' => $entry->note,
                    ], $report->students),
                ],
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
                'all_absent' => $allAbsent,
                'report' => ($reportStates[$session->id] ?? null)?->state() ?? 'missing',
                'rate_ok' => $rateOk,
            ];
        }

        /*
         * الأحدث أولًا: القرار يُتخذ على ما جرى للتوّ وهو ما زال في ذهن من
         * يقرّره، لا على حصة من قبل ثلاثة أشهر تتصدّر القائمة كل يوم.
         */
        usort($rows, static fn (array $a, array $b): int => [$b['date'], $b['start']] <=> [$a['date'], $a['start']]);

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

    public function finalize(
        SessionReviewRequest $request,
        string $session,
        SessionDecisionService $decisions,
    ): RedirectResponse {
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
            /*
             * القرارات الأربعة تعيش في خدمة واحدة يستدعيها ملف حسابات المعلم
             * أيضًا، فلا يوجد طريقان لإقفال حصة يتباعدان مع أول تعديل.
             */
            $decisions->decide($record, (string) $data['decision'], $actorId, (string) $data['reason']);
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages(['decision' => $violation->getMessage()]);
        }

        return back()->with('success', __('console_session_review.recorded'));
    }

    /**
     * اعتماد بضغطة واحدة للحصة التي اكتمل دليلها.
     *
     * القرار التفصيلي يطلب سببًا مكتوبًا لأن قراره قد يكون أي شيء — اعتماد أو
     * تغيّب أو إلغاء — ولا يعرف النظام أيّها اختير ولا لماذا. أما هنا فالقرار
     * واحد ثابت، ودليله مرصود في البيانات لا في رأس من يقرّر، فكتابة السبب
     * تصير نسخًا يدويًا لما يعرفه النظام أصلًا. يُولَّد السبب من الدليل نفسه
     * ويُحفظ كاملًا في `audit_log` و`session_status_history`، فلا يخسر السجل
     * شيئًا مقابل اختصار الخطوات.
     *
     * الأهلية تُفحص هنا من جديد ولا تُؤخذ من الواجهة: الزر قد يكون معروضًا على
     * شاشة قديمة، والقرار يفتح قيدة مستحقات لا تُعدَّل بعد إنشائها.
     */
    public function approve(
        SessionQuickApprovalRequest $request,
        string $session,
        SessionDecisionService $decisions,
    ): RedirectResponse {
        $organizationId = (string) $request->user()?->organization_id;
        $actorId = (string) $request->user()?->getAuthIdentifier();

        /** @var Session $record */
        $record = Session::query()->forOrganization($organizationId)->findOrFail($session);

        if ($record->status->value !== (string) $request->validated('expected_status')) {
            throw ValidationException::withMessages([
                'decision' => __('console_session_review.stale'),
            ]);
        }

        $reason = $this->quickApprovalReason($organizationId, $record);

        try {
            $decisions->decide($record, 'complete', $actorId, $reason);
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages(['decision' => $violation->getMessage()]);
        }

        return back()->with('success', __('console_session_review.recorded'));
    }

    /**
     * سبب الاعتماد السريع مشتقًّا من الدليل — أو رفض يشرح الناقص وطريق تجاوزه.
     *
     * لكل رفض هنا مخرج: الشاشة التفصيلية تقبل نفس الحصة بسبب مكتوب، أو تقبل
     * قرارًا آخر أصدق منها. أما السعر الناقص فلا مخرج له إلا إضافته، لأن
     * الاعتماد بدونه يُقفل الحصة بلا قيدة ولا يُصحَّح إلا بقيدة تسوية.
     */
    private function quickApprovalReason(string $organizationId, Session $record): string
    {
        $sessionId = (string) $record->getKey();

        if (!in_array($record->status, [
            SessionStatus::Scheduled,
            SessionStatus::Confirmed,
            SessionStatus::AwaitingReview,
        ], true)) {
            $this->refuseQuickApproval('blocked_status');
        }

        if (CarbonImmutable::parse((string) $record->scheduled_end)->isFuture()) {
            $this->refuseQuickApproval('blocked_not_ended');
        }

        $reportSubmitted = ($this->reports->forSessions([$sessionId])[$sessionId] ?? null)?->submittedAt !== null;
        $teacherJoined = $record->actual_start !== null;

        if (!$teacherJoined && !$reportSubmitted) {
            $this->refuseQuickApproval('blocked_no_evidence');
        }

        $participants = $this->participants->forSession($organizationId, $sessionId);
        $attendance = $participants === [] ? [] : $this->attendance->byParticipantIds(
            $organizationId,
            array_map(static fn ($participant): string => $participant->id, $participants),
        );

        $blocking = (array) config('scheduling.auto_finalize.blocking_attendance_statuses');

        foreach ($attendance as $row) {
            if (in_array($row->status, $blocking, true)) {
                $this->refuseQuickApproval('blocked_not_held');
            }
        }

        /*
         * «كل الطلاب متغيّبون» يجب أن يُقاس كما تقيسه الشاشة: عدد سجلات الغياب
         * مقابل عدد المشاركين، لا مقابل عدد السجلات الموجودة. حصة لطالبين رُصد
         * غياب أحدهما فقط ليست حصة غاب عنها الجميع، وردّها هنا كان يناقض ما
         * عرضته الشاشة للمستخدم قبل ضغطة واحدة.
         */
        $absentStatuses = (array) config('scheduling.auto_finalize.absent_attendance_statuses');
        $absent = count(array_filter(
            $attendance,
            static fn ($row): bool => in_array($row->status, $absentStatuses, true),
        ));

        if ($participants !== [] && $absent === count($participants)) {
            $this->refuseQuickApproval('blocked_all_absent');
        }

        if ($this->rates->hasApplicableRate($sessionId) !== true) {
            $this->refuseQuickApproval('blocked_no_rate');
        }

        return (string) __('console_session_review.quick.reason_'.match (true) {
            $teacherJoined && $reportSubmitted => 'joined_with_report',
            $teacherJoined => 'joined_no_report',
            default => 'report_off_platform',
        });
    }

    private function refuseQuickApproval(string $key): never
    {
        throw ValidationException::withMessages([
            'decision' => __('console_session_review.quick.'.$key),
        ]);
    }
}
