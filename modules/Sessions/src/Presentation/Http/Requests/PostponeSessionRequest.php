<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تأجيل حصة وإنشاء حصة التلافي.
 */
final class PostponeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.postpone');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'makeup_start' => ['required', 'date'],
            'makeup_end' => ['required', 'date', 'after:makeup_start'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'makeup_start' => __('sessions::fields.makeup_start'),
            'makeup_end' => __('sessions::fields.makeup_end'),
            'reason' => __('sessions::fields.reason'),
        ];
    }
}
