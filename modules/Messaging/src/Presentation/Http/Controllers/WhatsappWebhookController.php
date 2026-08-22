<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Messaging\Application\Actions\RecordWhatsappInboundAction;

/**
 * استقبال رسائل واتساب من مزوّد خارجي (webhook).
 */
final class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly RecordWhatsappInboundAction $action,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = request()->validate([
            'organization_id' => ['required', 'string', 'size:26'],
            'from_phone' => ['required', 'string', 'max:32'],
            'message_id' => ['required', 'string'],
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.whatsapp.body_max'),
            ],
            'media' => ['sometimes', 'array', 'max:'.(int) config('messaging.whatsapp.media_max_items')],
            'received_at' => ['sometimes', 'date'],
            'matched_user_id' => ['nullable', 'string', 'size:26'],
        ]);

        $this->action->execute(
            organizationId: $payload['organization_id'],
            fromPhone: $payload['from_phone'],
            messageId: $payload['message_id'],
            body: $payload['body'],
            media: $payload['media'] ?? null,
            matchedUserId: $payload['matched_user_id'] ?? null,
        );

        return response()->json([], Response::HTTP_ACCEPTED);
    }
}
