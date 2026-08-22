<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Actions\HandleWhatsappInboundAction;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Modules\Messaging\Presentation\Http\Requests\HandleWhatsappInboundRequest;
use Modules\Messaging\Presentation\Http\Resources\WhatsappInboundResource;

/**
 * تعامل موظف مع رسالة واتساب واردة.
 */
final class HandleWhatsappInboundController extends Controller
{
    public function __construct(
        private readonly HandleWhatsappInboundAction $action,
    ) {}

    public function __invoke(HandleWhatsappInboundRequest $request, WhatsappInbound $inbound): WhatsappInboundResource
    {
        Gate::authorize('handle', $inbound);

        $handled = $this->action->execute(
            inbound: $inbound,
            handlerUserId: (string) $request->user()->getAuthIdentifier(),
        );

        return new WhatsappInboundResource($handled);
    }
}
