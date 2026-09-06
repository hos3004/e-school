<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Console\QuranAvailabilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Staff\Application\Services\ConsoleTeacherAvailabilityService;

final readonly class LearningAvailabilityController
{
    public function __construct(private PortalData $data, private ConsoleTeacherAvailabilityService $service, private ConsoleContext $context) {}

    /** @return array{string,string} */
    private function actor(Request $request): array
    {
        $org = (string) $request->user()?->getAttribute('organization_id');
        $staff = $this->data->staffProfileId((string) $request->user()?->getAuthIdentifier(), $org);
        abort_if($staff === null, 403);

        return [$org, $staff];
    }

    public function index(Request $request): Response
    {
        [$org,$staff] = $this->actor($request);
        $timezone = (string) $this->context->forRequest($request)['timezone'];

        return Inertia::render('Learning/Availability', [
            'teacher' => $this->service->page($org, $staff), 'canCreate' => (bool) $request->user()?->can('staff.availability.create'),
            'defaults' => ['timezone' => $timezone, 'effective_from' => now($timezone)->toDateString()],
            'timezones' => $this->data->timezoneOptions(),
        ]);
    }

    public function store(QuranAvailabilityRequest $request): RedirectResponse
    {
        [$org,$staff] = $this->actor($request);
        $this->service->create($org, $staff, $request->validated(), (string) $request->user()?->getAuthIdentifier());

        return back()->with('success', __('console_quran.availability_saved'));
    }

    public function destroy(QuranAvailabilityRequest $request, string $availability): RedirectResponse
    {
        [$org,$staff] = $this->actor($request);
        $this->service->remove($org, $staff, $availability, (string) $request->user()?->getAuthIdentifier());

        return back()->with('success', __('console_quran.availability_saved'));
    }
}
