<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\ActivateConnectionAction;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Presentation\Http\Resources\IntegrationConnectionResource;

/**
 * تفعيل اتصال قائم.
 */
final class ActivateConnectionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActivateConnectionAction $action,
    ) {}

    public function __invoke(string $connection): IntegrationConnectionResource
    {
        $connectionModel = IntegrationConnection::query()->findOrFail($connection);

        $this->authorize('activate', $connectionModel);

        $this->action->execute($connectionModel);

        return new IntegrationConnectionResource($connectionModel->refresh());
    }
}
