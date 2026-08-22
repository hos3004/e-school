<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;

final class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return $target !== null
            && $this->user() !== null
            && $this->user()->can('changeStatus', $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => __('identity::validation.status_required'),
            'status.Illuminate\\Validation\\Rules\\Enum' => __('identity::validation.status_invalid'),
            'reason.required' => __('identity::errors.status_reason_required'),
            'reason.min' => __('identity::validation.reason_too_short'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('identity::labels.status'),
            'reason' => __('identity::labels.reason'),
        ];
    }
}
