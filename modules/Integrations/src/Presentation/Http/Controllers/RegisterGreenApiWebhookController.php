<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Presentation\Http\Requests\RegisterGreenApiWebhookRequest;

final class RegisterGreenApiWebhookController extends Controller
{
    public function __invoke(RegisterGreenApiWebhookRequest $request, GreenApiConnections $connections): RedirectResponse
    {
        $data = $request->validated();
        $registered = $connections->registerWebhook(
            (string) data_get($request->user(), 'organization_id'),
            (string) $request->user()?->getAuthIdentifier(),
            (string) $data['reason'],
        );
        if (!$registered) {
            throw ValidationException::withMessages([
                'webhook' => __('console_settings.green_api.webhook_failed'),
            ]);
        }

        return to_route('console.settings')->with('success', __('console_settings.green_api.webhook_saved'));
    }
}
