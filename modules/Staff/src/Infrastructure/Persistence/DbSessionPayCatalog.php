<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;
use Modules\Staff\Domain\Contracts\SessionPayCatalog;

final class DbSessionPayCatalog implements SessionPayCatalog
{
    public function __construct(private readonly OrganizationSettingQueries $settings) {}

    public function rates(string $organizationId, ?CarbonImmutable $at = null): array
    {
        $history = $this->settings->value($organizationId, (string) config('session_pay.setting_key'));
        if (!is_array($history)) {
            return [];
        }
        $at ??= CarbonImmutable::now('UTC');
        $result = [];
        foreach ($history as $revision) {
            if (CarbonImmutable::parse($revision['effective_from'])->lte($at)) {
                $result = $revision['rates'];
            }
        }

        return $result;
    }

    public function durations(string $organizationId, string $sessionType): array
    {
        $rates = array_filter($this->rates($organizationId), fn (array $rate): bool => $rate['session_type'] === $sessionType);
        if ($rates === []) {
            return array_values((array) config($sessionType === 'individual' ? 'scheduling.individual_session_durations' : 'scheduling.session_durations'));
        }
        $durations = array_values(array_unique(array_column($rates, 'duration_minutes')));
        sort($durations);

        return $durations;
    }
}
