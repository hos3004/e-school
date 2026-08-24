<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Notifications\Domain\Models\NotificationCategorySetting;

/**
 * يحسم إعدادات فئة الإشعار الفعّالة: override المؤسسة إن وُجد، وإلا افتراضي
 * config/notifications.php. هذا هو المصدر الوحيد الذي يقرأه محرّك الإرسال،
 * فلا يقرأ config الفئات مباشرة بعد اليوم.
 *
 * غياب صف المؤسسة يعني السلوك الافتراضي حرفيًا — لا تغيّر لأي مؤسسة لم تُخصّص.
 */
final class NotificationCategorySettingsResolver
{
    /** @var array<string, array<string, array{channels: list<string>, is_critical: bool, respects_quiet_hours: bool}>> */
    private array $cache = [];

    /**
     * قنوات الفئة الفعّالة (قبل تقاطعها مع القنوات المفعّلة عالميًا وتفضيلات المستلم).
     *
     * @return list<string>
     */
    public function channels(string $organizationId, string $category): array
    {
        $override = $this->override($organizationId, $category);

        if ($override !== null) {
            return $override['channels'];
        }

        return array_values(array_map(
            static fn (mixed $channel): string => (string) $channel,
            (array) config('notifications.categories.'.$category.'.channels', []),
        ));
    }

    public function isCritical(string $organizationId, string $category): bool
    {
        $override = $this->override($organizationId, $category);

        if ($override !== null) {
            return $override['is_critical'];
        }

        return (bool) config('notifications.categories.'.$category.'.critical', false);
    }

    public function respectsQuietHours(string $organizationId, string $category): bool
    {
        $override = $this->override($organizationId, $category);

        if ($override !== null) {
            return $override['respects_quiet_hours'];
        }

        return (bool) config('notifications.categories.'.$category.'.respects_quiet_hours', true);
    }

    /**
     * @return array{channels: list<string>, is_critical: bool, respects_quiet_hours: bool}|null
     */
    private function override(string $organizationId, string $category): ?array
    {
        return $this->settingsFor($organizationId)[$category] ?? null;
    }

    /**
     * @return array<string, array{channels: list<string>, is_critical: bool, respects_quiet_hours: bool}>
     */
    private function settingsFor(string $organizationId): array
    {
        if (isset($this->cache[$organizationId])) {
            return $this->cache[$organizationId];
        }

        $settings = NotificationCategorySetting::query()
            ->forOrganization($organizationId)
            ->get()
            ->mapWithKeys(static fn (NotificationCategorySetting $row): array => [
                $row->category => [
                    'channels' => array_values(array_map(
                        static fn (mixed $channel): string => (string) $channel,
                        (array) $row->channels,
                    )),
                    'is_critical' => $row->is_critical,
                    'respects_quiet_hours' => $row->respects_quiet_hours,
                ],
            ])
            ->all();

        return $this->cache[$organizationId] = $settings;
    }
}
