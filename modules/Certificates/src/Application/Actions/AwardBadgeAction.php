<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Events\BadgeAwarded;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\BadgeAward;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * منح شارة لمستخدم.
 *
 * قواعد العمل:
 *  - الشارة يجب أن تخص نفس المؤسسة وأن تكون مفعّلة.
 *  - لا منح مكرر: الشارة الواحدة تُمنح للمستخدم مرة واحدة فقط —
 *    قيود المنح لصيقة (append-only) ولا تُعدَّل ولا تُحذف.
 */
final readonly class AwardBadgeAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Badge $badge, string $userId, ?string $reason = null, ?string $actorId = null): BadgeAward
    {
        if (!$badge->is_active) {
            throw BusinessRuleViolation::make(
                'certificates.badge_inactive',
                'certificates::errors.badge_inactive',
            );
        }

        $alreadyAwarded = BadgeAward::query()
            ->where('badge_id', $badge->getKey())
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyAwarded) {
            throw BusinessRuleViolation::make(
                'certificates.badge_already_awarded',
                'certificates::errors.badge_already_awarded',
            );
        }

        $awardedAt = CarbonImmutable::now('UTC');
        $awardedById = $actorId ?? (string) auth()->id();

        /** @var array{0: BadgeAward, 1: BadgeAwarded} $result */
        $result = $this->transaction->run(function () use ($badge, $userId, $reason, $awardedAt, $awardedById, $actorId): array {
            $award = new BadgeAward;
            $award->fill([
                'organization_id' => $badge->organization_id,
                'badge_id' => (string) $badge->getKey(),
                'user_id' => $userId,
                'awarded_by' => $awardedById !== '' ? $awardedById : null,
                'reason' => $reason,
                'awarded_at' => $awardedAt,
            ]);
            $award->save();

            return [$award, new BadgeAwarded(
                awardId: (string) $award->getKey(),
                organizationId: $award->organization_id,
                badgeId: $award->badge_id,
                badgeCode: $badge->code,
                userId: $award->user_id,
                awardedById: $award->awarded_by,
                reason: $award->reason,
                awardedAt: $awardedAt->toIso8601String(),
                actorId: $actorId,
            )];
        });

        [$award, $event] = $result;

        $this->events->dispatch($event);

        return $award;
    }
}
