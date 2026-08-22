<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Certificates\Domain\Enums\BadgeTier;

/**
 * طلب إنشاء شارة.
 */
final class StoreBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.badge.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash'],
            'name' => ['required', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.ar' => ['required_with:description', 'string', 'max:2000'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'icon_path' => ['nullable', 'string', 'max:2048'],
            'tier' => ['required', 'string', Rule::in(array_map(fn (BadgeTier $t): string => $t->value, BadgeTier::cases()))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('certificates::fields.organization'),
            'code' => __('certificates::fields.code'),
            'name' => __('certificates::fields.name'),
            'description' => __('certificates::fields.description'),
            'icon_path' => __('certificates::fields.icon'),
            'tier' => __('certificates::fields.tier'),
            'is_active' => __('certificates::fields.is_active'),
        ];
    }
}
