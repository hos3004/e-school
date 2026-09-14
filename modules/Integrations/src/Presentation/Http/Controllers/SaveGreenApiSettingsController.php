<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Presentation\Http\Requests\SaveGreenApiSettingsRequest;

final class SaveGreenApiSettingsController extends Controller
{
    public function __invoke(SaveGreenApiSettingsRequest $request, GreenApiConnections $connections): RedirectResponse
    {
        $data = $request->validated();
        $connections->save(
            (string) data_get($request->user(), 'organization_id'),
            $data,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $data['reason'],
        );

        return to_route('console.settings')->with('success', __('console_settings.green_api.saved'));
    }
}
