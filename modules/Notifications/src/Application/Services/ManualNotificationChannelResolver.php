<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Notifications\Domain\Enums\Channel;

/** لا تعرض الواجهة قناة ما لم تكن مفعّلة ولها Gateway فعلي مسجّل. */
final class ManualNotificationChannelResolver
{
    /** @return list<Channel> */
    public function available(): array
    {
        return array_values(array_filter(
            Channel::cases(),
            function (Channel $channel): bool {
                $settings = config('notifications.channels.'.$channel->value);

                if (!is_array($settings) || !(bool) ($settings['enabled'] ?? false)) {
                    return false;
                }

                $gateway = $settings['gateway'] ?? null;

                return is_string($gateway)
                    && $gateway !== ''
                    && class_exists($gateway)
                    && is_a($gateway, ChannelGateway::class, true);
            },
        ));
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->available())
            ->mapWithKeys(static fn (Channel $channel): array => [$channel->value => $channel->label()])
            ->all();
    }

    public function allows(Channel $channel): bool
    {
        return in_array($channel, $this->available(), true);
    }
}
