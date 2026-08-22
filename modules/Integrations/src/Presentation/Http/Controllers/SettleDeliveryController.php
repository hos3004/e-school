<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\SettleWebhookDeliveryAction;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Modules\Integrations\Presentation\Http\Requests\SettleDeliveryRequest;
use Modules\Integrations\Presentation\Http\Resources\IntegrationWebhookDeliveryResource;

/**
 * تسوية نتيجة محاولة إيصال: نجاح أو فشل.
 */
final class SettleDeliveryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SettleWebhookDeliveryAction $action,
    ) {}

    public function __invoke(SettleDeliveryRequest $request, string $delivery): IntegrationWebhookDeliveryResource
    {
        $deliveryModel = IntegrationWebhookDelivery::query()->findOrFail($delivery);

        $this->authorize('view', $deliveryModel);

        $settled = $this->action->execute(
            delivery: $deliveryModel,
            success: (bool) $request->boolean('success'),
            responseCode: $request->filled('response_code') ? (int) $request->input('response_code') : null,
            responseBody: $request->input('response_body'),
        );

        return new IntegrationWebhookDeliveryResource($settled);
    }
}
