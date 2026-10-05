<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Domain\Enums\Weekday;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\Locales;

final class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        return $organization !== null
            && (bool) $this->user()?->can('update', $organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'slug' => ['prohibited'],
            'logo_path' => ['nullable', 'string', 'max:512'],
            'default_timezone' => ['sometimes', 'string', 'timezone'],
            'default_currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'default_locale' => ['sometimes', 'string', Rule::in(Locales::supported())],
            'supported_locales' => ['nullable', 'array'],
            'supported_locales.*' => ['string', Rule::in(Locales::supported())],
            'week_starts_on' => ['sometimes', 'string', 'in:'.implode(',', array_column(Weekday::cases(), 'value'))],
            'settings' => ['prohibited'],
            'feature_overrides' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.prohibited' => __('organization::validation.slug_immutable'),
            'settings.prohibited' => __('organization::validation.use_settings_endpoint'),
            'name.ar.required_with' => __('organization::validation.name_ar_required'),
            'default_timezone.timezone' => __('organization::validation.invalid_timezone'),
            'default_currency.size' => __('organization::validation.currency_size'),
            'week_starts_on.in' => __('organization::validation.weekday_invalid'),
        ];
    }
}
