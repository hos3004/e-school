<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\RecordWebhookDeliveryAction;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Modules\Integrations\Presentation\Http\Requests\RecordDeliveryRequest;
use Modules\Integrations\Presentation\Http\Resources\IntegrationWebhookDeliveryResource;

/**
 * تسجيل إيصال Webhook جديد في الطابور.
 */
final class RecordDeliveryController extends Controller
{
    public function __construct(
        private readonly RecordWebhookDeliveryAction $action,
    ) {}

    public function __invoke(RecordDeliveryRequest $request): IntegrationWebhookDeliveryResource
    {
        $delivery = $this->action->execute(
            connectionId: (string) $request->input('connection_id'),
            eventType: (string) $request->input('event_type'),
            direction: WebhookDirection::from((string) $request->input('direction', WebhookDirection::Outbound->value)),
            payload: (array) $request->input('payload', []),
        );

        return new IntegrationWebhookDeliveryResource($delivery);
    }
}
