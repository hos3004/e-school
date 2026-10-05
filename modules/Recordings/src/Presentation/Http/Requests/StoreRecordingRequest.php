<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تسجيل ملف تسجيل جديد لحصة.
 */
final class StoreRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recording.delete');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'session_id' => ['required', 'string', 'size:26'],
            'classroom_id' => ['required', 'string', 'size:26'],
            'provider' => ['required', 'string', 'max:50'],
            'external_recording_id' => ['required', 'string', 'max:255'],
            'disk' => ['required', 'string', 'max:50'],
            'path' => ['required', 'string', 'max:2000'],
            'thumbnail_path' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('recordings::fields.organization'),
            'session_id' => __('recordings::fields.session'),
            'classroom_id' => __('recordings::fields.classroom'),
            'provider' => __('recordings::fields.provider'),
            'external_recording_id' => __('recordings::fields.external_recording_id'),
            'disk' => __('recordings::fields.disk'),
            'path' => __('recordings::fields.path'),
        ];
    }
}
