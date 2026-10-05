<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Requests\CreateAssignmentRequest;

final class CreateTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Assignment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => ['ar' => $this->input('title')], 'instructions' => ['ar' => $this->input('instructions')]]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $original = CreateAssignmentRequest::createFrom($this);
        $rules = $original->rules();
        unset($rules['course_id'], $rules['group_id'], $rules['staff_profile_id'], $rules['assigned_at'], $rules['due_at'], $rules['reason']);

        return [...$rules, 'target' => ['required', 'string'], 'due_local' => ['required', 'date_format:Y-m-d\TH:i']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('due_local')) {
                return;
            }
            $zone = (string) app(ConsoleContext::class)->forRequest($this)['timezone'];
            $due = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->string('due_local')->toString(), $zone)->utc();
            if ($due->lessThanOrEqualTo(now('UTC'))) {
                $validator->errors()->add('due_local', __('assignments::validation.due_after_assigned'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['target' => __('learning.teaching.target'), 'due_local' => __('learning.teaching.deadline'),
            'title.ar' => __('learning.teaching.task_title'), 'instructions.ar' => __('learning.teaching.instructions'),
            'max_score' => __('learning.teaching.max_score'), 'late_penalty_percent' => __('learning.teaching.late_penalty')];
    }
}
