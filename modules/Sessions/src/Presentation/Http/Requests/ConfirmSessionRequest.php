<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تأكيد الحصة.
 */
final class ConfirmSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.confirm');
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
