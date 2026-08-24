<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;

final class PublicStudentRegistrationController extends Controller
{
    public function showForm(GeographyQueries $geography): Response
    {
        $locale = app()->getLocale();
        $countries = array_map(static fn ($country): array => [
            'id' => $country->id, 'iso2' => $country->iso2,
            'name' => $country->name[$locale] ?? $country->name['en'] ?? $country->iso2,
        ], $geography->countries());
        $regions = [];
        foreach ($countries as $country) {
            $regions[$country['id']] = array_map(static fn ($region): array => [
                'id' => $region->id, 'name' => $region->name[$locale] ?? $region->name['en'] ?? $region->code,
            ], $geography->regionsOf($country['id']));
        }

        return Inertia::render('Auth/RegisterStudent', compact('countries', 'regions'));
    }

    public function store(Request $request, GeographyQueries $geography): RedirectResponse
    {
        $key = 'student-registration:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, __('auth.register.rate_limited'));
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone', Rule::unique('registration_applications', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(StudentGender::class)],
            'country_id' => ['required', 'string', 'size:26'],
            'region_id' => ['required', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_unless($geography->regionExistsIn($validated['region_id'], $validated['country_id']), 422, __('auth.register.invalid_region'));

        $organizationId = (string) config('app.default_organization_id');
        if ($organizationId === '') {
            $organizationId = (string) DB::table('organizations')->orderBy('created_at')->value('id');
        }
        abort_if($organizationId === '', 500, __('auth.register.organization_unavailable'));

        $application = RegistrationApplication::query()->create([
            'organization_id' => $organizationId,
            'full_name' => trim($validated['full_name']),
            'email' => isset($validated['email']) ? mb_strtolower(trim((string) $validated['email'])) : null,
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'], 'gender' => $validated['gender'],
            'country_id' => $validated['country_id'], 'region_id' => $validated['region_id'],
            'status' => 'submitted', 'submitted_at' => now(), 'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('register.submitted', ['id' => $application->id]);
    }

    public function showSubmitted(Request $request): Response
    {
        return Inertia::render('Auth/RegistrationSubmitted', ['applicationId' => $request->query('id')]);
    }

    public function showStatus(string $id): Response
    {
        $key = 'student-registration-status:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, __('auth.register.status_rate_limited'));
        }
        RateLimiter::hit($key, 60);
        $application = RegistrationApplication::query()->find($id);

        return Inertia::render('Auth/ApplicationStatus', ['application' => $application ? [
            'id' => $application->id, 'applicant_name' => $this->maskedName($application->full_name),
            'status' => $application->status->value, 'created_at' => $application->created_at?->toIso8601String(),
        ] : null]);
    }

    private function maskedName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        return implode(' ', array_map(static fn (string $part): string => mb_substr($part, 0, 1).'***', $parts));
    }
}
