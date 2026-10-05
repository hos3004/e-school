<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Groups\Domain\Models\Group;

/**
 * طلب ربط برنامج بمجموعة.
 */
final class AttachProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Group $group */
        $group = $this->route('group');

        return $this->user()->can('attachProgram', $group);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'program_id' => __('groups::attributes.program_id'),
            'reason' => __('groups::attributes.reason'),
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
