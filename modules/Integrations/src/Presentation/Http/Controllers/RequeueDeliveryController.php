<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\RequeueDeadDeliveryAction;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Modules\Integrations\Presentation\Http\Requests\RequeueDeliveryRequest;
use Modules\Integrations\Presentation\Http\Resources\IntegrationWebhookDeliveryResource;

/**
 * إعادة إحياء إيصال ميت — إعادة يدوية بقرار مسؤول.
 */
final class RequeueDeliveryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RequeueDeadDeliveryAction $action,
    ) {}

    public function __invoke(RequeueDeliveryRequest $request, string $delivery): IntegrationWebhookDeliveryResource
    {
        $deliveryModel = IntegrationWebhookDelivery::query()->findOrFail($delivery);

        $this->authorize('requeue', $deliveryModel);

        $requeued = $this->action->execute($deliveryModel);

        return new IntegrationWebhookDeliveryResource($requeued);
    }
}
