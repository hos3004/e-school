<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Domain\Contracts\SessionClosureBacklogQuery;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\ValueObjects\SessionClosureBacklog;

final readonly class SessionClosureBacklogQueryService implements SessionClosureBacklogQuery
{
    public function cutoff(): CarbonImmutable
    {
        /*
         * المهلة تمنع وسم حصة انتهت قبل دقائق بأنها متروكة: المعلم قد يكون
         * بصدد رفع التقرير. رقمها في الإعدادات لا هنا.
         */
        return CarbonImmutable::now('UTC')
            ->subMinutes(max(0, (int) config('scheduling.closure_backlog.after_minutes')));
    }

    public function backlog(string $organizationId, CarbonImmutable $cutoff): SessionClosureBacklog
    {
        return $this->summarize($this->backlogQuery($organizationId, $cutoff), $cutoff);
    }

    public function awaitingReview(string $organizationId): SessionClosureBacklog
    {
        $query = Session::query()
            ->forOrganization($organizationId)
            ->where('status', SessionStatus::AwaitingReview->value);

        return $this->summarize($query, CarbonImmutable::now('UTC'));
    }

    public function backlogSessionIds(string $organizationId, CarbonImmutable $cutoff): array
    {
        return $this->ids($this->backlogQuery($organizationId, $cutoff));
    }

    public function awaitingReviewSessionIds(string $organizationId): array
    {
        return $this->ids(
            Session::query()
                ->forOrganization($organizationId)
                ->where('status', SessionStatus::AwaitingReview->value),
        );
    }

    public function pastSessionIdsInWindow(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
    ): array {
        $until = $untilUtcExclusive->greaterThan(CarbonImmutable::now('UTC'))
            ? CarbonImmutable::now('UTC')
            : $untilUtcExclusive;

        return $this->ids(
            Session::query()
                ->forOrganization($organizationId)
                ->where('status', '!=', SessionStatus::Superseded->value)
                ->where('scheduled_end', '>=', $fromUtc)
                ->where('scheduled_end', '<', $until),
        );
    }

    public function countActiveBetween(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
    ): int {
        return Session::query()
            ->forOrganization($organizationId)
            ->whereNotIn('status', [
                SessionStatus::Superseded->value,
                SessionStatus::CancelledByStudent->value,
                SessionStatus::CancelledByTeacher->value,
                SessionStatus::CancelledBySchool->value,
            ])
            ->where('scheduled_start', '>=', $fromUtc)
            ->where('scheduled_start', '<', $untilUtcExclusive)
            ->count();
    }

    /** @return Builder<Session> */
    private function backlogQuery(string $organizationId, CarbonImmutable $cutoff): Builder
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value])
            ->where('scheduled_end', '<', $cutoff);
    }

    /**
     * @param Builder<Session> $query
     * @return list<string>
     */
    private function ids(Builder $query): array
    {
        return $query
            ->orderBy('scheduled_end')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @param Builder<Session> $query */
    private function summarize(Builder $query, CarbonImmutable $cutoff): SessionClosureBacklog
    {
        /*
         * تجميعات على نفس الشرط بدل تحميل الصفوف: العدّاد يجب أن يكبر مع
         * التراكم لا أن يصمت عند حد صفحة.
         */
        $byTeacher = (clone $query)
            ->selectRaw('staff_profile_id, count(*) as total')
            ->groupBy('staff_profile_id')
            ->pluck('total', 'staff_profile_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $oldest = (clone $query)->min('scheduled_end');

        $olderThanAWeek = (clone $query)
            ->where('scheduled_end', '<', $cutoff->subWeek())
            ->count();

        return new SessionClosureBacklog(
            total: array_sum($byTeacher),
            byTeacher: $byTeacher,
            oldestScheduledEnd: $oldest === null ? null : CarbonImmutable::parse((string) $oldest)->toIso8601String(),
            olderThanAWeek: $olderThanAWeek,
        );
    }
}
