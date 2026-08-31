<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Groups\Domain\Models\Group;

final class ChangeGroupStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Group|null $group */
        $group = $this->route('group');

        if ($group === null) {
            return false;
        }

        return $this->routeIs('groups.activate')
            ? $this->user()->can('activate', $group)
            : $this->user()->can('complete', $group);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('groups::validation.reason_required'),
        ];
    }
}
