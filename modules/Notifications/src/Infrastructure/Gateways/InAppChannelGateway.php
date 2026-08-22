<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Gateways;

use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * بوابة الإشعار داخل التطبيق.
 *
 * سطر الـoutbox هو سجل الإشعار الذي تقرؤه واجهة الجرس؛ لذلك لا تحتاج
 * هذه القناة إلى مزوّد خارجي. نجاحها يعني أن السطر أصبح متاحًا للواجهة.
 */
final class InAppChannelGateway implements ChannelGateway
{
    public function send(GatewayMessage $message): GatewayResult
    {
        if ($message->channel !== Channel::InApp->value) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.gateway_channel_mismatch', [
                    'expected' => Channel::InApp->label(),
                    'actual' => $message->channel,
                ]),
                false,
            );
        }

        return GatewayResult::accepted([
            'driver' => Channel::InApp->value,
            'stored' => true,
            'outbox_id' => $message->messageId,
            'user_id' => $message->recipientId,
        ]);
    }
}
