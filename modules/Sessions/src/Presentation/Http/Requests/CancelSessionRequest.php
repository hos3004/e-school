<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب إلغاء حصة.
 */
final class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('session.cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'as' => ['required', 'string', Rule::in([
                'cancelled_by_student',
                'cancelled_by_teacher',
                'cancelled_by_school',
            ])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'as' => __('sessions::fields.cancelled_as'),
            'reason' => __('sessions::fields.reason'),
        ];
    }
}
