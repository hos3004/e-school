<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل مشاهدة أو تنزيل.
 */
final class LogRecordingViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordings.recording.watch');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['view', 'download'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => __('recordings::fields.action'),
        ];
    }
}
