<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Gateways;

use Illuminate\Support\Facades\Log;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Infrastructure\Push\PushMirrorDispatcher;
use Throwable;

/**
 * بوابة الإشعار داخل التطبيق.
 *
 * سطر الـoutbox هو سجل الإشعار الذي تقرؤه واجهة الجرس؛ لذلك لا تحتاج
 * هذه القناة إلى مزوّد خارجي. نجاحها يعني أن السطر أصبح متاحًا للواجهة.
 *
 * تُمرِّر أيضًا نفس هذا المحتوى كإشعار فعلي عبر PushMirrorDispatcher (انظر
 * تعليقها) — push ليست قناة مُهيّأة مستقلة في هذا التصميم، بل صدى لهذه
 * القناة بالذات. فشل التمرير لا يجوز أبدًا أن يُفشل كتابة سطر in_app نفسه،
 * لذلك الاستثناء يُبتلع هنا تمامًا.
 */
final class InAppChannelGateway implements ChannelGateway
{
    public function __construct(private readonly PushMirrorDispatcher $push) {}

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

        try {
            $this->push->dispatch($message);
        } catch (Throwable $error) {
            // إشعار فعلي أفضلية ثانوية دائمًا مقارنة بسطر in_app نفسه —
            // يُسجَّل للتشخيص فقط، ولا يُعاد رميه أبدًا.
            Log::warning('push.mirror.threw', [
                'user_id' => $message->recipientId,
                'event_name' => $message->eventName,
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);
        }

        return GatewayResult::accepted([
            'driver' => Channel::InApp->value,
            'stored' => true,
            'outbox_id' => $message->messageId,
            'user_id' => $message->recipientId,
        ]);
    }
}
