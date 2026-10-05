<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * فلاتر قائمة الحصص — كلها اختيارية، والافتراضي بلا فلترة (السلوك القديم).
 */
final class ListSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'from' => ['sometimes', 'date', 'required_with:to'],
            'to' => ['sometimes', 'date', 'required_with:from', 'after_or_equal:from'],
        ];
    }
}
