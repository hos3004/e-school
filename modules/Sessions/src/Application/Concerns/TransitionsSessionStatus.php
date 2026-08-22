<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionStatusHistory;
use Shared\Support\BusinessRuleViolation;

/**
 * منطق الانتقال بين حالات الحصة مشترك بين كل إجراءات الكتابة.
 *
 * القاعدة: أي انتقال يمر إجباريًا عبر canTransitionTo، ويُسجَّل دائمًا
 * في session_status_history داخل معاملة الإجراء نفسه.
 */
trait TransitionsSessionStatus
{
    /**
     * يُستدعى داخل DB::transaction فقط.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    protected function applyTransition(
        Session $session,
        SessionStatus $to,
        array $attributes = [],
        ?string $reason = null,
        ?string $changedBy = null,
        array $metadata = [],
    ): void {
        $from = $session->status;

        if (! $from->canTransitionTo($to)) {
            throw BusinessRuleViolation::make(
                'sessions.invalid_status_transition',
                'sessions::errors.invalid_transition',
                ['from' => $from->label(), 'to' => $to->label()],
            );
        }

        $session->forceFill([...$attributes, 'status' => $to])->save();

        SessionStatusHistory::query()->create([
            'session_id' => $session->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'changed_by' => $changedBy ?? (string) auth()->id(),
            'changed_at' => CarbonImmutable::now('UTC'),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    protected function guardNotTerminal(Session $session): void
    {
        if ($session->status->isTerminal()) {
            throw BusinessRuleViolation::make(
                'sessions.terminal_status',
                'sessions::errors.terminal_status',
                ['status' => $session->status->label()],
            );
        }
    }
}
