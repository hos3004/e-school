<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UnassignTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('assignment'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => __('groups::validation.reason_required')];
    }
}
