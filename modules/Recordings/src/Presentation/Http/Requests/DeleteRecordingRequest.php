<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب حذف تسجيل — السبب إجباري وفق قاعدة التدقيق.
 */
final class DeleteRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordings.recording.delete');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('recordings::fields.reason'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('recordings::errors.deletion_reason_required'),
        ];
    }
}
