<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Services\Console\SessionRateSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomPresenceQueries;
use Shared\Support\LocalizedJsonColumn;

/**
 * شاشة المتابعة — يوم المدرسة في لقطة واحدة.
 *
 * بقية الشاشات تجيب عن «ماذا حدث؟» بعد أن يحدث. هذه تجيب عن «ماذا يحدث الآن؟»،
 * وهو سؤال مختلف: الحصة التي موعدها الآن ولم يدخلها المعلم ليست خطأ في البيانات
 * بل موقف يحتاج تدخلًا خلال دقائق، وبعدها يصير حصة ضائعة تُكتشف آخر الشهر.
 *
 * الحالة تُشتق من **الساعة** لا من حالة الحصة المسجَّلة: حصة ما زالت `scheduled`
 * لأن أحدًا لم يفتح الغرفة هي «جارية الآن ولم يدخل أحد» في نظر المتابِع، لا
 * «مجدولة». هذا بالضبط ما يجعل ٥٧ حصة تمر دون أن يلاحظها أحد.
 */
final class LiveBoardController extends Controller
{
    public function __invoke(
        Request $request,
        SessionAdministrationQueries $sessions,
        SessionParticipantAdministrationQueries $participants,
        ClassroomPresenceQueries $presence,
        SessionReportStatusQueries $reports,
        StaffQueries $staff,
        StudentDirectoryQueries $students,
        AcademicCatalogQueries $catalog,
        SessionRateSuggestion $rates,
        ConsoleContext $context,
    ): Response {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);

        $timezone = (string) $context->forRequest($request)['timezone'];
        $now = CarbonImmutable::now('UTC');
        $localNow = $now->setTimezone($timezone);

        /*
         * نافذة اليوم المحلي كاملة: المنتهية تشرح ما فات، والقادمة تُظهر ما
         * يستعد له. الحدود تُحسب محليًا ثم تُحوَّل إلى UTC كما يقتضي عقد الوقت.
         */
        $from = $localNow->startOfDay()->utc();
        $until = $localNow->endOfDay()->utc();

        $records = $sessions->forReport(
            $organizationId,
            $from,
            $until,
            statuses: [],
            limit: (int) config('console.directory_search_limit'),
        );

        $sessionIds = array_map(static fn ($session): string => (string) $session->id, $records);
        $participantRows = $participants->forSessions($organizationId, $sessionIds);
        $presentBySession = $presence->currentlyPresentUserIds($sessionIds);
        $reportStates = $reports->forSessions($sessionIds);

        $staffIds = array_values(array_unique(array_map(
            static fn ($session): string => (string) $session->staffProfileId,
            $records,
        )));
        $teacherNames = $staff->namesForProfiles($organizationId, $staffIds);
        $teacherUserIds = [];
        foreach ($staffIds as $staffId) {
            $profile = $staff->userIdForProfile($organizationId, $staffId);
            if ($profile !== null) {
                $teacherUserIds[$staffId] = (string) $profile;
            }
        }

        $studentIds = [];
        foreach ($participantRows as $rows) {
            foreach ($rows as $row) {
                $studentIds[] = $row->studentProfileId;
            }
        }
        $studentNames = $students->namesForProfiles($organizationId, array_values(array_unique($studentIds)));
        $courses = $catalog->coursesByIds($organizationId, array_values(array_unique(array_map(
            static fn ($session): string => (string) $session->courseId,
            $records,
        ))));

        $rows = [];
        foreach ($records as $session) {
            $start = CarbonImmutable::parse($session->scheduledStart);
            $end = CarbonImmutable::parse($session->scheduledEnd);
            $status = SessionStatus::tryFrom($session->status);
            $sessionParticipants = $participantRows[$session->id] ?? [];
            $present = $presentBySession[$session->id] ?? [];

            $teacherUserId = $teacherUserIds[$session->staffProfileId] ?? null;
            $teacherIn = $teacherUserId !== null && in_array($teacherUserId, $present, true);

            /*
             * حضور الطالب يُقرأ من حالة المشارك الحية التي يحدّثها webhook
             * الغرفة، لا من أحداث الغرفة مباشرة: المشارك قد يدخل برابطه دون
             * أن يكون له حساب مستخدم مربوط.
             */
            $studentsIn = 0;
            $studentRows = [];
            foreach ($sessionParticipants as $participant) {
                if (!$participant->invitationActive) {
                    continue;
                }
                $inside = $participant->currentJoinedAt !== null;
                $studentsIn += (int) $inside;
                $studentRows[] = [
                    'id' => $participant->studentProfileId,
                    'name' => $studentNames[$participant->studentProfileId] ?? __('console.not_set'),
                    'inside' => $inside,
                    'everJoined' => $participant->firstJoinedAt !== null,
                    'excused' => $participant->excusedAt !== null,
                ];
            }
            $expected = count($studentRows);

            /*
             * «مستحقة» تُفصل عن «منتهية بلا قرار» بدليل واحد: هل يوجد ما يثبت
             * أن المعلم أدّى الحصة؟ فتحه للغرفة من المنصة (`actual_start`) أو
             * تقريره عنها. الأولى تنتظر توقيع الإدارة فقط، والثانية لا يُعرف
             * عنها شيء بعد. خلطهما في عدّاد واحد هو ما جعل الحصص المؤدَّاة
             * تضيع بين الحصص المشكوك فيها.
             */
            $reportSubmitted = (($reportStates[$session->id] ?? null)?->state() ?? 'missing') !== 'missing';
            $state = $this->state(
                $status,
                $start,
                $end,
                $now,
                $teacherIn,
                $studentsIn,
                $session->actualStart !== null || $reportSubmitted,
            );

            /*
             * السعر يُفحص للمستحقة وحدها: اعتماد حصة بلا سعر يقفلها بلا أي
             * قيدة ويُظهر للمعلم صفرًا بلا سبب، فالزر السريع لا يُعرض لها.
             */
            $rateOk = $state === 'due' ? $rates->hasApplicableRate((string) $session->id) : null;

            /*
             * الحصة التي ما زالت `in_progress` مستحقة فعلًا، لكن لا يقبلها
             * الاعتماد بعد: مسار الإقفال يبدأ من `scheduled`/`confirmed` أو من
             * `awaiting_review`، وهي بينهما حتى يلتقطها `sessions:end-elapsed`.
             * عرض الزر لها كان يَعِد بما يرفضه الخادم، فيُخفى وحده وتبقى الحصة
             * في عدّاد «مستحقة» حيث هي.
             */
            $approvable = $state === 'due' && in_array($status, [
                SessionStatus::Scheduled,
                SessionStatus::Confirmed,
                SessionStatus::AwaitingReview,
            ], true);

            $rows[] = [
                'id' => (string) $session->id,
                'state' => $state,
                'rateOk' => $rateOk,
                'canApprove' => $approvable && $rateOk === true,
                'needsRate' => $approvable && $rateOk === false,
                'reportSubmitted' => $reportSubmitted,
                'teacherJoined' => $session->actualStart !== null,
                'approveUrl' => route('console.sessions.review.approve', ['session' => $session->id]),
                'status' => (string) $session->status,
                'statusLabel' => $status?->label() ?? (string) $session->status,
                'start' => $start->setTimezone($timezone)->format('H:i'),
                'end' => $end->setTimezone($timezone)->format('H:i'),
                'startsAt' => $start->toIso8601String(),
                'endsAt' => $end->toIso8601String(),
                'minutes' => (int) round($start->diffInMinutes($end)),
                'teacher' => $teacherNames[$session->staffProfileId] ?? __('console.unassigned'),
                'teacherIn' => $teacherIn,
                'course' => isset($courses[$session->courseId])
                    ? LocalizedJsonColumn::display($courses[$session->courseId]->name)
                    : __('console.not_set'),
                'lesson' => LocalizedJsonColumn::display($session->title),
                'students' => $studentRows,
                'studentsIn' => $studentsIn,
                'studentsExpected' => $expected,
                'reportMissing' => !$reportSubmitted,
                'reviewUrl' => route('console.sessions.review'),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [self::ORDER[$a['state']], $a['startsAt']]
            <=> [self::ORDER[$b['state']], $b['startsAt']]);

        $counts = array_fill_keys(array_keys(self::ORDER), 0);
        foreach ($rows as $row) {
            $counts[$row['state']]++;
        }

        return Inertia::render('Console/LiveBoard', [
            'rows' => $rows,
            'counts' => $counts,
            'timezone' => $timezone,
            'now' => $localNow->format('H:i'),
            'date' => $localNow->toDateString(),
            'refreshSeconds' => (int) config('console.live_board_refresh_seconds'),
            'canReview' => (bool) $user?->can('session.finalize'),
        ]);
    }

    /** ترتيب العرض: ما يحتاج تدخلًا الآن أولًا، ثم ما يطمئن، ثم ما فات. */
    private const ORDER = [
        'running_nobody' => 0,
        'running_no_teacher' => 1,
        'running_no_student' => 2,
        'running_ok' => 3,
        'due' => 4,
        'upcoming' => 5,
        'ended_unresolved' => 6,
        'ended' => 7,
    ];

    private function state(
        ?SessionStatus $status,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $now,
        bool $teacherIn,
        int $studentsIn,
        bool $teacherEvidence,
    ): string {
        $closed = in_array($status, [
            SessionStatus::Completed,
            SessionStatus::NoShow,
            SessionStatus::Excused,
            SessionStatus::CancelledByStudent,
            SessionStatus::CancelledByTeacher,
            SessionStatus::CancelledBySchool,
            SessionStatus::Postponed,
            SessionStatus::Superseded,
        ], true);

        if ($closed) {
            return 'ended';
        }

        if ($now->lessThan($start)) {
            return 'upcoming';
        }

        /*
         * انتهى موعدها ولم تُقفل: هذه هي الحصة التي لا يحرّكها شيء آلي —
         * `sessions:end-elapsed` لا يلتقط إلا ما دخل `in_progress`. تُعرض
         * منفصلة لأنها الوحيدة التي تتحول إلى مال ضائع إن تُركت.
         */
        if ($now->greaterThanOrEqualTo($end)) {
            return $teacherEvidence ? 'due' : 'ended_unresolved';
        }

        return match (true) {
            $teacherIn && $studentsIn > 0 => 'running_ok',
            $teacherIn => 'running_no_student',
            $studentsIn > 0 => 'running_no_teacher',
            default => 'running_nobody',
        };
    }
}
