<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Events\NotificationPreferencesUpdated;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Support\Transaction;

/**
 * تحديث تفضيل مستخدم لفئة×قناة — upsert بفعل قيد الفريد على الثلاثية.
 */
final readonly class UpdatePreferenceAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        string $organizationId,
        string $userId,
        string $category,
        Channel $channel,
        bool $enabled,
        ?string $actorId = null,
    ): NotificationPreference {
        $preference = $this->transaction->run(function () use (
            $organizationId,
            $userId,
            $category,
            $channel,
            $enabled,
        ): NotificationPreference {
            /** @var NotificationPreference|null $existing */
            $existing = NotificationPreference::query()
                ->forUser($userId)
                ->forCategoryChannel($category, $channel->value)
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'enabled' => $enabled,
                    'updated_at' => CarbonImmutable::now('UTC'),
                ])->save();

                return $existing;
            }

            return NotificationPreference::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'category' => $category,
                'channel' => $channel->value,
                'enabled' => $enabled,
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
        });

        $this->events->dispatch(new NotificationPreferencesUpdated(
            outboxId: $preference->id,
            organizationId: $preference->organization_id,
            userId: $preference->user_id,
            category: $preference->category,
            channel: $preference->channel,
            enabled: $preference->enabled,
            actorId: $actorId ?? $preference->user_id,
            correlationId: null,
        ));

        return $preference;
    }
}
