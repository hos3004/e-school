<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionApproaching;
use Modules\Sessions\Domain\Events\SessionJoinWindowOpened;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionReminderDispatch;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * تذكيرات الحصة على مراحل.
 *
 * المراحل وأزمنتها تُقرأ من config('scheduling.reminder_dispatch.stages') —
 * لا رقم واحد هنا. لكل مرحلة صفّها في session_reminder_dispatches، وهو ما
 * يمنع التكرار بدل عمود واحد لا يحتمل إلا مرحلة واحدة.
 *
 * مرحلة روابط الدخول تختلف عن التنبيه المبكر في أن الرابط ليس واحدًا:
 *  · المعلم يأخذ رابط صفحة الحصة داخل النظام، فيدخل بحسابه ويُحتسب حضوره
 *    وتُقيَّد مستحقات الحصة له تلقائيًا.
 *  · الطالب يأخذ رابط الدخول الموقّع المربوط بمشاركته وحده، فيصل الفصل
 *    بنقرة واحدة دون تسجيل دخول، ويبقى الحضور محسوبًا لصاحبه.
 * ولأن الرابط يختلف بالمستلم، يُنشر حدث مستقل لكل طالب: قيد الصندوق الصادر
 * يُركَّب من حمولة الحدث، فحمولة واحدة تعني رابطًا واحدًا للجميع.
 */
final class DispatchSessionReminders extends Command
{
    protected $signature = 'sessions:dispatch-reminders';

    protected $description = 'Queue staged reminders and join links for upcoming sessions';

    public function __construct(
        private readonly Dispatcher $events,
        private readonly StudentDirectoryQueries $students,
        private readonly StaffQueries $staff,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $limit = max(1, (int) config('scheduling.reminder_dispatch.batch_size'));
        $dispatched = 0;

        foreach ($this->stages() as $stage) {
            $dispatched += $this->runStage($stage, $now, $limit);
        }

        $this->components->info(__('sessions::messages.reminders_dispatched', ['count' => $dispatched]));

        return self::SUCCESS;
    }

    /**
     * @return list<array{key: string, type: string, audience: string, before_minutes: int}>
     */
    private function stages(): array
    {
        $stages = [];

        foreach ((array) config('scheduling.reminder_dispatch.stages', []) as $stage) {
            if (!is_array($stage)) {
                continue;
            }

            $key = $stage['key'] ?? null;
            $beforeMinutes = (int) ($stage['before_minutes'] ?? 0);

            if (!is_string($key) || $key === '' || $beforeMinutes < 1) {
                continue;
            }

            $stages[] = [
                'key' => $key,
                'type' => is_string($stage['type'] ?? null) ? $stage['type'] : 'approaching',
                'audience' => is_string($stage['audience'] ?? null) ? $stage['audience'] : 'all',
                'before_minutes' => $beforeMinutes,
            ];
        }

        return $stages;
    }

    /**
     * الحالات التي تستحق هذه المرحلة.
     *
     * رابط الدخول يُرسل قبل الموعد بدقائق، والحصة تكون عندها كثيرًا في
     * `in_progress` لأن المعلم يستطيع فتح الفصل أبكر. حصرُها في «مجدولة أو
     * مؤكدة» كان يُسقط الرابط عن الحصص التي بدأت فعلًا — وهي أحوج ما تكون
     * إليه. المعيار هو نفسه الذي يفرضه EnterClassroom عند الدخول:
     * allowsJoining()، فلا يصل رابط يرفضه الفصل ولا يسقط رابط يقبله.
     *
     * التنبيه المبكر يبقى على حاله: قبل ساعتين لا تكون الحصة قد بدأت.
     *
     * @param array{key: string, type: string, audience: string, before_minutes: int} $stage
     * @return list<SessionStatus>
     */
    private function statusesFor(array $stage): array
    {
        if ($stage['type'] !== 'join_link') {
            return [SessionStatus::Scheduled, SessionStatus::Confirmed];
        }

        return array_values(array_filter(
            SessionStatus::cases(),
            static fn (SessionStatus $status): bool => $status->allowsJoining(),
        ));
    }

    /**
     * @param array{key: string, type: string, audience: string, before_minutes: int} $stage
     */
    private function runStage(array $stage, CarbonImmutable $now, int $limit): int
    {
        $until = $now->addMinutes($stage['before_minutes']);

        $sessionIds = Session::query()
            ->whereIn('status', $this->statusesFor($stage))
            ->where('scheduled_start', '>', $now)
            ->where('scheduled_start', '<=', $until)
            ->whereNotExists(static function ($query) use ($stage): void {
                $query->select(DB::raw(1))
                    ->from('session_reminder_dispatches')
                    ->whereColumn('session_reminder_dispatches.session_id', 'sessions.id')
                    ->where('session_reminder_dispatches.stage', $stage['key']);
            })
            ->orderBy('scheduled_start')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $dispatched = 0;

        foreach ($sessionIds as $sessionId) {
            if ($this->dispatchOne($sessionId, $stage, $now, $until)) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * @param array{key: string, type: string, audience: string, before_minutes: int} $stage
     */
    private function dispatchOne(string $sessionId, array $stage, CarbonImmutable $now, CarbonImmutable $until): bool
    {
        return DB::transaction(function () use ($sessionId, $stage, $now, $until): bool {
            /** @var Session|null $session */
            $session = Session::query()
                ->with(['participants' => static fn ($query) => $query->whereNull('revoked_at')])
                ->lockForUpdate()
                ->whereKey($sessionId)
                ->first();

            if ($session === null
                || !in_array($session->status, $this->statusesFor($stage), true)
                || $session->scheduled_start->lessThanOrEqualTo($now)
                || $session->scheduled_start->greaterThan($until)) {
                return false;
            }

            // القفل على الحصة لا يمنع تشغيلة ثانية بدأت قبله؛ الصف الفريد يمنع.
            $alreadyDispatched = SessionReminderDispatch::query()
                ->where('session_id', $sessionId)
                ->where('stage', $stage['key'])
                ->exists();

            if ($alreadyDispatched) {
                return false;
            }

            $recipients = $this->recipients($session);
            $count = $stage['type'] === 'join_link'
                ? $this->dispatchJoinLinks($session, $stage, $recipients, $now)
                : $this->dispatchApproaching($session, $recipients, $now);

            SessionReminderDispatch::query()->create([
                'organization_id' => (string) $session->organization_id,
                'session_id' => (string) $session->getKey(),
                'stage' => $stage['key'],
                'dispatched_at' => $now,
                'recipient_count' => $count,
            ]);

            return $count > 0;
        });
    }

    /**
     * @return array{
     *     students: list<array{participant_id: string, user_id: string}>,
     *     teacher_user_id: string|null
     * }
     */
    private function recipients(Session $session): array
    {
        $organizationId = (string) $session->organization_id;
        $participants = $session->participants
            ->mapWithKeys(static fn (mixed $participant): array => [
                (string) $participant->student_profile_id => (string) $participant->getKey(),
            ])
            ->all();
        $directory = $this->students->byIds($organizationId, array_keys($participants));
        $students = [];

        foreach ($participants as $studentProfileId => $participantId) {
            $userId = $directory[$studentProfileId]->userId ?? null;

            if (is_string($userId) && $userId !== '') {
                $students[] = ['participant_id' => $participantId, 'user_id' => $userId];
            }
        }

        return [
            'students' => $students,
            'teacher_user_id' => $this->staff->userIdForProfile(
                $organizationId,
                (string) $session->staff_profile_id,
            ),
        ];
    }

    /**
     * @param array{students: list<array{participant_id: string, user_id: string}>, teacher_user_id: string|null} $recipients
     */
    private function dispatchApproaching(Session $session, array $recipients, CarbonImmutable $now): int
    {
        $studentUserIds = array_values(array_unique(array_column($recipients['students'], 'user_id')));
        $teacherUserId = $recipients['teacher_user_id'];

        if ($studentUserIds === [] && $teacherUserId === null) {
            return 0;
        }

        $this->events->dispatch(new SessionApproaching(
            sessionId: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            scheduledStart: $session->scheduled_start->toIso8601String(),
            scheduledEnd: $session->scheduled_end->toIso8601String(),
            studentUserIds: $studentUserIds,
            teacherUserId: $teacherUserId,
            courseName: $this->courseName($session),
            durationMinutes: (int) $session->scheduled_start->diffInMinutes($session->scheduled_end),
        ));

        // العمود القديم يبقى محدثًا لأن لوحات التشغيل تقرأه بصفته «أُرسل تذكير».
        $session->forceFill(['reminder_sent_at' => $now])->save();

        return count($studentUserIds) + ($teacherUserId === null ? 0 : 1);
    }

    /**
     * @param array{key: string, type: string, audience: string, before_minutes: int} $stage
     * @param array{students: list<array{participant_id: string, user_id: string}>, teacher_user_id: string|null} $recipients
     */
    private function dispatchJoinLinks(
        Session $session,
        array $stage,
        array $recipients,
        CarbonImmutable $now,
    ): int {
        $minutesUntilStart = max(0, (int) round($now->diffInMinutes($session->scheduled_start)));

        if ($stage['audience'] === 'teacher') {
            $teacherUserId = $recipients['teacher_user_id'];

            if ($teacherUserId === null) {
                return 0;
            }

            $this->events->dispatch($this->joinWindowEvent(
                session: $session,
                audience: 'teacher',
                joinUrl: $this->teacherUrl($session),
                studentUserIds: [],
                teacherUserId: $teacherUserId,
                minutesUntilStart: $minutesUntilStart,
            ));

            return 1;
        }

        $sent = 0;

        foreach ($recipients['students'] as $student) {
            $joinUrl = $this->studentUrl($session, $student['participant_id']);

            if ($joinUrl === null) {
                continue;
            }

            $this->events->dispatch($this->joinWindowEvent(
                session: $session,
                audience: 'student',
                joinUrl: $joinUrl,
                studentUserIds: [$student['user_id']],
                teacherUserId: null,
                minutesUntilStart: $minutesUntilStart,
            ));
            $sent++;
        }

        return $sent;
    }

    /**
     * @param list<string> $studentUserIds
     */
    private function joinWindowEvent(
        Session $session,
        string $audience,
        string $joinUrl,
        array $studentUserIds,
        ?string $teacherUserId,
        int $minutesUntilStart,
    ): SessionJoinWindowOpened {
        return new SessionJoinWindowOpened(
            sessionId: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            audience: $audience,
            joinUrl: $joinUrl,
            scheduledStart: $session->scheduled_start->toIso8601String(),
            scheduledEnd: $session->scheduled_end->toIso8601String(),
            studentUserIds: $studentUserIds,
            teacherUserId: $teacherUserId,
            courseName: $this->courseName($session),
            minutesUntilStart: $minutesUntilStart,
        );
    }

    /**
     * اسم الحصة بكل اللغات المدعومة، أو بديل مترجم حين يكون العنوان فارغًا.
     *
     * القوالب تعلن course_name بارامترًا إلزاميًا، وTemplateRenderer يرفض
     * الإرسال إذا لم تُحل قيمته إلى نص. الخريطة الفارغة لا تُحل، فلا تُمرَّر.
     *
     * @return array<string, string>
     */
    private function courseName(Session $session): array
    {
        $title = is_array($session->title) ? $session->title : [];

        if (array_filter($title, static fn (mixed $value): bool => is_string($value) && trim($value) !== '') !== []) {
            return $title;
        }

        $names = [];

        foreach ((array) config('notifications.localization.supported', ['ar', 'en']) as $locale) {
            if (is_string($locale) && $locale !== '') {
                $names[$locale] = (string) __('sessions::messages.untitled_session', [], $locale);
            }
        }

        return $names;
    }

    /**
     * رابط المعلم يقصد صفحة الحصة داخل النظام لا الفصل مباشرة — الدخول
     * بالحساب هو ما يجعل النظام يحتسب حضوره ويقيّد مستحقات الحصة له.
     */
    private function teacherUrl(Session $session): string
    {
        $route = (string) config('scheduling.reminder_dispatch.teacher_session_route');

        return route($route, ['session' => (string) $session->getKey()]);
    }

    /**
     * رابط الطالب الموقّع ينتهي بانتهاء نافذة الحصة، ويعيد المتحكّم فرض
     * الحالة والنافذة والتجميد قبل أي توجيه للمزوّد.
     */
    private function studentUrl(Session $session, string $participantId): ?string
    {
        if ((bool) config('virtual-classroom.student_link.enabled') !== true) {
            return null;
        }

        $afterMinutes = max(0, (int) config('virtual-classroom.join_window.after_minutes'));

        return URL::temporarySignedRoute(
            (string) config('scheduling.reminder_dispatch.student_link_route'),
            $session->scheduled_end->addMinutes($afterMinutes),
            ['session' => (string) $session->getKey(), 'participant' => $participantId],
        );
    }
}
