<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Events\ReactivationRequested;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تقديم طلب إعادة تفعيل لتسجيل مجمّد.
 *
 * الحدود كلها من config('discipline.reactivation'):
 *  - requires_request / max_attempts / cooldown_days_between_attempts.
 */
final readonly class RequestReactivationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  organization_id · enrollment_id ·
     *                                      student_statement — بعد تحقّق FormRequest
     */
    public function execute(array $data): ReactivationRequest
    {
        $this->assertNoOpenRequest((string) $data['enrollment_id']);
        $this->assertAttemptsAvailable((string) $data['enrollment_id']);
        $this->assertCooldownPassed((string) $data['enrollment_id']);

        $request = $this->transaction->run(function () use ($data): ReactivationRequest {
            $attemptNumber = 1 + (int) ReactivationRequest::query()
                ->where('enrollment_id', $data['enrollment_id'])
                ->whereIn('status', [
                    ReactivationStatus::Approved->value,
                    ReactivationStatus::Rejected->value,
                    ReactivationStatus::Cancelled->value,
                ])
                ->count();

            $reactivation = new ReactivationRequest;
            $reactivation->fill([
                ...$data,
                'status' => ReactivationStatus::Pending,
                'attempt_number' => $attemptNumber,
                'requested_by' => (string) auth()->id(),
                'assessment_attempt_id' => null,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'decision_note' => null,
            ]);
            $reactivation->save();

            return $reactivation;
        });

        $this->events->dispatch(new ReactivationRequested(
            reactivationRequestId: (string) $request->getKey(),
            organizationId: (string) $request->organization_id,
            enrollmentId: (string) $request->enrollment_id,
            attemptNumber: (int) $request->attempt_number,
        ));

        return $request;
    }

    private function assertNoOpenRequest(string $enrollmentId): void
    {
        $open = ReactivationRequest::query()
            ->where('enrollment_id', $enrollmentId)
            ->open()
            ->exists();

        if ($open) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_open_exists',
                'discipline::errors.reactivation_open_exists',
            );
        }
    }

    private function assertAttemptsAvailable(string $enrollmentId): void
    {
        $maxAttempts = (int) config('discipline.reactivation.max_attempts', 1);

        $closed = (int) ReactivationRequest::query()
            ->where('enrollment_id', $enrollmentId)
            ->whereNot('status', ReactivationStatus::Pending->value)
            ->count();

        if ($closed >= $maxAttempts) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_max_attempts',
                'discipline::errors.reactivation_max_attempts',
                ['max_attempts' => $maxAttempts],
            );
        }
    }

    private function assertCooldownPassed(string $enrollmentId): void
    {
        $cooldownDays = (int) config('discipline.reactivation.cooldown_days_between_attempts', 0);

        if ($cooldownDays < 1) {
            return;
        }

        $lastDecision = ReactivationRequest::query()
            ->where('enrollment_id', $enrollmentId)
            ->whereNotNull('reviewed_at')
            ->orderByDesc('reviewed_at')
            ->value('reviewed_at');

        if ($lastDecision === null) {
            return;
        }

        $cooldownEndsAt = CarbonImmutable::parse($lastDecision, 'UTC')->addDays($cooldownDays);

        if (CarbonImmutable::now('UTC')->lt($cooldownEndsAt)) {
            throw BusinessRuleViolation::make(
                'discipline.reactivation_cooldown',
                'discipline::errors.reactivation_cooldown',
                ['days' => $cooldownDays],
            );
        }
    }
}
