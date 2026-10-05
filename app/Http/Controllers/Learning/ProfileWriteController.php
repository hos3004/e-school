<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\PortalProfileController;
use Illuminate\Http\RedirectResponse;
use Modules\Identity\Application\Actions\UpdateUserProfile;
use Modules\Identity\Presentation\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Presentation\Http\Requests\UpdateProfileRequest;

final class ProfileWriteController extends Controller
{
    public function password(UpdatePasswordRequest $request, PortalProfileController $controller): RedirectResponse
    {
        $request->validate(['password' => ['required', 'confirmed']]);

        return $controller->password($request);
    }

    public function update(UpdateProfileRequest $request, UpdateUserProfile $update): RedirectResponse
    {
        // The existing action is reused; validate IANA zones before they reach all
        // schedules. The Arabic workspace does not change the legacy locale preference.
        // Never accept account IDs, roles, or audit data from a form.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['required', 'timezone:all'],
        ]);
        $update->execute($request->user(), $validated);

        return back()->with('success', __('learning.saved'));
    }
}
