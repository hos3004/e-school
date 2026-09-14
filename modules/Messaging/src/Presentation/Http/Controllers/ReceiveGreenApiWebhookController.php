<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Messaging\Application\Actions\RecordWhatsappInboundAction;
use Modules\Messaging\Presentation\Http\Requests\ReceiveGreenApiWebhookRequest;
use Modules\Notifications\Domain\Contracts\ProviderDeliveryStatusRecorder;

final class ReceiveGreenApiWebhookController extends Controller
{
    public function __invoke(ReceiveGreenApiWebhookRequest $request, RecordWhatsappInboundAction $record, ProviderDeliveryStatusRecorder $statuses, GreenApiConnections $connections): JsonResponse
    {
        $data = $request->validated();
        if ($data['typeWebhook'] === 'stateInstanceChanged') {
            $connections->recordState($request->organizationId(), (string) ($data['stateInstance'] ?? ''));

            return response()->json([]);
        }

        if ($data['typeWebhook'] === 'outgoingMessageStatus') {
            if (($data['sendByApi'] ?? false) === true) {
                $statuses->record(
                    $request->organizationId(),
                    (string) ($data['idMessage'] ?? ''),
                    (string) ($data['status'] ?? ''),
                    (string) ($data['description'] ?? ''),
                );
            }

            return response()->json([]);
        }

        if ($data['typeWebhook'] !== 'incomingMessageReceived') {
            return response()->json([]);
        }

        $chatId = (string) data_get($data, 'senderData.chatId', '');
        $messageId = (string) ($data['idMessage'] ?? '');
        if (!str_ends_with($chatId, '@c.us') || $messageId === '') {
            return response()->json([]);
        }

        $message = (array) ($data['messageData'] ?? []);
        $type = (string) ($message['typeMessage'] ?? '');
        $body = match ($type) {
            'textMessage' => (string) data_get($message, 'textMessageData.textMessage', ''),
            'extendedTextMessage', 'quotedMessage' => (string) data_get($message, 'extendedTextMessageData.text', ''),
            default => (string) data_get($message, 'fileMessageData.caption', ''),
        };
        $media = null;
        if (in_array($type, ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage'], true)) {
            $media = [[
                'type' => $type,
                'mime_type' => (string) data_get($message, 'fileMessageData.mimeType', ''),
                'download_url' => (string) data_get($message, 'fileMessageData.downloadUrl', ''),
                'file_name' => (string) data_get($message, 'fileMessageData.fileName', ''),
            ]];
            if ($body === '') {
                $body = (string) __('messaging::messages.whatsapp_media_received');
            }
        }
        if (trim($body) === '') {
            return response()->json([]);
        }

        $body = mb_substr($body, 0, (int) config('messaging.whatsapp.body_max'));
        $receivedAt = isset($data['timestamp'])
            ? CarbonImmutable::createFromTimestampUTC((int) $data['timestamp'])
            : CarbonImmutable::now('UTC');

        $record->execute(
            organizationId: $request->organizationId(),
            fromPhone: substr($chatId, 0, -5),
            messageId: (string) data_get($data, 'instanceData.idInstance').':'.$chatId.':'.$messageId,
            body: $body,
            receivedAt: $receivedAt,
            media: $media,
        );

        return response()->json([]);
    }
}
