<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إنهاء الحصة وتركها بانتظار الاعتماد.
 */
final class EndSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.end');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
