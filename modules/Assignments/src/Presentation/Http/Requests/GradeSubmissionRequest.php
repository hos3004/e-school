<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Assignments\Domain\Models\AssignmentSubmission;

/**
 * طلب رصد درجة تسليم — الدرجة إلزامية والتغذية الراجعة اختيارية.
 */
final class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AssignmentSubmission $submission */
        $submission = $this->route('submission');

        return $this->user()->can('grade', $submission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => __('assignments::validation.score_required'),
            'score.min' => __('assignments::validation.score_min'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'score' => __('assignments::attributes.score'),
            'feedback' => __('assignments::attributes.feedback'),
            'reason' => __('assignments::attributes.reason'),
        ];
    }
}
