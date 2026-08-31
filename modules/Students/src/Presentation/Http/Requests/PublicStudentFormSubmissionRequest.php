<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;

/** تحقق الخادم من الحقول الأساسية ومن مخطط النموذج المنشور نفسه. */
final class PublicStudentFormSubmissionRequest extends FormRequest
{
    private ?RegistrationForm $resolvedForm = null;

    public function authorize(): bool
    {
        return (bool) config('admission.self_registration.enabled', false)
            && $this->registrationForm()->is_active;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $questions = $this->registrationForm()->questions;
        $questionIds = $questions->pluck('id')->all();
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(StudentGender::class)],
            'country_id' => ['required', 'ulid'],
            'region_id' => ['required', 'ulid'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evaluation' => $questionIds === []
                ? ['prohibited']
                : ['array:'.implode(',', $questionIds)],
        ];

        foreach ($questions as $question) {
            /** @var RegistrationQuestion $question */
            $field = 'evaluation.'.$question->id;
            $presence = $question->is_required ? 'required' : 'nullable';

            $rules[$field] = match ($question->type) {
                RegistrationQuestionType::Text => [$presence, 'string', 'max:1000'],
                RegistrationQuestionType::Textarea => [$presence, 'string', 'max:5000'],
                RegistrationQuestionType::Number => [$presence, 'numeric'],
                RegistrationQuestionType::Select, RegistrationQuestionType::Radio => [
                    $presence,
                    'string',
                    Rule::in((array) $question->options),
                ],
                RegistrationQuestionType::Checkbox => [
                    $presence,
                    'array',
                    ...($question->is_required ? ['min:1'] : []),
                    'max:20',
                ],
            };

            if ($question->type === RegistrationQuestionType::Checkbox) {
                $rules[$field.'.*'] = ['string', Rule::in((array) $question->options)];
            }
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [$this->validateGeography(...)];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $phone = $this->input('phone');

        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null,
            'phone' => is_string($phone) && trim($phone) !== '' ? trim($phone) : null,
        ]);
    }

    public function registrationForm(): RegistrationForm
    {
        if ($this->resolvedForm instanceof RegistrationForm) {
            return $this->resolvedForm;
        }

        $query = RegistrationForm::query()->published();
        $slug = $this->route('formSlug');

        if (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        } else {
            $organizationId = (string) config('app.default_organization_id');
            if ($organizationId !== '') {
                $query->forOrganization($organizationId);
            }

            $defaultSlug = (string) config('admission.self_registration.default_form_slug', '');
            if ($defaultSlug !== '') {
                $query->where('slug', $defaultSlug);
            }
        }

        return $this->resolvedForm = $query
            ->with(['questions' => static fn ($questions) => $questions->active()])
            ->oldest('created_at')
            ->firstOrFail();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'evaluation.array' => __('students::validation.registration_answers_invalid'),
            'evaluation.prohibited' => __('students::validation.registration_answers_invalid'),
            'evaluation.*.required' => __('students::validation.registration_answer_required'),
            'evaluation.*.in' => __('students::validation.registration_answer_invalid'),
            'evaluation.*.*.in' => __('students::validation.registration_answer_invalid'),
        ];
    }

    private function validateGeography(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['country_id', 'region_id'])) {
            return;
        }

        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        if (!$geography->regionExistsIn((string) $this->input('region_id'), (string) $this->input('country_id'))) {
            $validator->errors()->add('region_id', __('students::validation.region_not_in_country'));
        }
    }
}
