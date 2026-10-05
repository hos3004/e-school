<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SessionChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('learning.teacher.postponements.*') ? 'session.postpone.approve' : 'session.postpone.request') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['category' => ['required', Rule::in(['schedule', 'health', 'technical', 'personal'])],
            'note' => ['nullable', 'string', 'max:1500'],
            'proposed_local' => [Rule::requiredIf($this->routeIs('learning.sessions.postpone', 'learning.teacher.postponements.propose')), 'nullable', 'date_format:Y-m-d\TH:i']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['category' => __('learning.requests.category'), 'note' => __('learning.requests.note'), 'proposed_local' => __('learning.requests.proposed')];
    }
}
