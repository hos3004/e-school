<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Events\BadgeUpdated;
use Modules\Certificates\Domain\Models\Badge;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعديل شارة قائمة — الاسم أو الوصف أو الأيقونة أو التفعيل.
 *
 * الرمز (code) والتسلسل لا يتغيران بعد الإنشاء لأنهما مرجع في السجلات
 * والأحداث الصادرة.
 */
final readonly class UpdateBadgeAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  الحقول المسموح تعديلها فقط
     */
    public function execute(Badge $badge, array $data, ?string $actorId = null): Badge
    {
        $allowed = array_intersect_key($data, array_flip([
            'name',
            'description',
            'icon_path',
            'tier',
            'is_active',
        ]));

        if ($allowed === []) {
            throw BusinessRuleViolation::make(
                'certificates.badge_no_changes',
                'certificates::errors.badge_no_changes',
            );
        }

        /** @var array{0: Badge, 1: BadgeUpdated} $result */
        $result = $this->transaction->run(function () use ($badge, $allowed, $actorId): array {
            $badge->fill($allowed)->save();

            return [$badge, new BadgeUpdated(
                badgeId: (string) $badge->getKey(),
                organizationId: $badge->organization_id,
                code: $badge->code,
                tier: $badge->tier->value,
                isActive: $badge->is_active,
                changes: array_map(
                    fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
                    $allowed,
                ),
                actorId: $actorId,
            )];
        });

        [$badge, $event] = $result;

        $this->events->dispatch($event);

        return $badge;
    }

    /**
     * المستويات المسموح بها — من الـ Enum لا نصوص حرة.
     *
     * @return list<string>
     */
    public static function allowedTiers(): array
    {
        return array_map(fn (BadgeTier $tier): string => $tier->value, BadgeTier::cases());
    }
}
