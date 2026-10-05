<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\MarkNoShowAction;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Throwable;

/**
 * يقفل الحصص المنتظرة المراجعة التي اكتمل دليلها، فتُنشأ قيود المستحقات.
 *
 * كان هذا هو الطرف المفقود من السلسلة: `sessions:end-elapsed` ينقل الحصة إلى
 * `awaiting_review` ثم لا شيء ينقلها إلى حالة نهائية، و`Payroll` لا يستمع إلا
 * للحالات النهائية. فكان المعلم يُدرّس ولا يظهر له مستحق ولا عدّاد.
 *
 * ما لا يفعله هذا الأمر متعمَّد بقدر ما يفعله: لا يعتمد حصة ناقصة الدليل.
 * قيدة المستحقات append-only، فاعتماد حصة لم تُرصد لا يُصحَّح إلا بقيدة تسوية.
 * الناقص يبقى في شاشة الاعتماد الإداري ليقرره إنسان بسبب مكتوب.
 *
 * النتيجة تُشتق من الحضور المرصود لا من افتراض: إذا كان كل المشاركين متغيّبين
 * فالحالة `no_show` لا `completed` — الأجر واحد في الحالتين حسب
 * `config/payroll.php`، لكن `completed` كانت ستُثبت للطالب حضورًا لم يحدث
 * وتحرمه من احتساب المخالفة.
 */
final class FinalizeDueSessions extends Command
{
    protected $signature = 'sessions:finalize-due';

    protected $description = 'Finalize awaiting-review sessions whose report and attendance are complete';

    public function handle(
        SessionParticipantAdministrationQueries $participants,
        AttendanceAdministrationQueries $attendance,
        SessionReportStatusQueries $reports,
        CompleteSessionAction $complete,
        MarkNoShowAction $noShow,
    ): int {
        if (config('scheduling.auto_finalize.enabled') !== true) {
            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now('UTC')
            ->subMinutes(max(0, (int) config('scheduling.auto_finalize.after_minutes')));

        $sessions = Session::query()
            ->where('status', SessionStatus::AwaitingReview)
            ->where('scheduled_end', '<', $cutoff)
            ->orderBy('scheduled_end')
            ->limit(max(1, (int) config('scheduling.auto_finalize.batch_size')))
            ->get();

        if ($sessions->isEmpty()) {
            $this->components->info(__('sessions::messages.auto_finalize_summary', ['count' => 0]));

            return self::SUCCESS;
        }

        $sessionIds = $sessions->map(static fn (Session $session): string => (string) $session->getKey())->all();
        $reportStates = $reports->forSessions($sessionIds);
        $finalized = 0;

        foreach ($sessions as $session) {
            $sessionId = (string) $session->getKey();
            $organizationId = (string) $session->organization_id;

            if ((bool) config('scheduling.auto_finalize.require_report')
                && ($reportStates[$sessionId] ?? null)?->submittedAt === null) {
                continue;
            }

            $rows = $participants->forSession($organizationId, $sessionId);

            /*
             * حصة بلا مشاركين ليست دليلًا على شيء: لا حضور يُرصد ولا غياب.
             * تُترك للمراجعة الإدارية بدل أن تُعتمد على فراغ.
             */
            if ($rows === []) {
                continue;
            }

            $records = $attendance->byParticipantIds(
                $organizationId,
                array_map(static fn ($row): string => $row->id, $rows),
            );

            if ((bool) config('scheduling.auto_finalize.require_attendance')
                && count($records) !== count($rows)) {
                continue;
            }

            $blocking = (array) config('scheduling.auto_finalize.blocking_attendance_statuses');
            $notHeld = array_filter(
                $records,
                static fn ($record): bool => in_array($record->status, $blocking, true),
            );

            /*
             * «لم تُقَم» ليست دليلًا ناقصًا بل دليل نفي: تُترك للمراجعة
             * الإدارية بدل أن تُقفل مكتملة فتدفع أجرًا عن حصة لم تحدث.
             */
            if ($notHeld !== []) {
                continue;
            }

            $absentStatuses = (array) config('scheduling.auto_finalize.absent_attendance_statuses');
            $allAbsent = $records !== [] && array_reduce(
                array_values($records),
                static fn (bool $carry, $record): bool => $carry && in_array($record->status, $absentStatuses, true),
                true,
            );

            $actorId = $this->actorFor($sessionId);

            if ($actorId === null) {
                Log::warning('sessions.auto_finalize_skipped', ['session_id' => $sessionId]);

                continue;
            }

            try {
                if ($allAbsent) {
                    $noShow->execute(
                        $session,
                        (string) __('sessions::messages.finalized_all_absent'),
                        $actorId,
                        actorType: 'system',
                    );
                } else {
                    $complete->execute(
                        $session,
                        $actorId,
                        (string) __('sessions::messages.finalized_after_review'),
                        actorType: 'system',
                    );
                }
                $finalized++;
            } catch (Throwable $exception) {
                Log::warning('sessions.auto_finalize_failed', [
                    'session_id' => $sessionId,
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->components->info(__('sessions::messages.auto_finalize_summary', ['count' => $finalized]));

        return self::SUCCESS;
    }

    /**
     * المنفّذ في سجل الحالات لا بد أن يكون مستخدمًا حقيقيًا (قيد قاعدة البيانات)،
     * فيُنسب الانتقال لمن أنهى الحصة، ويعلّمه التدقيق «system».
     */
    private function actorFor(string $sessionId): ?string
    {
        $actorId = SessionStatusHistory::query()
            ->where('session_id', $sessionId)
            ->where('to_status', SessionStatus::AwaitingReview->value)
            ->orderByDesc('changed_at')
            ->value('changed_by');

        return is_string($actorId) && $actorId !== '' ? $actorId : null;
    }
}
