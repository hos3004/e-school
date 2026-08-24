<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;

/**
 * يضمن أن لكل فئة معرّفة في config صفَّ إعداد للمؤسسة — يُنشئ الناقص بالافتراضي
 * دون المساس بما خصّصه الأدمن. عملية idempotent تُستدعى عند فتح شاشة الإعدادات.
 */
final class CategorySettingsSynchronizer
{
    public function ensureForOrganization(string $organizationId): void
    {
        if ($organizationId === '') {
            return;
        }

        /** @var array<string, array<string, mixed>> $categories */
        $categories = (array) config('notifications.categories', []);

        $existing = NotificationCategorySetting::query()
            ->forOrganization($organizationId)
            ->pluck('category')
            ->all();

        foreach ($categories as $category => $definition) {
            if (!is_string($category) || in_array($category, $existing, true)) {
                continue;
            }

            NotificationCategorySetting::query()->create([
                'organization_id' => $organizationId,
                'category' => $category,
                'channels' => $this->defaultChannels($definition),
                'is_critical' => (bool) ($definition['critical'] ?? false),
                'respects_quiet_hours' => (bool) ($definition['respects_quiet_hours'] ?? true),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function defaultChannels(array $definition): array
    {
        $known = Channel::values();

        return array_values(array_filter(
            array_map(
                static fn (mixed $channel): string => (string) $channel,
                (array) ($definition['channels'] ?? []),
            ),
            static fn (string $channel): bool => in_array($channel, $known, true),
        ));
    }
}
