<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementScheduled;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Scheduling\Domain\Services\ConflictDetector;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\TimeRange;

/**
 * اعتماد طلب التأجيل وإنشاء حصة التلافي.
 *
 * هنا تلتقي ثلاث قواعد من العميل:
 *  - التأجيل — وحده — يقابله حصة تلافي.
 *  - حصة التلافي مرتبطة بأصلها عبر makeup_for_session_id.
 *  - مستحق المعلم عن الحصة الأصلية يبقى مؤجَّلًا حتى تُقام حصة التلافي،
 *    وهو ما يتكفّل به موديول Payroll عند سماعه للحدث المنشور في النهاية.
 */
final readonly class ApprovePostponement
{
    public function __construct(
        private Transaction $transaction,
        private ConflictDetector $conflicts,
    ) {}

    public function execute(
        string $requestId,
        string $approvedBy,
        ?CarbonImmutable $agreedStart = null,
    ): PostponementRequest {
        $request = PostponementRequest::query()->findOrFail($requestId);

        $this->assertTransitionAllowed($request->status, PostponementStatus::Scheduled);

        $start = $agreedStart
            ?? $request->proposed_by_teacher_start
            ?? $request->proposed_start;

        if (!$start instanceof CarbonImmutable) {
            $start = CarbonImmutable::parse((string) $start);
        }

        $original = DB::table('sessions')
            ->where('id', $request->session_id)
            ->first(['id', 'organization_id', 'group_id', 'course_id', 'staff_profile_id',
                'session_type', 'scheduled_start', 'scheduled_end', 'title']);

        if ($original === null) {
            throw BusinessRuleViolation::make(
                'session.not_found',
                'scheduling::errors.session_not_found',
            );
        }

        $durationMinutes = (int) CarbonImmutable::parse($original->scheduled_start)
            ->diffInMinutes(CarbonImmutable::parse($original->scheduled_end));

        $range = TimeRange::fromDuration($start, $durationMinutes);

        $this->assertWithinMakeupWindow(CarbonImmutable::parse($original->scheduled_start), $start);
        $this->assertNoConflict($range, (string) $original->staff_profile_id, $original->group_id);

        $makeupId = (string) Str::ulid();

        $updated = $this->transaction->run(function () use (
            $request, $original, $range, $makeupId, $approvedBy, $start,
        ): PostponementRequest {
            DB::table('sessions')->insert([
                'id' => $makeupId,
                'organization_id' => $original->organization_id,
                'group_id' => $original->group_id,
                'course_id' => $original->course_id,
                'staff_profile_id' => $original->staff_profile_id,
                'makeup_for_session_id' => $original->id,
                'session_type' => 'makeup',
                'status' => 'scheduled',
                'scheduled_start' => $range->start,
                'scheduled_end' => $range->end,
                'title' => $original->title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // الحصة الأصلية تصبح مؤجَّلة — وهي حالة نهائية لا تُقام بعدها.
            DB::table('sessions')
                ->where('id', $original->id)
                ->update(['status' => 'postponed', 'updated_at' => now()]);

            $request->fill([
                'status' => PostponementStatus::Scheduled,
                'agreed_start' => $start,
                'makeup_session_id' => $makeupId,
                'responded_by' => $approvedBy,
                'responded_at' => CarbonImmutable::now('UTC'),
            ]);

            $request->save();

            return $request;
        });

        event(new PostponementScheduled(
            requestId: (string) $updated->getKey(),
            sessionId: (string) $original->id,
            makeupSessionId: $makeupId,
            agreedStart: $start->toIso8601String(),
            actorId: $approvedBy,
        ));

        return $updated;
    }

    private function assertTransitionAllowed(
        PostponementStatus $from,
        PostponementStatus $to,
    ): void {
        if (!$from->canTransitionTo($to)) {
            throw BusinessRuleViolation::make(
                'postponement.invalid_transition',
                'scheduling::errors.postponement_invalid_transition',
                ['from' => $from->value, 'to' => $to->value],
            );
        }
    }

    /**
     * حصة التلافي يجب أن تُعقد خلال نافذة محددة من الموعد الأصلي،
     * وإلا فقد التأجيل معناه وتحوّل إلى تأجيل مفتوح.
     */
    private function assertWithinMakeupWindow(
        CarbonImmutable $originalStart,
        CarbonImmutable $makeupStart,
    ): void {
        $days = (int) config('scheduling.postponement.makeup_window_days', 30);
        $deadline = $originalStart->addDays($days);

        if ($makeupStart->greaterThan($deadline)) {
            throw BusinessRuleViolation::make(
                'postponement.outside_makeup_window',
                'scheduling::errors.outside_makeup_window',
                ['days' => $days],
            );
        }
    }

    private function assertNoConflict(
        TimeRange $range,
        string $staffProfileId,
        ?string $groupId,
    ): void {
        $conflicts = $this->conflicts->conflictsFor(
            range: $range,
            staffProfileId: $staffProfileId,
            groupId: $groupId === null ? null : (string) $groupId,
        );

        if ($conflicts !== []) {
            throw BusinessRuleViolation::make(
                'scheduling.conflict_detected',
                'scheduling::errors.conflict_detected',
                ['count' => count($conflicts)],
            );
        }
    }
}
