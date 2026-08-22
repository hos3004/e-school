<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Modules\Integrations\Application\Actions\DisableConnectionAction;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Presentation\Http\Requests\DisableConnectionRequest;
use Modules\Integrations\Presentation\Http\Resources\IntegrationConnectionResource;

/**
 * إيقاف اتصال — السبب مطلوب للتدقيق.
 */
final class DisableConnectionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DisableConnectionAction $action,
    ) {}

    public function __invoke(DisableConnectionRequest $request, string $connection): IntegrationConnectionResource
    {
        $connectionModel = IntegrationConnection::query()->findOrFail($connection);

        $this->authorize('disable', $connectionModel);

        $this->action->execute($connectionModel, (string) $request->input('reason'));

        return new IntegrationConnectionResource($connectionModel->refresh());
    }
}
