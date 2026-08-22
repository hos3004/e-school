<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Events\PostponementRequested;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * طلب تأجيل حصة.
 *
 * قرار العميل: التأجيل هو المسار الوحيد المتاح للطالب لتغيير موعد حصة —
 * زر الإلغاء مطفأ. ولذلك هذا الإجراء هو بوابة الطالب الأساسية.
 *
 * الحراس هنا ثلاثة: المهلة، وحالة الحصة، والحد الشهري.
 */
final readonly class RequestPostponement
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    /**
     * @param array{reason?: string|null} $data
     */
    public function execute(
        string $sessionId,
        string $requestedBy,
        string $studentProfileId,
        CarbonImmutable $proposedStart,
        array $data = [],
    ): PostponementRequest {
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->whereNull('deleted_at')
            ->first(['id', 'status', 'scheduled_start']);

        if ($session === null) {
            throw BusinessRuleViolation::make(
                'session.not_found',
                'scheduling::errors.session_not_found',
            );
        }

        $this->assertSessionIsPostponable((string) $session->status);
        $this->assertNoticeMet(CarbonImmutable::parse($session->scheduled_start));
        $this->assertNoPendingRequest($sessionId);
        $this->assertMonthlyLimitNotExceeded($studentProfileId);
        $this->assertProposedStartIsInFuture($proposedStart);

        $slaHours = (int) config('scheduling.postponement.teacher_response_sla_hours', 12);

        $request = $this->transaction->run(function () use (
            $sessionId,
            $requestedBy,
            $studentProfileId,
            $proposedStart,
            $data,
            $slaHours,
        ): PostponementRequest {
            $request = new PostponementRequest;

            $request->fill([
                'session_id' => $sessionId,
                'requested_by' => $requestedBy,
                'requested_for_student_id' => $studentProfileId,
                'status' => PostponementStatus::Requested,
                'proposed_start' => $proposedStart,
                'reason' => $data['reason'] ?? null,
                'expires_at' => CarbonImmutable::now('UTC')->addHours($slaHours),
            ]);

            $request->save();

            return $request;
        });

        // إشعار المعلم والإدارة يتم عبر مستمعي هذا الحدث — لا نستدعي قناة إرسال هنا.
        event(new PostponementRequested(
            requestId: (string) $request->getKey(),
            sessionId: $sessionId,
            studentProfileId: $studentProfileId,
            proposedStart: $proposedStart->toIso8601String(),
            actorId: $requestedBy,
        ));

        return $request;
    }

    /**
     * الحصة المنتهية أو الملغاة أو المؤجَّلة سلفًا لا تُؤجَّل.
     */
    private function assertSessionIsPostponable(string $status): void
    {
        $open = ['scheduled', 'confirmed'];

        if (!in_array($status, $open, true)) {
            throw BusinessRuleViolation::make(
                'session.not_postponable',
                'scheduling::errors.session_not_postponable',
                ['status' => $status],
            );
        }
    }

    /**
     * مهلة التأجيل — ربع ساعة قبل الموعد في الإعداد الحالي.
     * ما دونها يُعامل تغيّبًا، لا تأجيلًا.
     */
    private function assertNoticeMet(CarbonImmutable $scheduledStart): void
    {
        $required = (int) config('scheduling.notice.postponement_minutes', 15);
        $actual = (int) CarbonImmutable::now('UTC')->diffInMinutes($scheduledStart, false);

        if ($actual < $required) {
            throw BusinessRuleViolation::make(
                'postponement.notice_not_met',
                'scheduling::errors.postponement_notice_not_met',
                ['required' => $required, 'actual' => max($actual, 0)],
            );
        }
    }

    private function assertNoPendingRequest(string $sessionId): void
    {
        $pending = PostponementRequest::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', [
                PostponementStatus::Requested->value,
                PostponementStatus::AlternativeProposed->value,
            ])
            ->exists();

        if ($pending) {
            throw BusinessRuleViolation::make(
                'postponement.already_pending',
                'scheduling::errors.postponement_already_pending',
            );
        }
    }

    /**
     * تجاوز الحد الشهري لا يمنع الطلب — الإدارة هي من تبتّ فيه بدل المعلم.
     * لكن حتى ذلك الحين نرفضه من مسار الطالب المباشر.
     */
    private function assertMonthlyLimitNotExceeded(string $studentProfileId): void
    {
        $max = (int) config('scheduling.postponement.max_per_student_per_month', 4);

        if ($max === 0) {
            return;
        }

        $now = CarbonImmutable::now('UTC');

        $used = PostponementRequest::query()
            ->where('requested_for_student_id', $studentProfileId)
            ->whereNotIn('status', [
                PostponementStatus::Rejected->value,
                PostponementStatus::Withdrawn->value,
            ])
            ->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])
            ->count();

        if ($used >= $max) {
            throw BusinessRuleViolation::make(
                'postponement.monthly_limit_reached',
                'scheduling::errors.postponement_monthly_limit',
                ['max' => $max],
            );
        }
    }

    private function assertProposedStartIsInFuture(CarbonImmutable $proposedStart): void
    {
        if ($proposedStart->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            throw BusinessRuleViolation::make(
                'postponement.proposed_start_in_past',
                'scheduling::errors.proposed_start_in_past',
            );
        }
    }
}
