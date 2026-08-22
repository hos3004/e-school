<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب منح شارة لمستخدم.
 */
final class AwardBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.award.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'size:26'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('certificates::fields.user'),
            'reason' => __('certificates::fields.reason'),
        ];
    }
}
