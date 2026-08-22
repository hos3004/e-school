<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب قبول غياب الطالب بعذر.
 */
final class ExcuseAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.excuse');
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
            'reason' => __('sessions::fields.reason'),
        ];
    }
}
