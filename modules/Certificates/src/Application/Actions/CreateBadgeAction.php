<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Events\BadgeCreated;
use Modules\Certificates\Domain\Models\Badge;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إضافة شارة إلى كتالوج المؤسسة.
 *
 * الرمز (code) فريد عالميًا بقيود قاعدة البيانات — نتحقق مسبقًا لنعطي
 * خطأ عمل مفهومًا بدل استثناء قيد.
 */
final readonly class CreateBadgeAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?string $actorId = null): Badge
    {
        $tier = $data['tier'] instanceof BadgeTier
            ? $data['tier']
            : BadgeTier::from((string) $data['tier']);

        if (Badge::query()->where('code', $data['code'])->exists()) {
            throw BusinessRuleViolation::make(
                'certificates.badge_code_taken',
                'certificates::errors.badge_code_taken',
                ['code' => (string) $data['code']],
            );
        }

        /** @var array{0: Badge, 1: BadgeCreated} $result */
        $result = $this->transaction->run(function () use ($data, $tier, $actorId): array {
            $badge = new Badge;
            $badge->fill([
                ...$data,
                'tier' => $tier->value,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            $badge->save();

            return [$badge, new BadgeCreated(
                badgeId: (string) $badge->getKey(),
                organizationId: $badge->organization_id,
                code: $badge->code,
                tier: $badge->tier->value,
                isActive: $badge->is_active,
                actorId: $actorId,
            )];
        });

        [$badge, $event] = $result;

        $this->events->dispatch($event);

        return $badge;
    }
}
