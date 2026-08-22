<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب رصد غياب الطالب بدون إذن.
 */
final class MarkNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.mark_no_show');
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
