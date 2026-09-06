<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\QuranAvailabilityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Staff\Application\Services\ConsoleTeacherAvailabilityService;
use Shared\Support\BusinessRuleViolation;

final class QuranAvailabilityController extends Controller
{
    public function __construct(private readonly ConsoleTeacherAvailabilityService $service, private readonly ConsoleContext $context) {}

    public function index(Request $request, string $teacher): Response
    {
        $organizationId = (string) $request->user()?->organization_id;
        $timezone = (string) $this->context->forRequest($request)['school']['timezone'];

        return Inertia::render('Console/QuranAvailability', [
            'teacher' => $this->service->page($organizationId, $teacher),
            'canCreate' => $request->user()?->can('staff.availability.create') ?? false,
            'defaults' => ['timezone' => $timezone, 'effective_from' => now($timezone)->toDateString()],
        ]);
    }

    public function store(QuranAvailabilityRequest $request, string $teacher): RedirectResponse
    {
        return $this->perform(fn () => $this->service->create((string) $request->user()?->organization_id, $teacher, $request->validated(), (string) $request->user()?->getAuthIdentifier()));
    }

    public function decide(QuranAvailabilityRequest $request, string $teacher, string $availability): RedirectResponse
    {
        return $this->perform(fn () => $this->service->decide((string) $request->user()?->organization_id, $teacher, $availability, (string) $request->validated('decision'), (string) $request->user()?->getAuthIdentifier()));
    }

    public function destroy(QuranAvailabilityRequest $request, string $teacher, string $availability): RedirectResponse
    {
        return $this->perform(fn () => $this->service->remove((string) $request->user()?->organization_id, $teacher, $availability, (string) $request->user()?->getAuthIdentifier()));
    }

    private function perform(callable $operation): RedirectResponse
    {
        try {
            $operation();
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['availability' => $exception->getMessage()]);
        }

        return back()->with('success', __('console_quran.availability_saved'));
    }
}
