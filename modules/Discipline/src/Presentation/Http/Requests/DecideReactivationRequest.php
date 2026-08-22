<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;

/**
 * طلب حسم إعادة التفعيل — القرار مقبول أو مرفوض فقط،
 * وملاحظة القرار إلزامية وفق قاعدة التدقيق.
 */
final class DecideReactivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ReactivationRequest $reactivation */
        $reactivation = $this->route('reactivation');

        return $this->user()->can('decide', $reactivation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                Rule::enum(ReactivationStatus::class),
                Rule::in([
                    ReactivationStatus::Approved->value,
                    ReactivationStatus::Rejected->value,
                ]),
            ],
            'decision_note' => ['required', 'string', 'min:3', 'max:2000'],
            'assessment_attempt_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.in' => __('discipline::validation.invalid_decision'),
            'decision_note.required' => __('discipline::validation.decision_note_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => __('discipline::attributes.decision'),
            'decision_note' => __('discipline::attributes.decision_note'),
            'assessment_attempt_id' => __('discipline::attributes.assessment_attempt_id'),
        ];
    }
}
