<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Throwable;

/**
 * ينقل الحصص الجارية بعد موعد نهايتها بمهلة الإعدادات إلى «بانتظار المراجعة».
 *
 * بدونه تبقى الحصة in_progress فلا تظهر في كشوف الحضور المعلّقة. المهلة لا تقل
 * عن نافذة الدخول المتأخر حتى لا تُغلق حصة ما زال دخولها مسموحًا. المنفّذ في
 * سجل الحالات هو من بدأ الحصة (القيد يشترط مستخدمًا)، والتدقيق يعلّمه «system».
 */
final class EndElapsedSessions extends Command
{
    protected $signature = 'sessions:end-elapsed';

    protected $description = 'Move in-progress sessions past their scheduled end to awaiting review';

    public function handle(EndSessionAction $end): int
    {
        $cutoff = CarbonImmutable::now('UTC')
            ->subMinutes(max(0, (int) config('scheduling.auto_end.after_minutes')));
        $sessions = Session::query()
            ->where('status', SessionStatus::InProgress)
            ->where('scheduled_end', '<', $cutoff)
            ->orderBy('scheduled_end')
            ->limit(max(1, (int) config('scheduling.auto_end.batch_size')))
            ->get();

        $ended = 0;
        foreach ($sessions as $session) {
            $actorId = SessionStatusHistory::query()
                ->where('session_id', $session->id)
                ->where('to_status', SessionStatus::InProgress->value)
                ->orderByDesc('changed_at')
                ->value('changed_by');

            if (!is_string($actorId) || $actorId === '') {
                Log::warning('sessions.auto_end_skipped', ['session_id' => $session->id]);

                continue;
            }

            try {
                $end->execute(
                    $session,
                    $actorId,
                    (string) __('sessions::messages.ended_after_schedule'),
                    actorType: 'system',
                );
                $ended++;
            } catch (Throwable $exception) {
                Log::warning('sessions.auto_end_failed', [
                    'session_id' => $session->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->components->info(__('sessions::messages.auto_end_summary', ['count' => $ended]));

        return self::SUCCESS;
    }
}
