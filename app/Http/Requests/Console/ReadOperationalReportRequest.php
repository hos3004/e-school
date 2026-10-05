<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Modules\Reporting\Presentation\Http\Requests\ExportOperationalReportRequest;

final class ReadOperationalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('report.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...(new ExportOperationalReportRequest)->rules(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw (new ValidationException($validator))->redirectTo(route('console.reports'));
    }
}
