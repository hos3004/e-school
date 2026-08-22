<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * طلب تحديث تفضيل إشعارات للمستخدم الحالي.
 */
final class UpdatePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:64'],
            'channel' => ['required', 'string', Rule::in(Channel::values())],
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => __('notifications::fields.category'),
            'channel' => __('notifications::fields.channel'),
            'enabled' => __('notifications::fields.enabled'),
        ];
    }
}
