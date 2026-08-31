<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Application\Actions\SubmitPublicRegistrationFormAction;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Presentation\Http\Requests\PublicStudentFormSubmissionRequest;

final class PublicStudentRegistrationController extends Controller
{
    public function showForm(GeographyQueries $geography, ?string $formSlug = null): Response
    {
        $form = $this->registrationForm($formSlug);
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

        return Inertia::render('Auth/RegisterStudent', [
            'countries' => $countries,
            'regions' => $regions,
            'registrationForm' => [
                'slug' => $form->slug,
                'title' => $form->localizedTitle(),
                'description' => $form->localizedDescription(),
            ],
            'questions' => $this->activeQuestions($form),
            'submitUrl' => route('register.student.form.store', ['formSlug' => $form->slug]),
        ]);
    }

    public function store(
        PublicStudentFormSubmissionRequest $request,
        SubmitPublicRegistrationFormAction $submit,
        ?string $formSlug = null,
    ): RedirectResponse {
        $application = $submit->execute($request->registrationForm(), $request->validated());

        return redirect()->route('register.submitted', ['id' => $application->id]);
    }

    /**
     * أسئلة التقييم المفعّلة تُعرض على النموذج، وإجاباتها تُخزَّن كلقطة
     * (نص السؤال + الإجابة) حتى لا يمس تعديل الأسئلة لاحقًا ما قُدم سابقًا.
     *
     * @return list<array{id: string, question: string, type: string, options: list<string>|null, required: bool}>
     */
    private function activeQuestions(RegistrationForm $form): array
    {
        $form->loadMissing(['questions' => static fn ($query) => $query->active()]);

        return $form->questions
            ->map(static fn (RegistrationQuestion $question): array => [
                'id' => $question->id,
                'question' => $question->localizedQuestion(),
                'type' => $question->type->value,
                'options' => $question->options,
                'required' => $question->is_required,
            ])
            ->all();
    }

    private function registrationForm(?string $slug): RegistrationForm
    {
        abort_unless((bool) config('admission.self_registration.enabled', false), 404);

        $query = RegistrationForm::query()->published();

        if (is_string($slug) && $slug !== '') {
            return $query->where('slug', $slug)->firstOrFail();
        }

        $organizationId = (string) config('app.default_organization_id');
        if ($organizationId !== '') {
            $query->forOrganization($organizationId);
        }

        $defaultSlug = (string) config('admission.self_registration.default_form_slug', '');
        if ($defaultSlug !== '') {
            $query->where('slug', $defaultSlug);
        }

        return $query->oldest('created_at')->firstOrFail();
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
