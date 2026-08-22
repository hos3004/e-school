<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب بدء الحصة.
 */
final class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.start');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
