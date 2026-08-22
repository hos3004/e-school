<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\EstablishConnectionAction;
use Modules\Integrations\Presentation\Http\Requests\StoreConnectionRequest;
use Modules\Integrations\Presentation\Http\Resources\IntegrationConnectionResource;

/**
 * إنشاء اتصال جديد بمزوّد خارجي.
 */
final class StoreConnectionController extends Controller
{
    public function __construct(
        private readonly EstablishConnectionAction $action,
    ) {}

    public function __invoke(StoreConnectionRequest $request): IntegrationConnectionResource
    {
        $connection = $this->action->execute(
            organizationId: (string) $request->input('organization_id'),
            providerId: (string) $request->input('provider_id'),
            credentials: (array) $request->input('credentials', []),
            settings: (array) $request->input('settings', []),
        );

        return new IntegrationConnectionResource($connection);
    }
}
