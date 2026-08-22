<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Organization\Domain\Models\Holiday;

final class DeleteHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Holiday|null $holiday */
        $holiday = $this->route('holiday');

        return $holiday !== null
            && (bool) $this->user()?->can('delete', $holiday);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
