<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Persistence;

use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Throwable;

/**
 * موجّه بوابات القنوات — يحوّل قناة الرسالة إلى تنفيذ البوابة المعلن في
 * config('notifications.channels.<channel>.gateway').
 *
 * اسم الصنف يأتي من الإعداد، ويُشترط أن يحقق العقد العام الذي يملكه
 * Integrations. الرسالة الداخلة DTO بقيم أولية ولا تكشف نموذج Outbox.
 */
final class ConfiguredChannelGateway implements ChannelGateway
{
    public function send(GatewayMessage $message): GatewayResult
    {
        $gatewayClass = config('notifications.channels.'.$message->channel.'.gateway');

        if (!is_string($gatewayClass) || $gatewayClass === '' || !class_exists($gatewayClass)) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.gateway_unconfigured', [
                    'channel' => $message->channel,
                ]),
                false,
            );
        }

        try {
            $gateway = app($gatewayClass);
        } catch (Throwable) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.gateway_unconfigured', [
                    'channel' => $message->channel,
                ]),
                false,
            );
        }

        if (!$gateway instanceof ChannelGateway) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.gateway_unconfigured', [
                    'channel' => $message->channel,
                ]),
                false,
            );
        }

        return $gateway->send($message);
    }
}
