<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Organization\Domain\Models\Organization;
use Modules\Organization\Domain\Enums\Weekday;

/**
 * @property-read array{name?: string}|mixed $name
 */
final class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Organization::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:organizations,slug'],
            'logo_path' => ['nullable', 'string', 'max:512'],
            'default_timezone' => ['required', 'string', 'timezone'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'default_locale' => ['required', 'string', 'in:ar,en,fr'],
            'supported_locales' => ['nullable', 'array'],
            'supported_locales.*' => ['string', 'in:ar,en,fr'],
            'week_starts_on' => ['required', 'string', 'in:'.implode(',', array_column(Weekday::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('organization::validation.name_required'),
            'name.ar.required' => __('organization::validation.name_ar_required'),
            'slug.required' => __('organization::validation.slug_required'),
            'slug.unique' => __('organization::errors.slug_taken', ['slug' => (string) $this->input('slug')]),
            'default_timezone.timezone' => __('organization::validation.invalid_timezone'),
            'default_currency.size' => __('organization::validation.currency_size'),
            'week_starts_on.in' => __('organization::validation.weekday_invalid'),
        ];
    }
}
