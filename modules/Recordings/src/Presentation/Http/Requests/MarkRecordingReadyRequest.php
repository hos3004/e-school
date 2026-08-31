<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarkRecordingReadyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recording.delete');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'thumbnail_path' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
